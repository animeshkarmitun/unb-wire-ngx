<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLogBrowser;
use App\Livewire\Admin\RolesManager;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * COS-68/67/70/69/71: role & user audit entries carry ip/correlation/entity,
 * actors are named, diffs are readable plain text, ordering is deterministic.
 */
class AuditRoleUserActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->superadmin()->create([
            'role_id' => Role::where('name', 'Admin')->first()->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->admin);
    }

    public function test_user_role_change_logs_ip_and_correlation(): void
    {
        $target = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $newRole = Role::where('name', 'Uploader-English')->first();

        Livewire::test(RolesManager::class)
            ->call('updateUserRole', $target->id, $newRole->id);

        $row = DB::table('audit_logs')->where('action', 'user.role_changed')->first();
        $this->assertNotNull($row, 'audit row written');
        $this->assertNotNull($row->ip);
        $this->assertNotNull($row->correlation_id);
        $this->assertEquals($this->admin->id, $row->actor_id);
        $this->assertEquals('user', $row->entity_type);
        $this->assertEquals($target->id, $row->entity_id);

        $diff = json_decode($row->diff, true);
        $this->assertStringContainsString($target->name, $diff['message']);
        $this->assertStringNotContainsString('<b>', $diff['message']);
    }

    public function test_user_invited_logs_entity_and_plain_diff(): void
    {
        $role = Role::where('name', 'Editor')->first();

        Livewire::test(RolesManager::class)
            ->set('invName', 'New Reporter')
            ->set('invEmail', 'newreporter@unbnews.org')
            ->set('invDesk', 'English')
            ->set('invRole', (string) $role->id)
            ->call('inviteMember');

        $row = DB::table('audit_logs')->where('action', 'user.invited')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->ip);
        $this->assertNotNull($row->correlation_id);
        $this->assertEquals('user', $row->entity_type);
        $this->assertNotNull($row->entity_id, 'COS-70: invited user entity id recorded');
        $this->assertStringContainsString('New Reporter', json_decode($row->diff, true)['message']);
    }

    public function test_role_save_logs_ip_and_correlation(): void
    {
        $role = Role::where('name', 'Uploader-English')->first();

        Livewire::test(RolesManager::class)
            ->call('openDrawer', $role->id)
            ->call('saveRole');

        $row = DB::table('audit_logs')->where('action', 'role.updated')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->ip);
        $this->assertNotNull($row->correlation_id);
        $this->assertEquals('role', $row->entity_type);
        $this->assertEquals($role->id, $row->entity_id);
    }

    public function test_same_created_at_rows_order_deterministically(): void
    {
        // COS-71: identical timestamps must have a stable secondary sort key.
        $ts = now();
        DB::table('audit_logs')->insert([
            'actor_type' => 'user', 'actor_id' => $this->admin->id, 'action' => 'role.created',
            'created_at' => $ts,
        ]);
        DB::table('audit_logs')->insert([
            'actor_type' => 'user', 'actor_id' => $this->admin->id, 'action' => 'role.updated',
            'created_at' => $ts,
        ]);

        $order = AuditLog::orderByDesc('created_at')->orderByDesc('id')->pluck('action')->all();
        $this->assertSame(['role.updated', 'role.created'], $order);
    }

    public function test_browser_renders_actor_names_and_readable_diff(): void
    {
        $target = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        Livewire::test(RolesManager::class)
            ->call('updateUserRole', $target->id, Role::where('name', 'Uploader-English')->first()->id);

        $resp = Livewire::test(AuditLogBrowser::class);

        $resp->assertSee($this->admin->name); // COS-67: real actor name
        $resp->assertDontSee('User #'.$this->admin->id);
        $html = $resp->html();
        $this->assertStringNotContainsString('&lt;b&gt;', $html); // COS-69: no HTML leakage
        $this->assertStringContainsString('moved to role', $html);
    }
}
