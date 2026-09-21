<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_request_accepts_valid_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->getJson('/api/v1/portal/feed?limit=10')
            ->assertOk();
    }

    public function test_feed_request_accepts_since(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->getJson('/api/v1/portal/feed?since=2026-01-01')
            ->assertOk();
    }

    public function test_profile_update_request_validates_empty_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->patchJson('/profile', ['name' => ''])
            ->assertStatus(422);
    }
}
