<?php

namespace Tests\Feature;

use App\Livewire\Admin\ClientsManager;
use App\Mail\SendPortalInvite;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Role;
use App\Models\User;
use App\Services\PortalAccountService;
use Database\Seeders\ClientSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PortalAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Client $client;

    protected Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(ClientSeeder::class);

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->client = Client::where('name', 'The Daily Star')->firstOrFail();
        $this->clientRole = Role::where('type', 'client')->firstOrFail();

        $this->actingAs($this->admin);
    }

    public function test_invite_creates_client_user_with_invited_status(): void
    {
        Mail::fake();

        $svc = app(PortalAccountService::class);
        $user = $svc->invite($this->client, [
            'name' => 'Test Editor',
            'email' => 'editor@test.com',
            'client_role_id' => $this->clientRole->id,
        ]);

        $this->assertInstanceOf(ClientUser::class, $user);
        $this->assertEquals('invited', $user->status);
        $this->assertEquals('Test Editor', $user->name);
        $this->assertEquals('editor@test.com', $user->email);
        $this->assertEquals($this->client->id, $user->client_id);
        $this->assertEquals($this->clientRole->id, $user->client_role_id);
        $this->assertNotNull($user->password);

        $this->assertDatabaseHas('client_users', [
            'email' => 'editor@test.com',
            'status' => 'invited',
        ]);

        Mail::assertQueued(SendPortalInvite::class, function ($mail) {
            return $mail->hasTo('editor@test.com');
        });
    }

    public function test_invite_with_duplicate_email_returns_validation_error(): void
    {
        ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'email' => 'existing@test.com',
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('openPortalInviteModal')
            ->set('portalInviteName', 'Duplicate User')
            ->set('portalInviteEmail', 'existing@test.com')
            ->call('invitePortalUser')
            ->assertHasErrors(['portalInviteEmail']);
    }

    public function test_deactivate_sets_status_and_revokes_tokens(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'active',
        ]);

        $user->createToken('test-token');

        $this->assertNotNull($user->fresh()->tokens);
        $this->assertCount(1, $user->fresh()->tokens);

        $svc = app(PortalAccountService::class);
        $result = $svc->deactivate($user);

        $this->assertEquals('deactivated', $result->fresh()->status);
        $this->assertCount(0, $result->fresh()->tokens);
    }

    public function test_reactivate_sets_status_to_active(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'deactivated',
        ]);

        $svc = app(PortalAccountService::class);
        $result = $svc->reactivate($user);

        $this->assertEquals('active', $result->fresh()->status);
    }

    public function test_resend_invite_sends_new_email(): void
    {
        Mail::fake();

        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'invited',
        ]);

        $oldPasswordHash = $user->password;

        $svc = app(PortalAccountService::class);
        $svc->resendInvite($user);

        Mail::assertQueued(SendPortalInvite::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertNotEquals($oldPasswordHash, $user->fresh()->password);
    }

    public function test_update_role_changes_client_role_id(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'client_role_id' => $this->clientRole->id,
        ]);

        $newRole = Role::where('type', 'client')->firstOrFail();

        $svc = app(PortalAccountService::class);
        $result = $svc->updateRole($user, $newRole->id);

        $this->assertEquals($newRole->id, $result->fresh()->client_role_id);
    }

    public function test_rbac_non_clients_edit_user_gets_403(): void
    {
        $uploaderRole = Role::where('name', 'Uploader-Bangla')->firstOrFail();
        $uploader = User::factory()->create(['role_id' => $uploaderRole->id]);

        Livewire::actingAs($uploader)
            ->test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('openPortalInviteModal')
            ->assertStatus(403);
    }

    public function test_audit_log_entries_written_for_invite(): void
    {
        Mail::fake();

        $svc = app(PortalAccountService::class);
        $user = $svc->invite($this->client, [
            'name' => 'Audit Test User',
            'email' => 'audit@test.com',
            'client_role_id' => $this->clientRole->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_user.invited',
            'entity_type' => 'client_user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_audit_log_entries_written_for_deactivate(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'active',
        ]);

        $svc = app(PortalAccountService::class);
        $svc->deactivate($user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_user.deactivated',
            'entity_type' => 'client_user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_audit_log_entries_written_for_reactivate(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'deactivated',
        ]);

        $svc = app(PortalAccountService::class);
        $svc->reactivate($user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_user.activated',
            'entity_type' => 'client_user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_audit_log_entries_written_for_resend_invite(): void
    {
        Mail::fake();

        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'invited',
        ]);

        $svc = app(PortalAccountService::class);
        $svc->resendInvite($user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_user.invite_resent',
            'entity_type' => 'client_user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_audit_log_entries_written_for_update_role(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'client_role_id' => $this->clientRole->id,
        ]);

        $svc = app(PortalAccountService::class);
        $svc->updateRole($user, $this->clientRole->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_user.role_changed',
            'entity_type' => 'client_user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_livewire_deactivate_portal_user_action(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'active',
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('deactivatePortalUser', $user->id)
            ->assertDispatched('toast');

        $this->assertEquals('deactivated', $user->fresh()->status);
    }

    public function test_livewire_reactivate_portal_user_action(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'deactivated',
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('reactivatePortalUser', $user->id)
            ->assertDispatched('toast');

        $this->assertEquals('active', $user->fresh()->status);
    }

    public function test_livewire_resend_portal_invite_action(): void
    {
        Mail::fake();

        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'invited',
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('resendPortalInvite', $user->id)
            ->assertDispatched('toast');

        Mail::assertQueued(SendPortalInvite::class);
    }

    public function test_livewire_update_portal_user_role_action(): void
    {
        $user = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'client_role_id' => $this->clientRole->id,
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('updatePortalUserRole', $user->id, (string) $this->clientRole->id)
            ->assertDispatched('toast');

        $this->assertEquals($this->clientRole->id, $user->fresh()->client_role_id);
    }

    public function test_portal_users_tab_visible_in_drawer(): void
    {
        ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Drawer Test User',
            'email' => 'drawer@test.com',
            'status' => 'active',
        ]);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $this->client->id)
            ->call('setDrawerTab', 'portal-users')
            ->assertSee('Portal Users')
            ->assertSee('Drawer Test User')
            ->assertSee('drawer@test.com')
            ->assertSee('Invite portal user');
    }
}
