<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryVersion;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\StoryRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RevisionService
{
    private const SNAPSHOT_FIELDS = [
        'headline',
        'sub_head',
        'brief',
        'body_html',
        'category_id',
        'dateline_city',
        'dateline_at',
        'priority',
        'is_breaking',
        'language',
        'embargo_until',
    ];

    private const RESTORE_STATES = ['draft', 'in_review', 'changes_requested'];

    public function __construct(
        private StoryRepository $repo,
    ) {}

    public function snapshot(Story $story, User $actor): StoryVersion
    {
        $snap = [];
        foreach (self::SNAPSHOT_FIELDS as $f) {
            $val = $story->$f;
            $snap[$f] = match ($f) {
                'is_breaking' => (bool) ($val ?? false),
                'priority' => $val ?: 'routine',
                default => $val,
            };
        }
        if (! $story->relationLoaded('tags')) {
            $story->load('tags');
        }
        $snap['tags'] = $story->tags->pluck('name')->all();

        return $this->repo->createVersion($story, $snap, $actor->id);
    }

    public function diff(StoryVersion $a, StoryVersion $b): array
    {
        $snapA = $a->snapshot;
        $snapB = $b->snapshot;
        $fields = [];
        foreach (self::SNAPSHOT_FIELDS as $f) {
            $va = $snapA[$f] ?? null;
            $vb = $snapB[$f] ?? null;
            if ($va !== $vb) {
                $fields[] = ['field' => $f, 'from' => $va, 'to' => $vb];
            }
        }
        $tagsA = $snapA['tags'] ?? [];
        $tagsB = $snapB['tags'] ?? [];
        sort($tagsA);
        sort($tagsB);
        if ($tagsA !== $tagsB) {
            $fields[] = ['field' => 'tags', 'from' => $tagsA, 'to' => $tagsB];
        }
        $bodyA = $snapA['body_html'] ?? '';
        $bodyB = $snapB['body_html'] ?? '';

        return ['fields' => $fields, 'body' => $this->tokenDiff($bodyA, $bodyB)];
    }

    public function restore(Story $story, int $version, User $actor, ?int $expectedVersion = null): Story
    {
        if (! in_array($story->status, self::RESTORE_STATES, true)) {
            throw new UnprocessableEntityHttpException('Cannot restore a '.$story->status.' story — must be draft, in_review, or changes_requested');
        }
        if ($expectedVersion !== null && (int) $story->version !== $expectedVersion) {
            throw new ConflictHttpException('Version conflict — story has been modified');
        }
        $story->update(['locked_by' => $actor->id, 'locked_at' => now()]);
        $target = $story->versions()->where('version', $version)->first();
        if (! $target) {
            throw new UnprocessableEntityHttpException("Version {$version} not found for this story");
        }

        return DB::transaction(function () use ($story, $target, $actor, $version) {
            $fromVersion = $story->version;
            $snap = $target->snapshot;
            $data = [];
            foreach (self::SNAPSHOT_FIELDS as $f) {
                if (array_key_exists($f, $snap)) {
                    $data[$f] = $snap[$f];
                }
            }
            $data['version'] = $story->version + 1;
            if (isset($data['body_html'])) {
                $data['body_text'] = HtmlSanitizer::text($data['body_html']);
            }
            $data['is_breaking'] = $data['is_breaking'] ?? false;
            $story->update($data);
            if (isset($snap['tags'])) {
                $story->tags()->sync(
                    \App\Models\Tag::whereIn('name', $snap['tags'])->pluck('id')->all()
                );
            }
            $this->snapshot($story, $actor);
            $story->events()->create([
                'actor_id' => $actor->id,
                'action' => 'restored',
                'from_status' => $story->status,
                'to_status' => $story->status,
                'payload' => ['from_version' => $fromVersion, 'to_version' => $version],
            ]);
            app(\App\Repositories\AuditLogRepository::class)->log(
                'restored',
                'Story',
                $story->id,
                ['from_version' => $fromVersion, 'to_version' => $version],
            );

            return $story->refresh();
        });
    }

    private function tokenDiff(string $a, string $b): array
    {
        $tokA = preg_split('/\s+/', $a, -1, PREG_SPLIT_NO_EMPTY);
        $tokB = preg_split('/\s+/', $b, -1, PREG_SPLIT_NO_EMPTY);
        $segs = [];
        $i = 0;
        $j = 0;
        while ($i < count($tokA) && $j < count($tokB)) {
            if ($tokA[$i] === $tokB[$j]) {
                $segs[] = ['token' => $tokA[$i], 'changed' => false];
                $i++;
                $j++;
            } else {
                $nextB = array_search($tokA[$i], array_slice($tokB, $j), true);
                $nextA = array_search($tokB[$j], array_slice($tokA, $i), true);
                if ($nextB === false && $nextA === false) {
                    $segs[] = ['token' => $tokA[$i], 'changed' => true, 'type' => 'removed'];
                    $segs[] = ['token' => $tokB[$j], 'changed' => true, 'type' => 'added'];
                    $i++;
                    $j++;
                } elseif ($nextA !== false && ($nextB === false || $nextA <= $nextB)) {
                    $segs[] = ['token' => $tokB[$j], 'changed' => true, 'type' => 'added'];
                    $j++;
                } else {
                    $segs[] = ['token' => $tokA[$i], 'changed' => true, 'type' => 'removed'];
                    $i++;
                }
            }
        }
        for (; $i < count($tokA); $i++) {
            $segs[] = ['token' => $tokA[$i], 'changed' => true, 'type' => 'removed'];
        }
        for (; $j < count($tokB); $j++) {
            $segs[] = ['token' => $tokB[$j], 'changed' => true, 'type' => 'added'];
        }

        return array_values(array_filter($segs, fn ($s) => $s['changed']));
    }
}
