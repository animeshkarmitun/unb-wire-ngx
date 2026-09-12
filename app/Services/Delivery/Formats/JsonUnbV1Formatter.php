<?php

namespace App\Services\Delivery\Formats;

use App\Models\Story;
use App\Services\Delivery\WireOutput;

class JsonUnbV1Formatter
{
    public function format(Story $story): WireOutput
    {
        $data = [
            'public_id' => $story->public_id,
            'headline' => $story->headline,
            'sub_head' => $story->sub_head,
            'brief' => $story->brief,
            'body_html' => $story->body_html,
            'category' => $story->category->name_en ?? 'General',
            'language' => $story->language,
            'published_at' => $story->published_at?->toIso8601String(),
            'status' => $story->status,
            'is_breaking' => (bool) $story->is_breaking,
            'tags' => $story->tags ? $story->tags->pluck('name')->values()->all() : [],
            'caps' => $story->media ? $story->media->pluck('caption')->filter()->values()->all() : [],
            'media' => $story->media ? $story->media->map(fn ($m) => [
                'id' => $m->id,
                'public_id' => $m->public_id,
                'caption' => $m->caption ?: $m->title,
                'kind' => $m->kind,
                'credit' => $m->credit ?? 'UNB',
            ])->values()->all() : [],
            'has_video' => $story->media ? $story->media->where('kind', 'video')->isNotEmpty() : false,
        ];

        return new WireOutput(
            content: json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            filename: "UNB-{$story->public_id}.json",
            contentType: 'application/json'
        );
    }
}
