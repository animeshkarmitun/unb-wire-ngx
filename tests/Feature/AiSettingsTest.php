<?php

namespace Tests\Feature;

use App\Livewire\Admin\AiSettings;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name' => 'Admin',
            'type' => 'system',
            'description' => 'Full control',
            'is_locked' => true,
        ]);

        $adminRole->permissions()->create([
            'module' => 'settings',
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
        ]);

        $adminRole->permissions()->create([
            'module' => 'ai',
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@unb.com.bd',
        ]);

        $uploaderRole = Role::create([
            'name' => 'Uploader',
            'type' => 'custom',
            'description' => 'Drafts only',
            'is_locked' => false,
        ]);

        $uploaderRole->permissions()->create([
            'module' => 'settings',
            'can_view' => false,
            'can_create' => false,
            'can_edit' => false,
            'can_publish' => false,
            'can_delete' => false,
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'role_id' => $uploaderRole->id,
            'email' => 'uploader@unb.com.bd',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.ai-settings'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_ai_settings(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.ai-settings'));
        $response->assertForbidden();
    }

    public function test_admin_can_access_ai_settings_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.ai-settings'));
        $response->assertOk();
        $response->assertSee('AI settings');
        $response->assertSee('AI pre-edit is active');
    }

    public function test_component_mounts_with_canonical_defaults_when_empty(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->assertSet('preeditEn', true)
            ->assertSet('preeditBn', true)
            ->assertSet('preeditPhotos', false)
            ->assertSet('autoPublish', false)
            ->assertSet('killed', false)
            ->assertSet('monthlyCap', 500000)
            ->assertViewHas('totalTokens', 312400)
            ->assertViewHas('costEst', 4186);
    }

    public function test_component_loads_existing_settings_from_database(): void
    {
        Setting::create([
            'key' => 'ai.desk',
            'value' => [
                'preeditEn' => false,
                'preeditBn' => true,
                'preeditPhotos' => true,
                'autoPublish' => true,
                'autoCats' => ['Weather', 'Bangladesh'],
                'monthlyCap' => 800000,
                'stylePrompt' => 'Custom style prompt',
                'killed' => false,
                'model' => 'openai:gpt-4o',
            ],
            'updated_by' => $this->admin->id,
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->assertSet('preeditEn', false)
            ->assertSet('preeditBn', true)
            ->assertSet('preeditPhotos', true)
            ->assertSet('autoPublish', true)
            ->assertSet('autoCats', ['Weather', 'Bangladesh'])
            ->assertSet('monthlyCap', 800000)
            ->assertSet('stylePrompt', 'Custom style prompt');
    }

    public function test_auto_publish_confirmation_modal_flow(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->assertSet('autoPublish', false)
            ->assertSet('showAutoModal', false);

        // Clicking to enable opens confirmation modal
        $comp->call('toggleAutoPublish')
            ->assertSet('showAutoModal', true)
            ->assertSet('autoPublish', false);

        // Cancelling keeps it false
        $comp->call('cancelAutoPublish')
            ->assertSet('showAutoModal', false)
            ->assertSet('autoPublish', false);

        // Opening again and confirming enables auto-publish
        $comp->call('toggleAutoPublish')
            ->call('confirmAutoPublish')
            ->assertSet('showAutoModal', false)
            ->assertSet('autoPublish', true)
            ->assertDispatched('toast');

        // Toggling when true turns it off immediately without modal
        $comp->call('toggleAutoPublish')
            ->assertSet('showAutoModal', false)
            ->assertSet('autoPublish', false);
    }

    public function test_category_chip_toggling(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(AiSettings::class);

        // Initially 'Bangladesh' is not in default autoCats
        $this->assertNotContains('Bangladesh', $comp->get('autoCats'));

        // Toggle on
        $comp->call('toggleCategory', 'Bangladesh');
        $this->assertContains('Bangladesh', $comp->get('autoCats'));

        // Toggle off
        $comp->call('toggleCategory', 'Bangladesh');
        $this->assertNotContains('Bangladesh', $comp->get('autoCats'));
    }

    public function test_monthly_cap_validation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->set('monthlyCap', 1000)
            ->call('save')
            ->assertHasErrors(['monthlyCap' => 'min']);
    }

    public function test_emergency_kill_switch_instantly_persists_and_logs_audit(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->assertSet('killed', false);

        // Trip kill switch
        $comp->call('toggleKill')
            ->assertSet('killed', true)
            ->assertSet('autoPublish', false)
            ->assertDispatched('toast');

        // Verify DB was immediately updated
        $setting = Setting::where('key', 'ai.desk')->first();
        $this->assertNotNull($setting);
        $val = $setting->value;
        $this->assertTrue($val['killed']);
        $this->assertFalse($val['autoPublish']);

        // Verify AuditLog was recorded
        $audit = AuditLog::where('action', 'ai.kill_switch.on')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->admin->id, $audit->actor_id);
        $this->assertTrue($audit->diff['killed']);

        // Toggle kill switch back off
        $comp->call('toggleKill')
            ->assertSet('killed', false);

        $auditOff = AuditLog::where('action', 'ai.kill_switch.off')->first();
        $this->assertNotNull($auditOff);
    }

    public function test_reset_defaults_restores_canonical_values(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->set('preeditEn', false)
            ->set('monthlyCap', 999000)
            ->set('autoPublish', true)
            ->call('resetDefaults')
            ->assertSet('preeditEn', true)
            ->assertSet('monthlyCap', 500000)
            ->assertSet('autoPublish', false)
            ->assertDispatched('toast');
    }

    public function test_save_settings_persists_and_audits(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->set('preeditEn', true)
            ->set('preeditBn', false)
            ->set('preeditPhotos', true)
            ->set('monthlyCap', 600000)
            ->set('autoCats', ['Weather', 'World'])
            ->set('stylePrompt', 'UNB Style Custom Rules')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $setting = Setting::where('key', 'ai.desk')->first();
        $this->assertNotNull($setting);
        $this->assertFalse($setting->value['preeditBn']);
        $this->assertTrue($setting->value['preeditPhotos']);
        $this->assertEquals(600000, $setting->value['monthlyCap']);
        $this->assertEquals(['Weather', 'World'], $setting->value['autoCats']);
        $this->assertEquals('UNB Style Custom Rules', $setting->value['stylePrompt']);

        $audit = AuditLog::where('action', 'ai.settings.updated')->first();
        $this->assertNotNull($audit);
        $this->assertEquals(600000, $audit->diff['new']['monthlyCap']);
    }

    public function test_ai_service_respects_settings_persisted_by_component(): void
    {
        $aiService = app(AiService::class);

        // Default state: AI works
        $res = $aiService->call('preedit', ['text' => 'sample news', 'language' => 'en'], $this->admin->id);
        $this->assertArrayNotHasKey('error', $res);

        // Disable English desk
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->set('preeditEn', false)
            ->call('save');

        $resDisabled = $aiService->call('preedit', ['text' => 'sample news', 'language' => 'en'], $this->admin->id);
        $this->assertEquals('AI disabled for this desk', $resDisabled['error']);

        // Trip kill switch
        Livewire::actingAs($this->admin)
            ->test(AiSettings::class)
            ->call('toggleKill');

        $resKilled = $aiService->call('preedit', ['text' => 'sample news', 'language' => 'en'], $this->admin->id);
        $this->assertEquals('AI kill switch is ON', $resKilled['error']);
    }
}
