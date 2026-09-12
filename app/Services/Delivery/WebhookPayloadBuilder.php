<?php

namespace App\Services\Delivery;

use App\Models\Story;

class WebhookPayloadBuilder
{
    public function build(Story $story, string $event): array
    {
        $story->loadMissing(['category', 'tags', 'media', 'owner']);

        $category = null;
        if ($story->category) {
            $category = [
                'slug' => $story->category->slug,
                'name' => $story->category->name_en ?? $story->category->name ?? $story->category->slug,
            ];
        }

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
                'media' => $story->media ? $story->media->map(fn ($m) => [
                    'public_id' => trim((string) $m->public_id),
                    'kind' => $m->kind,
                    'caption' => $m->pivot?->caption_override ?: $m->caption,
                    'download_url' => url("/api/v1/media/{$m->id}/download"),
                ])->toArray() : [],
            ],
        ];
    }
}
