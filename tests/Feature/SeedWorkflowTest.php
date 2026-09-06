<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\RbacService;
use App\Services\Search\EntitlementResolver;
use App\Services\Search\TenantTokenIssuer;
use App\Services\StoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        $r = Role::where('name', $role)->firstOrFail();

        return User::factory()->create(['role_id' => $r->id]);
    }

    public function test_sub_editor_to_editor_publish_workflow_with_seed_roles(): void
    {
        $cat = Category::factory()->create(['slug' => 'bangladesh', 'name_en' => 'Bangladesh']);
        $uploader = $this->user('Uploader-English');
        $editor = $this->user('Editor');
        $rbac = app(RbacService::class);
        $svc = app(StoryService::class);

        $this->assertTrue($rbac->can($uploader, 'stories', 'create'));
        $this->assertFalse($rbac->can($uploader, 'stories', 'publish'));
        $this->assertTrue($rbac->can($editor, 'stories', 'publish'));

        $story = $svc->createDraft([
            'language' => 'en', 'headline' => 'Seed workflow headline', 'brief' => 'Brief for seed',
            'body_html' => '<p>Body from sub-editor desk</p>', 'category_id' => $cat->id,
        ], $uploader);
        $this->assertEquals('draft', $story->status);
        $this->assertDatabaseHas('story_versions', ['story_id' => $story->id, 'version' => 1]);

        $story = $svc->transition($story, 'in_review', $uploader);
        $this->assertEquals('in_review', $story->status);

        try {
            $svc->transition($story, 'published', $uploader);
            $this->fail('uploader should not publish directly');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Invalid transition', $e->getMessage());
        }

        $story = $svc->transition($story, 'approved', $editor);
        $this->assertEquals('approved', $story->status);
        $story = $svc->transition($story, 'published', $editor);
        $this->assertEquals('published', $story->status);
        $this->assertNotNull($story->published_at);
        $this->assertDatabaseHas('index_outbox', ['document_id' => $story->public_id, 'op' => 'upsert']);
        $this->assertDatabaseHas('story_events', ['story_id' => $story->id, 'to_status' => 'published']);
    }

    public function test_office_to_client_dashboard_entitlement_and_feed(): void
    {
        $cat = Category::factory()->create();
        $officeAdmin = $this->user('Admin');
        $biz = $this->user('Business Team');

        $client = Client::factory()->create(['name' => 'Dhaka Tribune', 'code' => 'DT-001']);
        $pkgEn = Package::factory()->create([
            'code' => 'PKG-EN', 'name' => 'English Wire',
            'entitlement_filter' => ['languages' => ['en'], 'category_ids' => null],
            'price_monthly' => 1500,
        ]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkgEn->id, 'status' => 'active', 'starts_at' => now()]);
        DB::table('client_channels')->insert(['client_id' => $client->id, 'type' => 'webhook', 'config' => json_encode(['url' => 'https://example.test/hook']), 'status' => 'active', 'failure_count' => 0]);
        [$key, $raw] = app(ApiKeyService::class)->issue($client, 'portal', ['feed:read', 'media:download']);
        $this->assertDatabaseHas('client_api_keys', ['client_id' => $client->id]);

        $story = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Client visible story', 'brief' => 'Brief',
            'body_html' => '<p>English wire content</p>', 'category_id' => $cat->id,
        ], $officeAdmin);
        $svc = app(StoryService::class);
        $story = $svc->transition($story, 'in_review', $officeAdmin);
        $story = $svc->transition($story, 'approved', $officeAdmin);
        $story = $svc->transition($story, 'published', $officeAdmin);

        (new FanoutStory($story->id))->handle();
        $this->assertDatabaseHas('deliveries', ['client_id' => $client->id, 'deliverable_id' => $story->id]);

        $tokenData = app(TenantTokenIssuer::class)->issueFor($client);
        $this->assertStringContainsString('en', $tokenData['filter']);
        $this->assertEquals('language IN [en]', $tokenData['filter']);

        $resp = $this->getJson('/api/v1/portal/feed');
        $resp->assertOk();
        $found = collect($resp->json('data'))->firstWhere('public_id', $story->public_id);
        $this->assertNotNull($found, 'published story appears in portal feed');

        $resp2 = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$raw]);
        $resp2->assertOk();
        $this->assertNotEmpty($resp2->json('data'));
    }

    public function test_bn_story_not_visible_to_en_only_client(): void
    {
        $cat = Category::factory()->create();
        $admin = $this->user('Admin');
        $client = Client::factory()->create();
        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['en']]]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()]);
        DB::table('client_channels')->insert(['client_id' => $client->id, 'type' => 'webhook', 'config' => json_encode(['url' => 'https://example.test/hook']), 'status' => 'active', 'failure_count' => 0]);

        $bn = app(StoryService::class)->createDraft([
            'language' => 'bn', 'headline' => 'বাংলা খবর', 'brief' => 'ব্রিফ', 'body_html' => '<p>বাংলা কন্টেন্ট</p>', 'category_id' => $cat->id,
        ], $admin);
        $svc = app(StoryService::class);
        $bn = $svc->transition($bn, 'in_review', $admin);
        $bn = $svc->transition($bn, 'approved', $admin);
        $bn = $svc->transition($bn, 'published', $admin);

        (new FanoutStory($bn->id))->handle();
        $this->assertDatabaseMissing('deliveries', ['client_id' => $client->id, 'deliverable_id' => $bn->id]);

        $filter = app(EntitlementResolver::class)->compileMeiliFilter($client);
        $this->assertEquals('language IN [en]', $filter);
        $this->assertStringNotContainsString('bn', $filter);
    }
}
