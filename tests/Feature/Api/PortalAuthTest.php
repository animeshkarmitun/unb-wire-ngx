<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\ClientUser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $client = Client::factory()->create(['name' => 'The Daily Star']);
        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $resp = $this->postJson('/api/v1/portal/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'token',
            'client_user' => ['id', 'name', 'email', 'client_role_id', 'client' => ['name', 'initials']],
        ]);
        $this->assertEquals('user@example.com', $resp->json('client_user.email'));
        $this->assertEquals('The Daily Star', $resp->json('client_user.client.name'));
        $this->assertEquals('TD', $resp->json('client_user.client.initials'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        ClientUser::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correctpassword'),
            'status' => 'active',
        ]);

        $resp = $this->postJson('/api/v1/portal/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_login_with_deactivated_user_returns_403(): void
    {
        ClientUser::factory()->create([
            'email' => 'deactivated@example.com',
            'password' => Hash::make('password123'),
            'status' => 'deactivated',
        ]);

        $resp = $this->postJson('/api/v1/portal/login', [
            'email' => 'deactivated@example.com',
            'password' => 'password123',
        ]);

        $resp->assertStatus(403);
        $resp->assertJson(['message' => 'Account is not active']);
    }

    public function test_login_with_nonexistent_email_returns_422(): void
    {
        $resp = $this->postJson('/api/v1/portal/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_logout_revokes_token(): void
    {
        $client = Client::factory()->create();
        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $loginResp = $this->postJson('/api/v1/portal/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResp->json('token');

        $this->postJson('/api/v1/portal/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forgot_password_returns_200_for_existing_email(): void
    {
        ClientUser::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $resp = $this->postJson('/api/v1/portal/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $resp->assertOk();
        $resp->assertJson(['message' => 'If the email exists, a reset link has been sent.']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'user@example.com']);
    }

    public function test_forgot_password_returns_200_for_nonexistent_email(): void
    {
        $resp = $this->postJson('/api/v1/portal/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $resp->assertOk();
        $resp->assertJson(['message' => 'If the email exists, a reset link has been sent.']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nonexistent@example.com']);
    }

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $user = ClientUser::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $token = 'reset-token-123';
        \DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $resp = $this->postJson('/api/v1/portal/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resp->assertOk();
        $resp->assertJson(['message' => 'Password reset successful']);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'user@example.com']);
    }

    public function test_reset_password_with_expired_token_returns_422(): void
    {
        $user = ClientUser::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $token = 'expired-token-123';
        \DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2),
        ]);

        $resp = $this->postJson('/api/v1/portal/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['message' => 'Reset token has expired']);
    }

    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        ClientUser::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        \DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $resp = $this->postJson('/api/v1/portal/reset-password', [
            'token' => 'wrong-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['message' => 'Invalid or expired reset token']);
    }

    public function test_existing_portal_feed_still_works_with_api_key(): void
    {
        $resp = $this->getJson('/api/v1/portal/feed?language=en');

        $resp->assertOk();
    }

    public function test_existing_portal_context_still_works_without_auth(): void
    {
        $resp = $this->getJson('/api/v1/portal/context');

        $resp->assertOk();
        $this->assertNull($resp->json('client'));
    }

    public function test_login_validates_required_fields(): void
    {
        $resp = $this->postJson('/api/v1/portal/login', []);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validates_password_min_length(): void
    {
        $resp = $this->postJson('/api/v1/portal/login', [
            'email' => 'user@example.com',
            'password' => 'short',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $resp = $this->postJson('/api/v1/portal/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['password']);
    }
}
