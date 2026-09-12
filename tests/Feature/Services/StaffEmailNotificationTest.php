<?php

namespace Tests\Feature\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\StoryNotification;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_review_requested_includes_mail_channel(): void
    {
        $n = new StoryNotification('review_requested', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $channels = $n->via($user);
        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_changes_requested_includes_mail_channel(): void
    {
        $n = new StoryNotification('changes_requested', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertContains('mail', $n->via($user));
    }

    public function test_killed_includes_mail_channel(): void
    {
        $n = new StoryNotification('killed', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertContains('mail', $n->via($user));
    }

    public function test_handover_includes_mail_channel(): void
    {
        $n = new StoryNotification('handover', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertContains('mail', $n->via($user));
    }

    public function test_approved_does_not_include_mail_channel(): void
    {
        $n = new StoryNotification('approved', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertNotContains('mail', $n->via($user));
    }

    public function test_published_does_not_include_mail_channel(): void
    {
        $n = new StoryNotification('published', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertNotContains('mail', $n->via($user));
    }

    public function test_note_added_does_not_include_mail_channel(): void
    {
        $n = new StoryNotification('note_added', ['headline' => 'Test', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->assertNotContains('mail', $n->via($user));
    }

    public function test_mail_message_has_correct_subject(): void
    {
        $n = new StoryNotification('review_requested', ['headline' => 'PM visits flood areas', 'story_id' => 1, 'actor_id' => 1]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $mail = $n->toMail($user);
        $this->assertEquals('[UNB Wire] Story awaiting review: PM visits flood areas', $mail->subject);
    }
}
