<?php

namespace Tests\Unit;

use App\Models\Story as StoryEloquent;
use App\Services\Ai\FactGuard;
use App\Services\Delivery\TriggerMatcher;
use App\Services\Delivery\WebhookSigner;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PureRuleTest extends TestCase
{
    public function test_webhook_signer_produces_correct_hmac(): void
    {
        $body = '{"hello":"world"}';
        $secret = 'topsecret';
        $headers = (new WebhookSigner)->headers($body, $secret, 'story.published');
        $this->assertArrayHasKey('X-UNB-Signature', $headers);
        $this->assertArrayHasKey('X-UNB-Event', $headers);
        $this->assertSame('story.published', $headers['X-UNB-Event']);
        $this->assertStringStartsWith('sha256=', $headers['X-UNB-Signature']);
        $this->assertMatchesRegularExpression('/^sha256=[a-f0-9]{64}$/', $headers['X-UNB-Signature']);
    }

    public function test_trigger_matcher_language_miss(): void
    {
        $m = new TriggerMatcher;
        $story = new StoryEloquent(['language' => 'bn']);
        $story->setRelation('tags', new Collection([]));
        $this->assertFalse($m->shouldFire($story, ['languages' => ['en']]));
    }

    public function test_trigger_matcher_category_miss(): void
    {
        $m = new TriggerMatcher;
        $story = new StoryEloquent(['language' => 'en', 'category_id' => 99]);
        $story->setRelation('tags', new Collection([]));
        $this->assertFalse($m->shouldFire($story, ['category_ids' => [1, 2]]));
    }

    public function test_trigger_matcher_empty_filter_matches(): void
    {
        $m = new TriggerMatcher;
        $story = new StoryEloquent(['language' => 'en', 'category_id' => 1]);
        $story->setRelation('tags', new Collection([]));
        $this->assertTrue($m->shouldFire($story, []));
    }

    public function test_fact_guard_extracts_numbers_missing_from_source(): void
    {
        $g = new FactGuard;
        $r = $g->extract('a peaceful protest', 'Police said 5,000 marched on Friday.');
        $this->assertIsArray($r);
        $this->assertContains('5,000', $r);
    }

    public function test_fact_guard_extracts_quoted_span_missing_from_source(): void
    {
        $g = new FactGuard;
        $r = $g->extract('a speech today', 'The minister said "we will rebuild everything" by 2026.');
        $this->assertIsArray($r);
        $this->assertContains('"we will rebuild everything"', $r);
    }
}
