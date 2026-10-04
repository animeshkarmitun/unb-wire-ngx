<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLogBrowser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuditBrowserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::create(['name' => 'Admin', 'type' => 'system', 'description' => 'Full', 'is_locked' => true]);
        $adminRole->permissions()->create(['module' => 'audit', 'can_view' => true]);
        $adminRole->permissions()->create(['module' => 'stories', 'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_publish' => true, 'can_delete' => true]);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $editorRole = Role::create(['name' => 'Editor', 'type' => 'custom', 'description' => 'Ed', 'is_locked' => false]);
        $editorRole->permissions()->create(['module' => 'stories', 'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_publish' => true]);
        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
    }

    public function test_admin_can_access_audit_browser(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.audit'));
        $response->assertOk();
        $response->assertSee('Audit Log');
    }

    public function test_editor_denied_audit_browser(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.audit'));
        $response->assertForbidden();
    }

    public function test_audit_browser_renders_filters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AuditLogBrowser::class)
            ->assertSee('All actions')
            ->assertSee('All types');
    }

    public function test_audit_browser_filter_excludes_non_matching_action(): void
    {
        // Seed one published + one handover audit row.
        $actorId = $this->admin->id;
        DB::table('audit_logs')->insert([
            [
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'handover',
                'entity_type' => 'Story',
                'entity_id' => 1,
                'diff' => null,
                'ip' => '127.0.0.1',
                'user_agent' => 'Test',
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ],
            [
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'role.updated',
                'entity_type' => 'Role',
                'entity_id' => 2,
                'diff' => null,
                'ip' => '127.0.0.1',
                'user_agent' => 'Test',
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ],
        ]);

        $component = Livewire::actingAs($this->admin)->test(AuditLogBrowser::class);

        // The component initialises without a filter; both rows are present.
        $this->assertGreaterThanOrEqual(2, $component->results->total());

        $component->set('filterAction', 'handover');
        $this->assertSame(1, $component->results->total());

        $component->set('filterAction', 'role.updated');
        $this->assertSame(1, $component->results->total());

        $component->set('filterAction', 'no_such_action_xyzzy');
        $this->assertSame(0, $component->results->total());
    }
}
