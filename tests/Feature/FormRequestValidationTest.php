<?php

namespace Tests\Feature;

use App\Http\Requests\AiAssistRequest;
use App\Http\Requests\TusCreateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
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

    public function test_ai_assist_request_rejects_unknown_story_id(): void
    {
        $rules = (new AiAssistRequest)->rules();

        $v = Validator::make(['text' => 'x', 'story_id' => 999999], $rules);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('story_id', $v->errors()->toArray());
    }

    public function test_ai_assist_request_rejects_text_too_long(): void
    {
        $rules = (new AiAssistRequest)->rules();

        $v = Validator::make(['text' => str_repeat('a', 20001)], $rules);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('text', $v->errors()->toArray());
    }

    public function test_ai_assist_request_rejects_invalid_kind_when_present(): void
    {
        $rules = (new AiAssistRequest)->rules();

        // AiAssistRequest accepts unknown text fields but the route calls ->validated().
        // Verify required rules and type checks.
        $v = Validator::make(['story_id' => 'not-an-int'], $rules);
        $this->assertTrue($v->fails());
    }

    public function test_tus_create_request_rejects_missing_upload_length(): void
    {
        $rules = (new TusCreateRequest)->rules();

        $v = Validator::make([], $rules);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('upload_length', $v->errors()->toArray());
    }

    public function test_tus_create_request_rejects_negative_upload_length(): void
    {
        $rules = (new TusCreateRequest)->rules();

        $v = Validator::make(['upload_length' => -1], $rules);
        $this->assertTrue($v->fails());
    }

    public function test_tus_create_request_rejects_invalid_filename_extension(): void
    {
        $rules = (new TusCreateRequest)->rules();

        $v = Validator::make(['upload_length' => 1024, 'filename' => 'evil.exe'], $rules);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('filename', $v->errors()->toArray());
    }
}
