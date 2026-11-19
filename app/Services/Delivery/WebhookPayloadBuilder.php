<?php

namespace App\Services\Delivery;

use App\Models\MediaAsset;
use App\Models\Story;

class WebhookPayloadBuilder
{
    public function build(Story $story, string $event, ?iterable $visibleMedia = null): array
    {
        $story->loadMissing(['category', 'tags', 'media', 'owner']);

        $category = null;
        if ($story->category) {
            $category = [
                'slug' => $story->category->slug,
                'name' => $story->category->name_en ?? $story->category->name ?? $story->category->slug,
            ];
        }

        $media = $visibleMedia ?? ($story->media
            ? $story->media->reject(fn (MediaAsset $m) => $m->isEmbargoed() || $m->pivot?->role === 'body')
            : collect());

        return [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'public_id' => trim((string) $story->public_id),
                'headline' => $story->headline,
                'summary' => $story->brief,
                'body_html' => $story->body_html,
                'language' => $story->language,
                'author' => $story->owner ? $story->owner->name : 'Staff Reporter',
                'is_breaking' => (bool) $story->is_breaking,
                'category' => $category,
                'tags' => $story->tags ? $story->tags->pluck('name')->toArray() : [],
                'published_at' => $story->published_at ? $story->published_at->toIso8601String() : null,
                'media' => collect($media)
                    ->reject(fn ($m) => $m->pivot?->role === 'body')
                    ->values()
                    ->map(fn ($m) => [
                        'public_id' => trim((string) $m->public_id),
                        'kind' => $m->kind,
                        'caption' => $m->pivot?->caption_override ?: $m->caption,
                        'download_url' => url("/api/v1/media/{$m->id}/download"),
                    ])->toArray(),
            ],
        ];
    }
}
