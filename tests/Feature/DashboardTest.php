<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Download;
use App\Models\Package;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(PackageSeeder::class);

        $adminRole = Role::where('name', 'Admin')->first();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    public function test_dashboard_renders_for_staff(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Stories published today');
        $response->assertSee('Active clients');
        $response->assertSee('Distribution success rate');
        $response->assertSee('Exclusive content sent');
        $response->assertSee('Recent stories');
        $response->assertSee('Top clients today');
        $response->assertSee('Download'); // FAB
        $response->assertSee(route('admin.news', 'en')); // View all
        $response->assertSee(route('admin.clients')); // All clients
    }

    public function test_dashboard_service_computes_kpis_and_deltas(): void
    {
        $today = Carbon::today('Asia/Dhaka');
        $yesterday = Carbon::yesterday('Asia/Dhaka');
        $cat = Category::first();

        // 2 published today, 1 published yesterday
        Story::factory()->create([
            'category_id' => $cat->id,
            'status' => 'published',
            'published_at' => $today->copy()->addHours(2),
            'headline' => 'Story Today 1',
        ]);
        Story::factory()->create([
            'category_id' => $cat->id,
            'status' => 'published',
            'published_at' => $today->copy()->addHours(3),
            'headline' => 'Story Today 2',
            'is_breaking' => true,
        ]);
        Story::factory()->create([
            'category_id' => $cat->id,
            'status' => 'published',
            'published_at' => $yesterday->copy()->addHours(2),
            'headline' => 'Story Yesterday 1',
        ]);

        // 2 active clients, 1 created today
        $client1 = Client::factory()->create(['status' => 'active', 'created_at' => $today]);
        $client2 = Client::factory()->create(['status' => 'active', 'created_at' => Carbon::now('Asia/Dhaka')->subDays(20)]);

        // Record a download
        Download::create([
            'client_id' => $client1->id,
            'item_type' => 'story',
            'item_id' => 1,
            'created_at' => $today->copy()->addHour(),
        ]);

        $service = app(DashboardService::class);
        $data = $service->getData();

        $this->assertEquals(2, $data['publishedToday']);
        $this->assertEquals('+1 from yesterday', $data['deltaPublishedText']);
        $this->assertEquals('up', $data['deltaPublishedDirection']);

        $this->assertEquals(2, $data['activeClients']);
        $this->assertStringContainsString('this week', $data['deltaClientsText']);

        $this->assertEquals(1, $data['exclusiveToday']);
        $this->assertEquals('+1 from yesterday', $data['deltaExclusiveText']);

        $this->assertNotEmpty($data['recentStories']);
        $this->assertNotEmpty($data['topClients']);
        $this->assertEquals($client1->name, $data['topClients'][0]['name']);
        $this->assertEquals(1, $data['topClients'][0]['downloads']);
    }

    public function test_dashboard_eager_loading_no_n_plus_one(): void
    {
        Model::preventLazyLoading(true);

        $cat = Category::first();
        for ($i = 0; $i < 5; $i++) {
            Story::factory()->create([
                'category_id' => $cat->id,
                'status' => 'published',
                'published_at' => Carbon::today('Asia/Dhaka'),
            ]);
        }

        $package = Package::first();
        for ($i = 0; $i < 4; $i++) {
            $c = Client::factory()->create(['status' => 'active']);
            ClientPackage::create([
                'client_id' => $c->id,
                'package_id' => $package->id,
                'starts_at' => now(),
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);

        Model::preventLazyLoading(false);
    }

    public function test_dashboard_service_hides_client_business_data_without_clients_view(): void
    {
        // COS-80: uploaders have no clients:view — the service must not compute
        // or return client business data for them (shared cache split prevents
        // a cached business blob leaking through).
        $uploaderRole = Role::where('name', 'Uploader-English')->first();
        $uploader = User::factory()->create(['role_id' => $uploaderRole->id]);
        Client::factory()->create(['status' => 'active']);
        $cat = Category::first();
        Story::factory()->create([
            'category_id' => $cat->id,
            'status' => 'published',
            'published_at' => now(),
            'owner_id' => $uploader->id,
            'created_by' => $uploader->id,
        ]);

        $data = app(DashboardService::class)->getData($uploader);

        $this->assertArrayNotHasKey('activeClients', $data);
        $this->assertArrayNotHasKey('successRate', $data);
        $this->assertSame([], $data['topClients']);
        $this->assertNotEmpty($data['recentStories']); // newsroom data stays
    }

    public function test_dashboard_service_returns_business_data_for_clients_view(): void
    {
        $client = Client::factory()->create(['status' => 'active']);

        $data = app(DashboardService::class)->getData($this->admin);

        $this->assertEquals(1, $data['activeClients']);
        $this->assertNotEmpty($data['topClients']);
        $this->assertEquals($client->name, $data['topClients'][0]['name']);
    }
}
