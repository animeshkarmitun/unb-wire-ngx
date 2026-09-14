<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class StubAiProvider implements AiProvider
{
    public function call(string $kind, array $payload): AiResult
    {
        $text = $payload['text'] ?? '';

        $pack = match ($kind) {
            'preedit' => [
                'headline' => $payload['headline'] ?? 'AI: '.Str::limit($text, 60).' — polished',
                'brief' => 'AI brief — '.$text,
                'category' => ['name' => 'Business'],
                'tags' => ['economy', 'bangladesh'],
                'body' => '<p>AI polished body for: '.e($text).'</p>',
            ],
            'tags' => [
                'category' => ['name' => 'Sports'],
                'tags' => ['cricket', 'world-cup'],
            ],
            'translate' => [
                'headline' => 'বাংলা শিরোনাম',
                'body' => '<p>বাংলা অনুবাদ</p>',
            ],
            'generate' => [
                'headline' => 'AI Generated headline',
                'brief' => 'AI brief',
                'body' => '<p>AI generated body from raw: '.e($text).'</p>',
                'category' => ['name' => 'Bangladesh'],
                'tags' => ['breaking'],
            ],
            default => ['note' => 'unknown kind'],
        };

        return new AiResult(
            headline: $pack['headline'] ?? null,
            brief: $pack['brief'] ?? null,
            body: $pack['body'] ?? null,
            categoryName: $pack['category']['name'] ?? null,
            tags: $pack['tags'] ?? [],
            tokensIn: 100,
            tokensOut: 200,
            costMicros: 1000,
            model: 'stub',
        );
    }
}
