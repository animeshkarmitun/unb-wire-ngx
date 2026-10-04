<?php

namespace Tests\Feature\Repositories;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SettingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(SettingRepository::class);
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $result = $this->repo->get('nonexistent');

        $this->assertNull($result);
    }

    public function test_get_returns_value_for_existing_key(): void
    {
        Setting::create(['key' => 'test.key', 'value' => ['foo' => 'bar']]);

        $result = $this->repo->get('test.key');

        $this->assertEquals(['foo' => 'bar'], $result);
    }

    public function test_set_creates_new_setting(): void
    {
        $result = $this->repo->set('new.key', ['value' => 1]);

        $this->assertInstanceOf(Setting::class, $result);
        $this->assertEquals('new.key', $result->key);
        $this->assertDatabaseHas('settings', ['key' => 'new.key']);
    }

    public function test_set_updates_existing_setting(): void
    {
        Setting::create(['key' => 'existing', 'value' => 'old']);

        $this->repo->set('existing', 'new');

        $this->assertDatabaseHas('settings', ['key' => 'existing']);
        $this->assertEquals('new', $this->repo->get('existing'));
    }

    public function test_set_records_updated_by(): void
    {
        $user = User::factory()->create();
        $this->repo->set('key', 'value', $user->id);

        $setting = Setting::where('key', 'key')->first();
        $this->assertEquals($user->id, $setting->updated_by);
    }

    public function test_get_ai_desk_config_returns_array(): void
    {
        Setting::create(['key' => 'ai.desk', 'value' => ['enabled' => true]]);

        $result = $this->repo->getAiDeskConfig();

        $this->assertIsArray($result);
        $this->assertTrue($result['enabled']);
    }

    public function test_get_ai_desk_config_returns_empty_when_missing(): void
    {
        $result = $this->repo->getAiDeskConfig();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_delivery_config_returns_array(): void
    {
        Setting::create(['key' => 'delivery', 'value' => ['engine' => 'auto']]);

        $result = $this->repo->getDeliveryConfig();

        $this->assertIsArray($result);
        $this->assertEquals('auto', $result['engine']);
    }

    public function test_get_delivery_config_returns_empty_when_missing(): void
    {
        $result = $this->repo->getDeliveryConfig();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
