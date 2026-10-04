<?php

namespace Tests\Feature;

use App\Livewire\Admin\ServiceConfig;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->role->permissions()->updateOrCreate(
            ['module' => 'settings'],
            ['can_view' => true, 'can_edit' => true]
        );
    }

    public function test_mount_loads_existing_config(): void
    {
        Setting::create(['key' => 'service.en', 'value' => ['wireName' => 'UNB Wire', 'description' => 'Test desc', 'enabled' => true]]);

        $this->actingAs($this->admin);
        Livewire::test(ServiceConfig::class, ['service' => 'en'])
            ->assertSet('wireName', 'UNB Wire')
            ->assertSet('description', 'Test desc')
            ->assertSet('enabled', true);
    }

    public function test_mount_handles_missing_config(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ServiceConfig::class, ['service' => 'bn'])
            ->assertSet('wireName', '')
            ->assertSet('description', '')
            ->assertSet('enabled', true);
    }

    public function test_save_persists_config(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ServiceConfig::class, ['service' => 'en'])
            ->set('wireName', 'Updated Wire')
            ->set('description', 'Updated desc')
            ->set('enabled', false)
            ->call('save');

        $this->assertDatabaseHas('settings', ['key' => 'service.en']);
        $setting = Setting::where('key', 'service.en')->first();
        $this->assertEquals('Updated Wire', $setting->value['wireName']);
        $this->assertEquals('Updated desc', $setting->value['description']);
        $this->assertFalse($setting->value['enabled']);
    }

    public function test_save_updates_existing_config(): void
    {
        Setting::create(['key' => 'service.en', 'value' => ['wireName' => 'Old', 'description' => 'Old desc', 'enabled' => true]]);

        $this->actingAs($this->admin);
        Livewire::test(ServiceConfig::class, ['service' => 'en'])
            ->set('wireName', 'New Wire')
            ->call('save');

        $setting = Setting::where('key', 'service.en')->first();
        $this->assertEquals('New Wire', $setting->value['wireName']);
    }

    public function test_service_switch(): void
    {
        Setting::create(['key' => 'service.bn', 'value' => ['wireName' => 'UNB Bangla', 'description' => 'Bangla service', 'enabled' => true]]);

        $this->actingAs($this->admin);
        Livewire::test(ServiceConfig::class, ['service' => 'bn'])
            ->assertSet('wireName', 'UNB Bangla')
            ->assertSet('service', 'bn');
    }
}
