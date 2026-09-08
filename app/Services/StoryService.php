<?php

namespace App\Services;

use App\Jobs\FanoutStory;
use App\Models\Story;
use App\Models\StoryNote;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class StoryService
{
    private const TRANSITIONS = [
        'draft' => ['in_review', 'killed'],
        'in_review' => ['changes_requested', 'approved', 'killed'],
        'changes_requested' => ['draft', 'in_review', 'killed'],
        'approved' => ['published', 'killed'],
        'published' => ['killed', 'archived'],
        'killed' => [],
        'archived' => [],
    ];

    public function __construct(
        private RevisionService $revisions,
        private AuditLogRepository $audit,
    ) {}

    public function createDraft(array $data, User $actor): Story
    {
        if (isset($data['body_html'])) {
            $data['body_html'] = HtmlSanitizer::clean($data['body_html']);
        }
        $data = array_merge($data, [
            'status' => 'draft',
            'owner_id' => $actor->id,
            'created_by' => $actor->id,
            'version' => 1,
            'body_text' => HtmlSanitizer::text($data['body_html'] ?? ''),
        ]);

        return DB::transaction(function () use ($data, $actor) {
            $story = Story::create($data);
            $this->revisions->snapshot($story, $actor);
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => 'created',
                'from_status' => null,
                'to_status' => 'draft',
            ]);

            return $story;
        });
    }

    public function updateDraft(Story $story, array $data, int $expectedVersion, User $actor): Story
    {
        if ((int) $story->version !== $expectedVersion) {
            throw new ConflictHttpException('Version conflict — stale save');
        }
        if (isset($data['body_html'])) {
            $data['body_html'] = HtmlSanitizer::clean($data['body_html']);
            $data['body_text'] = HtmlSanitizer::text($data['body_html']);
            if (! empty($story->ai_touched['body']) && $data['body_html'] !== $story->body_html) {
                $ai = $story->ai_touched;
                unset($ai['body']);
                $data['ai_touched'] = empty($ai) ? null : $ai;
            }
        }

        return DB::transaction(function () use ($story, $data, $actor) {
            $story->update(array_merge($data, ['version' => $story->version + 1]));
            $this->revisions->snapshot($story->refresh(), $actor);

            return $story;
        });
    }

    public function acquireLock(Story $story, User $actor): void
    {
        $story->update(['locked_by' => $actor->id, 'locked_at' => now()]);
    }

    public function takeOver(Story $story, User $actor, ?int $expectedVersion = null): void
    {
        if ($expectedVersion !== null && (int) $story->version !== $expectedVersion) {
            throw new ConflictHttpException('Version conflict — story has been modified');
        }

        $prev = $story->lockedBy ?? $story->owner;
        $prevName = $prev ? $prev->name : ($story->locked_by ? "user #{$story->locked_by}" : 'previous owner');
        DB::transaction(function () use ($story, $actor, $prevName) {
            $story->update([
                'locked_by' => $actor->id,
                'locked_at' => now(),
                'owner_id' => $actor->id,
            ]);
            $story->notes()->create([
                'user_id' => $actor->id,
                'kind' => 'system',
                'is_internal' => true,
                'body' => "Taken over by {$actor->name} from {$prevName} — shift handover",
            ]);
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => 'handover',
                'from_status' => $story->status,
                'to_status' => $story->status,
                'payload' => ['from_user' => $prevName, 'to_user' => $actor->name],
            ]);
            $this->audit->log('handover', 'Story', $story->id, ['from' => $prevName, 'to' => $actor->name]);
        });
    }

    public function addNote(Story $story, string $body, User $actor, ?string $kind = null): StoryNote
    {
        return app(NoteService::class)->add($story, $actor, $body, $kind);
    }

    public function transition(Story $story, string $to, User $actor, string $gate = 'manual'): Story
    {
        $from = $story->status;
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new UnprocessableEntityHttpException("Invalid transition {$from} → {$to}");
        }
        if ($to === 'published') {
            if (! empty($story->ai_touched) && ! $story->is_breaking) {
                $cfg = DB::table('settings')->where('key', 'ai.desk')->value('value');
                $cfg = is_string($cfg) ? json_decode($cfg, true) : $cfg;
                $allowAuto = ! empty($cfg['autoPublish']) && in_array($story->category_id, (array) ($cfg['autoCats'] ?? []), true);
                if (! $allowAuto) {
                    throw new UnprocessableEntityHttpException('AI-touched fields require review before publish');
                }
            }
            if ($story->embargo_until && $story->embargo_until->isFuture()) {
                throw new UnprocessableEntityHttpException('Embargo still active');
            }
        }

        return DB::transaction(function () use ($story, $from, $to, $actor, $gate) {
            $extra = [];
            if ($to === 'published' && ! $story->published_at) {
                $extra['published_at'] = now();
            }
            $story->update(array_merge(['status' => $to, 'version' => $story->version + 1], $extra));
            $this->revisions->snapshot($story, $actor);
            $action = match ($to) {
                'published' => $gate === 'auto' ? 'auto_published' : 'published',
                'killed' => 'killed',
                'archived' => 'archived',
                default => $to === 'in_review' ? 'sent_to_review' : $to,
            };
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'payload' => $to === 'published' ? ['gate' => $gate] : null,
            ]);
            $this->audit->log(
                $action,
                'Story',
                $story->id,
                ['from' => $from, 'to' => $to],
            );
            Cache::forget('portal:feed:*');
            Cache::forget('feed:v1:*');
            if ($to === 'published') {
                DB::table('index_outbox')->insert([
                    'index_name' => 'main',
                    'op' => 'upsert',
                    'document_id' => $story->public_id,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => now(),
                ]);
                dispatch(new FanoutStory($story->id))->afterResponse();
            }
            if ($to === 'killed') {
                dispatch(new FanoutStory($story->id))->afterResponse();
            }

            return $story->refresh();
        });
    }
}
