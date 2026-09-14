<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiProvider implements AiProvider
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.openai.api_key', '');
        $this->model = $model ?? config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    public function call(string $kind, array $payload): AiResult
    {
        $prompt = $this->buildPrompt($kind, $payload);
        $stylePrompt = $payload['stylePrompt'] ?? config('services.openai.style_prompt', '');

        $messages = array_filter([
            ['role' => 'system', 'content' => $stylePrompt ?: $this->defaultSystemPrompt()],
            ['role' => 'user', 'content' => $prompt],
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->baseUrl.'/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'OpenAI API error: '.$response->status();

                return new AiResult(error: $error);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];

            $tokensIn = (int) ($usage['prompt_tokens'] ?? 0);
            $tokensOut = (int) ($usage['completion_tokens'] ?? 0);
            $costMicros = (int) round(($tokensIn + $tokensOut) * 0.15);

            $parsed = $this->parseResponse($kind, $content);

            return new AiResult(
                headline: $parsed['headline'] ?? null,
                brief: $parsed['brief'] ?? null,
                body: $parsed['body'] ?? null,
                categoryName: $parsed['category']['name'] ?? ($parsed['categoryName'] ?? null),
                tags: $parsed['tags'] ?? [],
                tokensIn: $tokensIn,
                tokensOut: $tokensOut,
                costMicros: $costMicros,
                model: $data['model'] ?? $this->model,
            );
        } catch (\Exception $e) {
            return new AiResult(error: 'AI request failed: '.$e->getMessage());
        }
    }

    private function buildPrompt(string $kind, array $payload): string
    {
        $text = $payload['text'] ?? '';
        $headline = $payload['headline'] ?? '';
        $brief = $payload['brief'] ?? '';

        return match ($kind) {
            'preedit' => <<<PROMPT
You are a wire news copy editor. Given the raw text below, return a JSON object with:
- "headline": a wire-style headline (inverted pyramid, past tense, max 80 chars)
- "brief": a one-sentence summary (max 150 chars)
- "body": the full story in wire format (HTML paragraphs, dateline "DHAKA, Mon DD —", end with "END/UNB")
- "category": {"name": one of "Bangladesh","World","Business","Sports","Entertainment","Technology","Health","Environment"}
- "tags": array of 2-5 relevant lowercase tags

Raw text:
{$text}

Current headline (if any): {$headline}
Current brief (if any): {$brief}

Return ONLY valid JSON. Do not invent names, figures or quotes. If a fact is uncertain, flag it with [VERIFY].
PROMPT,
            'tags' => <<<PROMPT
Given this news text, return a JSON object with:
- "category": {"name": one of "Bangladesh","World","Business","Sports","Entertainment","Technology","Health","Environment"}
- "tags": array of 2-5 relevant lowercase tags

Text:
{$text}

Return ONLY valid JSON.
PROMPT,
            'translate' => <<<PROMPT
Translate the following news content to Bangla (Bengali). Return a JSON object with:
- "headline": translated headline in Bangla
- "body": translated body in Bangla (HTML paragraphs, keep wire format markers)

Content:
Headline: {$headline}
Body: {$text}

Return ONLY valid JSON.
PROMPT,
            'generate' => <<<PROMPT
Given this raw input, generate a complete wire news story. Return a JSON object with:
- "headline": wire-style headline (inverted pyramid, past tense, max 80 chars)
- "brief": one-sentence summary (max 150 chars)
- "body": full story in wire format (HTML paragraphs, dateline, end with "END/UNB")
- "category": {"name": one of "Bangladesh","World","Business","Sports","Entertainment","Technology","Health","Environment"}
- "tags": array of 2-5 relevant lowercase tags

Raw input:
{$text}

Return ONLY valid JSON. Do not invent names, figures or quotes.
PROMPT,
            default => "Process this input and return JSON: {$text}",
        };
    }

    private function parseResponse(string $kind, string $content): array
    {
        $cleaned = trim($content);
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        }

        $parsed = json_decode($cleaned, true);
        if (! is_array($parsed)) {
            return match ($kind) {
                'preedit' => ['headline' => Str::limit($cleaned, 80), 'body' => '<p>'.$cleaned.'</p>'],
                'translate' => ['headline' => Str::limit($cleaned, 80), 'body' => '<p>'.$cleaned.'</p>'],
                default => ['headline' => Str::limit($cleaned, 80)],
            };
        }

        return $parsed;
    }

    private function defaultSystemPrompt(): string
    {
        return 'You are a UNB wire news copy editor. Rules: inverted pyramid; active voice; past tense for events; attribute every claim (said, according to); no adjectives of judgement; spell out numbers one to nine; dateline format "DHAKA, Mon DD —"; end with "END/UNB"; never invent names, figures or quotes; if a fact is uncertain, flag it with [VERIFY]. Always respond with valid JSON only.';
    }
}
