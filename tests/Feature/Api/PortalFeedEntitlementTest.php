<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalFeedEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $catBusiness;

    private Category $catEntertainment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->catBusiness = Category::factory()->create(['name_en' => 'Business', 'slug' => 'business']);
        $this->catEntertainment = Category::factory()->create(['name_en' => 'Entertainment', 'slug' => 'entertainment']);
    }

    private function createPortalUserWithPackage(array $filter): array
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

        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $token = $user->createToken('portal')->plainTextToken;

        return [$client, $user, $token];
    }

    public function test_portal_user_with_english_only_package_only_sees_english_in_feed(): void
    {
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catBusiness->id,
            'headline' => 'English Business Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);
        Story::factory()->published()->create([
            'language' => 'bn',
            'category_id' => $this->catBusiness->id,
            'headline' => 'Bangla Business Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $user, $token] = $this->createPortalUserWithPackage([
            'languages' => ['en'],
            'category_ids' => null,
        ]);

        $resp = $this->getJson('/api/v1/portal/feed', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('English Business Story', $resp->json('data.0.headline'));
    }

    public function test_portal_user_with_category_restriction_only_sees_matching_category(): void
    {
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catBusiness->id,
            'headline' => 'Business Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catEntertainment->id,
            'headline' => 'Entertainment Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $user, $token] = $this->createPortalUserWithPackage([
            'languages' => ['en'],
            'category_ids' => [$this->catEntertainment->id],
        ]);

        $resp = $this->getJson('/api/v1/portal/feed', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('Entertainment Story', $resp->json('data.0.headline'));
    }

    public function test_portal_user_cannot_view_unentitled_story(): void
    {
        $bnStory = Story::factory()->published()->create([
            'language' => 'bn',
            'category_id' => $this->catBusiness->id,
            'headline' => 'Bangla Exclusive',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $user, $token] = $this->createPortalUserWithPackage([
            'languages' => ['en'],
            'category_ids' => null,
        ]);

        // Trying to view a Bangla story with English-only package -> 404
        $resp = $this->getJson("/api/v1/portal/story/{$bnStory->public_id}", [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertNotFound();
    }

    public function test_portal_user_can_view_entitled_story(): void
    {
        $enStory = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catBusiness->id,
            'headline' => 'English Accessible Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $user, $token] = $this->createPortalUserWithPackage([
            'languages' => ['en'],
            'category_ids' => null,
        ]);

        $resp = $this->getJson("/api/v1/portal/story/{$enStory->public_id}", [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals('English Accessible Story', $resp->json('data.headline'));
    }
}
