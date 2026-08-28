<?php

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use App\Services\HtmlSanitizer;

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

    public function createDraft(array $data, User $actor): Story
    {
        if (isset($data['body_html'])) $data['body_html'] = HtmlSanitizer::clean($data['body_html']);
        $data = array_merge($data, [
            'status' => 'draft',
            'owner_id' => $actor->id,
            'created_by' => $actor->id,
            'version' => 1,
            'body_text' => HtmlSanitizer::text($data['body_html'] ?? ''),
        ]);
        return DB::transaction(function () use ($data, $actor) {
            $story = Story::create($data);
            $story->versions()->create([
                'version' => 1,
                'snapshot' => ['headline' => $story->headline, 'body_html' => $story->body_html],
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);
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
            $story->versions()->create([
                'version' => $story->version,
                'snapshot' => ['headline' => $story->headline, 'brief' => $story->brief, 'body_html' => $story->body_html],
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);
            return $story->refresh();
        });
    }

    public function acquireLock(Story $story, User $actor): void
    {
        $story->update(['locked_by' => $actor->id, 'locked_at' => now()]);
    }

    public function takeOver(Story $story, User $actor): void
    {
        $prev = $story->locked_by;
        DB::transaction(function () use ($story, $actor, $prev) {
            $story->update(['locked_by' => $actor->id, 'locked_at' => now()]);
            $story->notes()->create([
                'user_id' => $actor->id,
                'kind' => 'system',
                'body' => "Lock taken over from user #{$prev} by #{$actor->id}",
            ]);
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => 'take_over',
                'from_status' => $story->status,
                'to_status' => $story->status,
            ]);
        });
    }

    public function transition(Story $story, string $to, User $actor): Story
    {
        $from = $story->status;
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new UnprocessableEntityHttpException("Invalid transition {$from} → {$to}");
        }
        if ($to === 'published') {
            if (! empty($story->ai_touched) && ! $story->is_breaking) {
                $cfg = DB::table('settings')->where('key','ai.desk')->value('value');
                $cfg = is_string($cfg) ? json_decode($cfg,true) : $cfg;
                $allowAuto = !empty($cfg['autoPublish']) && in_array($story->category_id, (array)($cfg['autoCats'] ?? []), true);
                if(! $allowAuto) throw new UnprocessableEntityHttpException('AI-touched fields require review before publish');
            }
            if ($story->embargo_until && $story->embargo_until->isFuture()) {
                throw new UnprocessableEntityHttpException('Embargo still active');
            }
        }
        return DB::transaction(function () use ($story, $from, $to, $actor) {
            $extra = [];
            if ($to === 'published' && ! $story->published_at) {
                $extra['published_at'] = now();
            }
            $story->update(array_merge(['status' => $to], $extra));
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => $to,
                'from_status' => $from,
                'to_status' => $to,
            ]);
            \Illuminate\Support\Facades\Cache::forget('portal:feed:*');
            \Illuminate\Support\Facades\Cache::forget('feed:v1:*');
            if ($to === 'published') {
                DB::table('index_outbox')->insert([
                    'index_name' => 'main',
                    'op' => 'upsert',
                    'document_id' => $story->public_id,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => now(),
                ]);
            }
            if ($to === 'killed') {
                dispatch(new \App\Jobs\FanoutStory($story->id))->afterResponse();
            }
            return $story->refresh();
        });
    }
}
