<?php

namespace Tests\Unit;

use App\Models\StoryVersion;
use App\Services\RevisionService;
use Tests\TestCase;

class RevisionServiceDiffTest extends TestCase
{
    private RevisionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(RevisionService::class);
    }

    private function version(array $snap): StoryVersion
    {
        return StoryVersion::make(['snapshot' => $snap]);
    }

    public function test_identical_snapshots_empty_diff(): void
    {
        $snap = ['headline' => 'Same', 'body_html' => '<p>Hello world</p>', 'tags' => ['a']];
        $a = $this->version($snap);
        $b = $this->version($snap);
        $result = $this->svc->diff($a, $b);
        $this->assertEmpty($result['fields']);
        $this->assertEmpty($result['body']);
    }

    public function test_field_diff_detected(): void
    {
        $a = $this->version(['headline' => 'Old', 'priority' => 'low', 'body_html' => '', 'tags' => []]);
        $b = $this->version(['headline' => 'New', 'priority' => 'high', 'body_html' => '', 'tags' => []]);
        $result = $this->svc->diff($a, $b);
        $this->assertCount(2, $result['fields']);
        $this->assertEquals('headline', $result['fields'][0]['field']);
        $this->assertEquals('Old', $result['fields'][0]['from']);
        $this->assertEquals('New', $result['fields'][0]['to']);
        $this->assertEquals('priority', $result['fields'][1]['field']);
    }

    public function test_tags_diff_detected(): void
    {
        $a = $this->version(['headline' => '', 'body_html' => '', 'tags' => ['politics']]);
        $b = $this->version(['headline' => '', 'body_html' => '', 'tags' => ['politics', 'economy']]);
        $result = $this->svc->diff($a, $b);
        $tagField = collect($result['fields'])->firstWhere('field', 'tags');
        $this->assertNotNull($tagField);
        $this->assertEquals(['politics'], $tagField['from']);
        $this->assertEquals(['economy', 'politics'], $tagField['to']);
    }

    public function test_body_diff_marks_changes(): void
    {
        $a = $this->version(['headline' => '', 'body_html' => '<p>Hello world</p>', 'tags' => []]);
        $b = $this->version(['headline' => '', 'body_html' => '<p>Hello brave new world</p>', 'tags' => []]);
        $result = $this->svc->diff($a, $b);
        $this->assertNotEmpty($result['body']);
        $added = collect($result['body'])->where('type', 'added')->pluck('token')->all();
        $this->assertContains('brave', $added);
        $this->assertContains('new', $added);
    }

    public function test_body_diff_no_change_empty(): void
    {
        $a = $this->version(['headline' => '', 'body_html' => '<p>Same body</p>', 'tags' => []]);
        $b = $this->version(['headline' => '', 'body_html' => '<p>Same body</p>', 'tags' => []]);
        $result = $this->svc->diff($a, $b);
        $this->assertEmpty($result['body']);
    }
}
