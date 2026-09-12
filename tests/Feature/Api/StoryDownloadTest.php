<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoryDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $catNational;

    private Category $catSports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->catNational = Category::factory()->create(['name_en' => 'National', 'slug' => 'national']);
        $this->catSports = Category::factory()->create(['name_en' => 'Sports', 'slug' => 'sports']);
    }

    private function createClientWithPackage(array $filter): array
    {
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::factory()->create(['status' => 'active', 'entitlement_filter' => $filter]);

        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        [$key, $rawKey] = app(ApiKeyService::class)->issue($client, 'wire-key', ['feed:read']);

        return [$client, $rawKey];
    }

    public function test_story_download_requires_authentication(): void
    {
        $story = Story::factory()->published()->create([
            'category_id' => $this->catNational->id,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        $this->getJson("/api/v1/portal/story/{$story->public_id}/download")->assertStatus(401);
    }

    public function test_entitled_client_can_download_story_as_json(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'Test National Wire Story',
            'brief' => 'Brief summary',
            'body_html' => '<p>Full story body paragraph.</p>',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->get("/api/v1/portal/story/{$story->public_id}/download?format=json", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('attachment', $resp->headers->get('Content-Disposition'));

        // Check JSON content
        $json = json_decode($resp->getContent(), true);
        $this->assertEquals($story->public_id, $json['id'] ?? $json['public_id'] ?? null);

        // Verify download was ledged in downloads table
        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'story',
            'item_id' => $story->id,
            'format' => 'json',
        ]);
    }

    public function test_entitled_client_can_download_story_as_nitf(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'NITF Wire Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->get("/api/v1/portal/story/{$story->public_id}/download?format=nitf", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<nitf', $resp->getContent());

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'story',
            'item_id' => $story->id,
            'format' => 'nitf',
        ]);
    }

    public function test_entitled_client_can_download_story_as_newsml(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'NewsML Wire Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->get("/api/v1/portal/story/{$story->public_id}/download?format=newsml", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<newsItem', $resp->getContent());
    }

    public function test_unentitled_client_receives_403(): void
    {
        $bnStory = Story::factory()->published()->create([
            'language' => 'bn',
            'category_id' => $this->catNational->id,
            'headline' => 'Bangla Headline',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        // Client only has English package
        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson("/api/v1/portal/story/{$bnStory->public_id}/download", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(403);
    }

    public function test_download_returns_404_for_draft_story(): void
    {
        $draft = Story::factory()->create([
            'status' => 'draft',
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson("/api/v1/portal/story/{$draft->public_id}/download", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertNotFound();
    }

    public function test_portal_user_can_download_story_with_client_user_id_ledging(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'Portal User Download Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::factory()->create(['status' => 'active', 'entitlement_filter' => ['languages' => ['en'], 'category_ids' => null]]);
        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $token = $user->createToken('portal')->plainTextToken;

        $resp = $this->get("/api/v1/portal/story/{$story->public_id}/download?format=json", [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'client_user_id' => $user->id,
            'item_type' => 'story',
            'item_id' => $story->id,
        ]);
    }

    public function test_unsupported_format_returns_422(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson("/api/v1/portal/story/{$story->public_id}/download?format=xyz-unsupported", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(422);
    }
}
