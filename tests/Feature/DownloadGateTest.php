<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\Download\DownloadGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class DownloadGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_mismatched_language_is_denied_and_no_ledger_row(): void
    {
        $cat = Category::create(['slug' => 'cat-'.Str::random(4), 'name_en' => 'Cat', 'name_bn' => 'Cat', 'is_active' => true]);
        $pkg = Package::create([
            'code' => 'PKG-'.Str::random(6),
            'name' => 'pkg',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        $client = Client::create(['code' => 'CL-'.Str::random(4), 'name' => 'CL', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        ClientPackage::create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'bn', // not entitled
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'published',
            'version' => 1,
        ]);

        $before = DB::table('downloads')->count();

        try {
            app(DownloadGateService::class)->downloadStory($story, $client);
            $this->fail('expected AccessDeniedHttpException');
        } catch (AccessDeniedHttpException $e) {
            $this->assertStringContainsString('not entitled', $e->getMessage());
        }

        $this->assertSame($before, DB::table('downloads')->count(), 'denied download must not ledger');
    }

    public function test_allowed_download_ledgers_and_returns_wire_output(): void
    {
        $cat = Category::create(['slug' => 'cat-'.Str::random(4), 'name_en' => 'Cat', 'name_bn' => 'Cat', 'is_active' => true]);
        $pkg = Package::create([
            'code' => 'PKG-'.Str::random(6),
            'name' => 'pkg',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        $client = Client::create(['code' => 'CL-'.Str::random(4), 'name' => 'CL', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        ClientPackage::create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'published',
            'version' => 1,
        ]);

        $before = DB::table('downloads')->where('client_id', $client->id)->count();

        $output = app(DownloadGateService::class)->downloadStory($story, $client);

        $this->assertNotEmpty($output->content);
        $this->assertSame($before + 1, DB::table('downloads')->where('client_id', $client->id)->count());
    }

    public function test_no_active_packages_denies_download(): void
    {
        $cat = Category::create(['slug' => 'cat-'.Str::random(4), 'name_en' => 'Cat', 'name_bn' => 'Cat', 'is_active' => true]);
        $client = Client::create(['code' => 'CL-'.Str::random(4), 'name' => 'CL', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'published',
            'version' => 1,
        ]);

        $before = DB::table('downloads')->count();

        try {
            app(DownloadGateService::class)->downloadStory($story, $client);
            $this->fail('expected AccessDeniedHttpException');
        } catch (AccessDeniedHttpException) {
            // ok
        }

        $this->assertSame($before, DB::table('downloads')->count());
    }
}
