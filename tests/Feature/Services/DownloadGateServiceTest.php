<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\Download\DownloadGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadGateServiceTest extends TestCase
{
    use RefreshDatabase;

    private DownloadGateService $svc;

    private Client $client;

    private Story $story;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(DownloadGateService::class);
        $this->client = Client::factory()->create();
        $pkg = Package::create([
            'code' => 'PKG-'.Str::random(6),
            'name' => 'open',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        ClientPackage::create([
            'client_id' => $this->client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $this->story = Story::factory()->create([
            'language' => 'en',
            'status' => 'published',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_download_story_succeeds_with_valid_entitlement(): void
    {
        $result = $this->svc->downloadStory($this->story, $this->client);

        $this->assertNotEmpty($result->content);
    }

    public function test_download_story_returns_wire_output(): void
    {
        $result = $this->svc->downloadStory($this->story, $this->client, null, 'json-unb-v1');

        $this->assertNotNull($result->content);
        $this->assertNotNull($result->filename);
    }
}
