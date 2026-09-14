<?php

namespace App\Services\Ai;

class AiResult
{
    public function __construct(
        public readonly ?string $headline = null,
        public readonly ?string $brief = null,
        public readonly ?string $body = null,
        public readonly ?string $categoryName = null,
        public readonly array $tags = [],
        public readonly ?string $error = null,
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
        public readonly int $costMicros = 0,
        public readonly string $model = 'unknown',
    ) {}

    public function isError(): bool
    {
        return $this->error !== null;
    }

    public function toPack(): array
    {
        if ($this->isError()) {
            return ['error' => $this->error];
        }

        $pack = [];
        if ($this->headline !== null) {
            $pack['headline'] = $this->headline;
        }
        if ($this->brief !== null) {
            $pack['brief'] = $this->brief;
        }
        if ($this->body !== null) {
            $pack['body'] = $this->body;
        }
        if ($this->categoryName !== null) {
            $pack['category'] = ['name' => $this->categoryName];
        }
        if (! empty($this->tags)) {
            $pack['tags'] = $this->tags;
        }

        return $pack;
    }
}
