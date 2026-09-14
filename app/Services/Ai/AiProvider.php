<?php

namespace App\Services\Ai;

interface AiProvider
{
    /**
     * @param  string  $kind  preedit|tags|translate|generate
     * @param  array  $payload  text, headline, brief, language, stylePrompt
     * @return array{headline?: string, brief?: string, body?: string, category?: array{name: string}, tags?: string[], error?: string}
     */
    public function call(string $kind, array $payload): AiResult;
}
