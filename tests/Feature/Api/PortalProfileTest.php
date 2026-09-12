<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\ClientUser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUserAndToken(): array
    {
        $client = Client::factory()->create(['name' => 'The Daily Star', 'status' => 'active']);
        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $loginResp = $this->postJson('/api/v1/portal/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResp->json('token');

        return [$user, $client, $token];
    }

    public function test_get_profile_returns_client_user_data(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->getJson('/api/v1/portal/profile', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'id',
            'name',
            'email',
            'client_role' => ['name'],
            'client' => ['name', 'initials', 'status'],
            'last_login_at',
        ]);
        $this->assertEquals('John Doe', $resp->json('name'));
        $this->assertEquals('john@example.com', $resp->json('email'));
        $this->assertEquals('The Daily Star', $resp->json('client.name'));
        $this->assertEquals('TD', $resp->json('client.initials'));
        $this->assertEquals('active', $resp->json('client.status'));
    }

    public function test_patch_profile_updates_name(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->patchJson('/api/v1/portal/profile', [
            'name' => 'Jane Doe',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals('Jane Doe', $resp->json('name'));
        $this->assertEquals('john@example.com', $resp->json('email'));
        $this->assertDatabaseHas('client_users', [
            'id' => $user->id,
            'name' => 'Jane Doe',
        ]);
    }

    public function test_patch_profile_with_duplicate_email_returns_422(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        ClientUser::factory()->create([
            'client_id' => $client->id,
            'email' => 'taken@example.com',
        ]);

        $resp = $this->patchJson('/api/v1/portal/profile', [
            'email' => 'taken@example.com',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['email']);
    }

    public function test_patch_profile_with_same_email_is_allowed(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->patchJson('/api/v1/portal/profile', [
            'email' => 'john@example.com',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
    }

    public function test_patch_password_with_correct_current_password_updates(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->patchJson('/api/v1/portal/password', [
            'current_password' => 'password123',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $resp->assertJson(['message' => 'Password updated']);
        $this->assertTrue(Hash::check('newpassword456', $user->fresh()->password));
    }

    public function test_patch_password_with_wrong_current_password_returns_422(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->patchJson('/api/v1/portal/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['current_password']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/portal/profile')->assertStatus(401);
        $this->patchJson('/api/v1/portal/profile', ['name' => 'X'])->assertStatus(401);
        $this->patchJson('/api/v1/portal/password', [
            'current_password' => 'x',
            'password' => 'y',
            'password_confirmation' => 'y',
        ])->assertStatus(401);
    }

    public function test_patch_password_requires_confirmation(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $resp = $this->patchJson('/api/v1/portal/password', [
            'current_password' => 'password123',
            'password' => 'newpassword456',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['password']);
    }
}
