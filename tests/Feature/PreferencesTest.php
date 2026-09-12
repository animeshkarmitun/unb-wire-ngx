<?php

namespace Tests\Feature;

use App\Livewire\Admin\Preferences;
use App\Models\User;
use App\Support\DisplayPrefs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_date_format_persists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('dateFormat', 'iso')
            ->call('save');

        $this->assertSame('iso', $user->fresh()->date_format);
    }

    public function test_valid_density_persists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('density', 'compact')
            ->call('save');

        $this->assertSame('compact', $user->fresh()->density);
    }

    public function test_invalid_date_format_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('dateFormat', 'invalid')
            ->call('save')
            ->assertHasErrors(['dateFormat']);
    }

    public function test_invalid_density_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('density', 'invalid')
            ->call('save')
            ->assertHasErrors(['density']);
    }

    public function test_helper_renders_dmy_for_dhaka(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Dhaka', 'date_format' => 'dmy']);
        $dt = Carbon::parse('2026-09-13 10:00:00', 'UTC');

        $result = DisplayPrefs::format($dt, 'Asia/Dhaka', 'dmy');

        $this->assertSame('13 Sep, 04:00 PM', $result);
    }

    public function test_helper_renders_iso_for_utc(): void
    {
        $dt = Carbon::parse('2026-09-13 14:30:00', 'UTC');

        $result = DisplayPrefs::format($dt, 'UTC', 'iso');

        $this->assertSame('2026-09-13 14:30', $result);
    }

    public function test_helper_renders_mdy(): void
    {
        $dt = Carbon::parse('2026-09-13 10:00:00', 'UTC');

        $result = DisplayPrefs::format($dt, 'UTC', 'mdy');

        $this->assertSame('Sep 13, 10:00 AM', $result);
    }

    public function test_helper_returns_empty_for_null(): void
    {
        $this->assertSame('', DisplayPrefs::format(null));
    }

    public function test_helper_defaults_to_dhaka_dmy_for_guest(): void
    {
        $dt = Carbon::parse('2026-09-13 10:00:00', 'UTC');

        $result = DisplayPrefs::format($dt);

        $this->assertSame('13 Sep, 04:00 PM', $result);
    }
}
