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
        ];
    }

    public function test_superadmin_can_access_all_routes(): void
    {
        $user = $this->superadmin();
        $this->actingAs($user);

        $routes = [
            '/admin/news/en',
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
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_dashboard_accessible_to_all_authenticated(): void
    {
        $user = $this->user('Business Team');
        $this->actingAs($user);

        $this->get('/admin')->assertOk();
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
