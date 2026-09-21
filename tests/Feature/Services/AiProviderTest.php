<?php

namespace Tests\Feature\Services;

use App\Services\Ai\AiResult;
use App\Services\Ai\StubAiProvider;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    public function test_ai_result_is_error_when_error_set(): void
    {
        $result = new AiResult(error: 'Something failed');

        $this->assertTrue($result->isError());
    }

    public function test_ai_result_is_not_error_when_no_error(): void
    {
        $result = new AiResult(headline: 'Test');

        $this->assertFalse($result->isError());
    }

    public function test_ai_result_to_pack_returns_error_on_error(): void
    {
        $result = new AiResult(error: 'Failed');
        $pack = $result->toPack();

        $this->assertEquals(['error' => 'Failed'], $pack);
    }

    public function test_ai_result_to_pack_returns_fields(): void
    {
        $result = new AiResult(
            headline: 'Test Headline',
            brief: 'Test brief',
            body: '<p>Body</p>',
            categoryName: 'Business',
            tags: ['economy', 'test'],
        );

        $pack = $result->toPack();

        $this->assertEquals('Test Headline', $pack['headline']);
        $this->assertEquals('Test brief', $pack['brief']);
        $this->assertEquals('<p>Body</p>', $pack['body']);
        $this->assertEquals(['name' => 'Business'], $pack['category']);
        $this->assertEquals(['economy', 'test'], $pack['tags']);
    }

    public function test_ai_result_to_pack_omits_null_fields(): void
    {
        $result = new AiResult(headline: 'Only headline');

        $pack = $result->toPack();

        $this->assertArrayHasKey('headline', $pack);
        $this->assertArrayNotHasKey('brief', $pack);
        $this->assertArrayNotHasKey('body', $pack);
        $this->assertArrayNotHasKey('category', $pack);
        $this->assertArrayNotHasKey('tags', $pack);
    }

    public function test_stub_provider_returns_preedit_result(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('preedit', ['text' => 'Test raw text']);

        $this->assertFalse($result->isError());
        $this->assertNotNull($result->headline);
        $this->assertNotNull($result->body);
        $this->assertEquals('Business', $result->categoryName);
    }

    public function test_stub_provider_returns_tags_result(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('tags', ['text' => 'Cricket match']);

        $this->assertFalse($result->isError());
        $this->assertEquals('Sports', $result->categoryName);
        $this->assertContains('cricket', $result->tags);
    }

    public function test_stub_provider_returns_translate_result(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('translate', ['text' => 'Hello', 'headline' => 'Hi']);

        $this->assertFalse($result->isError());
        $this->assertNotNull($result->headline);
        $this->assertNotNull($result->body);
    }

    public function test_stub_provider_returns_generate_result(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('generate', ['text' => 'Raw input']);

        $this->assertFalse($result->isError());
        $this->assertNotNull($result->headline);
        $this->assertNotNull($result->body);
        $this->assertEquals('Bangladesh', $result->categoryName);
    }

    public function test_stub_provider_returns_unknown_kind(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('unknown', ['text' => 'Test']);

        $this->assertFalse($result->isError());
    }

    public function test_stub_provider_returns_token_counts(): void
    {
        $provider = new StubAiProvider;
        $result = $provider->call('preedit', ['text' => 'Test']);

        $this->assertEquals(100, $result->tokensIn);
        $this->assertEquals(200, $result->tokensOut);
        $this->assertEquals(1000, $result->costMicros);
        $this->assertEquals('stub', $result->model);
    }
}
