<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\ClientEntitlementOverride;
use App\Models\Download;
use App\Models\Package;
use App\Services\Billing\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $svc;

    private Client $client;

    private function setOverride(array $quotas): void
    {
        ClientEntitlementOverride::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'override_stories_quota' => array_key_exists('stories_quota', $quotas),
                'stories_quota' => $quotas['stories_quota'] ?? null,
                'override_media_quota' => array_key_exists('media_quota', $quotas),
                'media_quota' => $quotas['media_quota'] ?? null,
            ]
        );
    }

    private function attachPackage(?int $storiesQuota, ?int $mediaQuota, bool $addon = false): Package
    {
        $package = Package::factory()->create([
            'stories_quota' => $storiesQuota,
            'media_quota' => $mediaQuota,
            'entitlement_filter' => ['languages' => ['en'], 'is_addon' => $addon],
        ]);
        DB::table('client_packages')->insert([
            'client_id' => $this->client->id,
            'package_id' => $package->id,
            'starts_at' => now()->subDay(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $package;
    }

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

    public function test_get_usage_reads_package_quota_defaults(): void
    {
        $this->attachPackage(100, 50);

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
        $this->setOverride(['stories_quota' => 1]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'story',
            'created_at' => now(),
        ]);

        $this->assertFalse($this->svc->canDownloadStory($this->client));
    }

    public function test_can_download_story_true_when_under_quota(): void
    {
        $this->setOverride(['stories_quota' => 10]);

        $this->assertTrue($this->svc->canDownloadStory($this->client));
    }

    public function test_can_download_media_true_when_no_quota(): void
    {
        $this->assertTrue($this->svc->canDownloadMedia($this->client));
    }

    public function test_can_download_media_false_when_exceeded(): void
    {
        $this->setOverride(['media_quota' => 1]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'media',
            'created_at' => now(),
        ]);

        $this->assertFalse($this->svc->canDownloadMedia($this->client));
    }

    public function test_assert_can_download_story_throws_when_exceeded(): void
    {
        $this->setOverride(['stories_quota' => 0]);

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
        $this->setOverride(['media_quota' => 0]);

        Download::factory()->create([
            'client_id' => $this->client->id,
            'item_type' => 'media',
            'created_at' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->svc->assertCanDownloadMedia($this->client);
    }

    public function test_active_base_packages_union_by_max_and_null_means_unlimited(): void
    {
        $this->attachPackage(800, 400);
        $this->attachPackage(2000, 1000);
        $this->attachPackage(null, null, addon: true); // add-ons do not affect base quotas

        $limits = $this->svc->getQuotaLimits($this->client);
        $this->assertSame(2000, $limits['stories_quota']);
        $this->assertSame(1000, $limits['media_quota']);

        $this->attachPackage(null, null); // unlimited base package wins union
        $limits = $this->svc->getQuotaLimits($this->client);
        $this->assertNull($limits['stories_quota']);
        $this->assertNull($limits['media_quota']);
    }

    public function test_explicit_zero_client_override_replaces_package_default(): void
    {
        $this->attachPackage(800, 400);
        $this->setOverride(['media_quota' => 0]);

        $limits = $this->svc->getQuotaLimits($this->client);
        $this->assertSame(800, $limits['stories_quota']);
        $this->assertSame(0, $limits['media_quota']);
    }
}
