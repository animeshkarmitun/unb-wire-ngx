<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName): User
    {
        $this->seed(RoleSeeder::class);
        $role = Role::where('name', $roleName)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function superadmin(): User
    {
        $this->seed(RoleSeeder::class);
        $role = Role::where('name', 'Admin')->firstOrFail();

        return User::factory()->superadmin()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    /**
     * @dataProvider adminRouteProvider
     */
    public function test_route_returns_403_for_wrong_role(string $route, string $requiredModule, string $forbiddenRole): void
    {
        $user = $this->user($forbiddenRole);
        $this->actingAs($user);

        $response = $this->get($route);
        $response->assertStatus(403);
    }

    public static function adminRouteProvider(): array
    {
        return [
            'news (Business Team)' => ['/admin/news/en', 'stories', 'Business Team'],
            'photos (Business Team)' => ['/admin/photos', 'media', 'Business Team'],
            'clients (Uploader-English)' => ['/admin/clients', 'clients', 'Uploader-English'],
            'packages (Uploader-English)' => ['/admin/packages', 'packages', 'Uploader-English'],
            'roles (Uploader-English)' => ['/admin/roles', 'settings', 'Uploader-English'],
            'ai-settings (Uploader-English)' => ['/admin/ai-settings', 'settings', 'Uploader-English'],
            'distribution (Uploader-English)' => ['/admin/distribution', 'distribution', 'Uploader-English'],
            'delivery-settings (Uploader-English)' => ['/admin/delivery-settings', 'distribution', 'Uploader-English'],
            'audit (Editor)' => ['/admin/audit', 'audit', 'Editor'],
            'add-news (Business Team)' => ['/admin/add-news', 'stories', 'Business Team'],
            'ap-photos (Business Team)' => ['/admin/ap-photos', 'media', 'Business Team'],
            'service (Business Team)' => ['/admin/service/en', 'stories', 'Business Team'],
            'bangla news (Uploader-English)' => ['/admin/news/bn', 'stories_bn', 'Uploader-English'],
            'bangla service (Uploader-English)' => ['/admin/service/bn', 'stories_bn', 'Uploader-English'],
        ];
    }

    public function test_superadmin_can_access_all_routes(): void
    {
        $user = $this->superadmin();
        $this->actingAs($user);

        $routes = [
            '/admin/news/en',
            '/admin/news/bn',
            '/admin/photos',
            '/admin/clients',
            '/admin/packages',
            '/admin/roles',
            '/admin/ai-settings',
            '/admin/distribution',
            '/admin/delivery-settings',
            '/admin/audit',
            '/admin/add-news',
            '/admin/ap-photos',
            '/admin/service/en',
            '/admin/service/bn',
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_dashboard_accessible_to_all_authenticated(): void
    {
        // The dashboard is every staff member's landing page (200 for all), but
        // client business data must only render for roles with clients:view (COS-80).
        $user = $this->user('Business Team');
        $this->actingAs($user);

        $resp = $this->get('/admin')->assertOk();
        $resp->assertSee('Active clients');
        $resp->assertSee('Top clients today');
    }

    public function test_dashboard_hides_client_business_data_from_uploaders(): void
    {
        $uploader = $this->user('Uploader-English');
        $this->actingAs($uploader);

        $resp = $this->get('/admin')->assertOk();

        $resp->assertSee('Stories published today'); // newsroom data stays
        $resp->assertSee('Exclusive content sent');
        $resp->assertDontSee('Active clients');
        $resp->assertDontSee('Distribution success rate');
        $resp->assertDontSee('Top clients today');
        $resp->assertDontSee('Export report');
        // Note: the sidebar's raw Clients link is tracked separately as COS-65.
    }

    // ─── COS-63: language-aware news access ─────────────────────────

    public function test_bangla_uploader_can_open_bangla_news(): void
    {
        $user = $this->user('Uploader-Bangla');
        $this->actingAs($user);

        $this->get('/admin/news/bn')->assertOk();
        $this->get('/admin/news/en')->assertStatus(403);
    }

    public function test_bangla_uploader_cannot_open_english_news(): void
    {
        $user = $this->user('Uploader-Bangla');
        $this->actingAs($user);

        $this->get('/admin/news/en')->assertStatus(403);
    }

    public function test_bangla_uploader_can_open_bangla_service(): void
    {
        $user = $this->user('Uploader-Bangla');
        $this->actingAs($user);

        $this->get('/admin/service/bn')->assertOk();
        $this->get('/admin/service/en')->assertStatus(403);
    }

    public function test_sidebar_shows_only_the_uploader_language(): void
    {
        $user = $this->user('Uploader-Bangla');
        $this->actingAs($user);

        $resp = $this->get('/admin')->assertOk();
        $resp->assertSee('Bangla News');
        $resp->assertDontSee('English News');
    }

    public function test_preferences_accessible_to_all_authenticated(): void
    {
        $user = $this->user('Business Team');
        $this->actingAs($user);

        $this->get('/admin/preferences')->assertOk();
    }

    public function test_notifications_accessible_to_all_authenticated(): void
    {
        $user = $this->user('Business Team');
        $this->actingAs($user);

        $this->get('/admin/notifications')->assertOk();
    }
}
