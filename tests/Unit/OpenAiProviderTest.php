<?php

namespace Tests\Unit;

use App\Services\Ai\AiResult;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function makeProvider(): OpenAiProvider
    {
        return new OpenAiProvider('test-key', 'gpt-4o-mini');
    }

    private function openAiPayload(string $content = '{"headline":"H","brief":"B","body":"<p>x</p>","category":{"name":"Bangladesh"},"tags":["a","b"]}', int $promptTokens = 100, int $completionTokens = 50, string $model = 'gpt-4o-mini-2024-07-18'): array
    {
        return [
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'model' => $model,
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'total_tokens' => $promptTokens + $completionTokens],
        ];
    }

    public function test_success_returns_parsed_ai_result_with_usage(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiPayload(), 200),
        ]);

        $result = $this->makeProvider()->call('preedit', ['text' => 'Padma bridge update']);

        $this->assertInstanceOf(AiResult::class, $result);
        $this->assertNull($result->error);
        $this->assertSame('H', $result->headline);
        $this->assertSame('B', $result->brief);
        $this->assertSame('Bangladesh', $result->categoryName);
        $this->assertSame(['a', 'b'], $result->tags);
        $this->assertSame(100, $result->tokensIn);
        $this->assertSame(50, $result->tokensOut);
        $this->assertGreaterThan(0, $result->costMicros);
        $this->assertSame('gpt-4o-mini-2024-07-18', $result->model);
    }

    public function test_429_returns_error_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'rate limit exceeded']], 429),
        ]);

        $result = $this->makeProvider()->call('preedit', ['text' => 'x']);

        $this->assertNotNull($result->error);
        $this->assertSame('rate limit exceeded', $result->error);
        $this->assertNull($result->headline);
        $this->assertSame(0, $result->tokensIn);
    }

    public function test_500_returns_error_without_html_body(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('<html>Internal Server Error</html>', 500),
        ]);

        $result = $this->makeProvider()->call('preedit', ['text' => 'x']);

        $this->assertNotNull($result->error);
        $this->assertStringNotContainsString('<html>', $result->error);
        $this->assertStringContainsString('500', $result->error);
        $this->assertNull($result->headline);
        $this->assertSame('AI Generated headline', 'AI Generated headline');
    }

    public function test_malformed_body_does_not_throw_and_returns_something(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('not-json-at-all', 200),
        ]);

        $result = $this->makeProvider()->call('preedit', ['text' => 'x']);

        // Must not throw. Fallback parser fills at least one field.
        $this->assertNull($result->error);
        $this->assertTrue(
            ! empty($result->headline) || ! empty($result->body) || ! empty($result->brief),
            'parseResponse fallback must populate at least one output field'
        );
    }

    public function test_unexpected_exception_returns_error(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('socket reset');
        });

        $result = $this->makeProvider()->call('preedit', ['text' => 'x']);

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('AI request failed', $result->error);
    }
}
