<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Download;
use App\Services\Billing\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $svc;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(QuotaService::class);
        $this->client = Client::factory()->create();
    }

    public function test_get_usage_returns_null_quotas_when_no_tier(): void
    {
        $usage = $this->svc->getUsage($this->client);

        $this->assertNull($usage['stories_quota']);
        $this->assertNull($usage['media_quota']);
        $this->assertEquals(0, $usage['stories_used']);
        $this->assertEquals(0, $usage['media_used']);
    }

    public function test_get_usage_counts_downloads(): void
    {
        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'story',
            'created_at' => now(),
        ]);

        $usage = $this->svc->getUsage($this->client);

        $this->assertEquals(1, $usage['stories_used']);
    }

    public function test_get_usage_counts_media_downloads(): void
    {
        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'media',
            'created_at' => now(),
        ]);

        $usage = $this->svc->getUsage($this->client);

        $this->assertEquals(1, $usage['media_used']);
    }

    public function test_get_usage_reads_tier_quotas_from_notes(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['stories_quota' => 100, 'media_quota' => 50]])]);

        $usage = $this->svc->getUsage($this->client);

        $this->assertEquals(100, $usage['stories_quota']);
        $this->assertEquals(50, $usage['media_quota']);
    }

    public function test_can_download_story_true_when_no_quota(): void
    {
        $this->assertTrue($this->svc->canDownloadStory($this->client));
    }

    public function test_can_download_story_false_when_exceeded(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['stories_quota' => 1]])]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'story',
            'created_at' => now(),
        ]);

        $this->assertFalse($this->svc->canDownloadStory($this->client));
    }

    public function test_can_download_story_true_when_under_quota(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['stories_quota' => 10]])]);

        $this->assertTrue($this->svc->canDownloadStory($this->client));
    }

    public function test_can_download_media_true_when_no_quota(): void
    {
        $this->assertTrue($this->svc->canDownloadMedia($this->client));
    }

    public function test_can_download_media_false_when_exceeded(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['media_quota' => 1]])]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'media',
            'created_at' => now(),
        ]);

        $this->assertFalse($this->svc->canDownloadMedia($this->client));
    }

    public function test_assert_can_download_story_throws_when_exceeded(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['stories_quota' => 0]])]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'story',
            'created_at' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->svc->assertCanDownloadStory($this->client);
    }

    public function test_assert_can_download_media_throws_when_exceeded(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['media_quota' => 0]])]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'media',
            'created_at' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->svc->assertCanDownloadMedia($this->client);
    }
}
