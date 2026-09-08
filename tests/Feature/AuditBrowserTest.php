<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLogBrowser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
