<?php

namespace Tests\Feature;

use App\Jobs\SendStoryEmail;
use App\Mail\StoryAlert;
use App\Models\Client;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendStoryEmailTest extends TestCase
{
    use RefreshDatabase;

    private function createClientWithEmailPrefs(array $emailPrefs): Client
    {
        return Client::factory()->create([
            'notes' => json_encode([
                'channels' => [
                    'email' => $emailPrefs,
                ],
            ]),
        ]);
    }

    public function test_emails_sent_to_all_recipients_when_alerts_match()
    {
        Mail::fake();

        $client = $this->createClientWithEmailPrefs([
            'on' => true,
            'list' => ['test1@example.com', 'test2@example.com'],
            'alerts' => ['breaking' => true],
        ]);

        $story = Story::factory()->create(['is_breaking' => true]);

        (new SendStoryEmail($story->id, $client->id))->handle();

        Mail::assertQueued(StoryAlert::class, 2);
        Mail::assertQueued(StoryAlert::class, function ($mail) {
            return $mail->hasTo('test1@example.com') || $mail->hasTo('test2@example.com');
        });
    }

    public function test_no_email_when_email_on_is_false()
    {
        Mail::fake();

        $client = $this->createClientWithEmailPrefs([
            'on' => false,
            'list' => ['test@example.com'],
            'alerts' => ['breaking' => true],
        ]);

        $story = Story::factory()->create(['is_breaking' => true]);

        (new SendStoryEmail($story->id, $client->id))->handle();

        Mail::assertNothingQueued();
    }

    public function test_breaking_alert_pref_only_sends_for_breaking_stories()
    {
        Mail::fake();

        $client = $this->createClientWithEmailPrefs([
            'on' => true,
            'list' => ['test@example.com'],
            'alerts' => ['breaking' => true, 'exclusive' => false],
        ]);

        $storyBreaking = Story::factory()->create(['is_breaking' => true]);
        $storyRegular = Story::factory()->create(['is_breaking' => false]);

        (new SendStoryEmail($storyBreaking->id, $client->id))->handle();
        Mail::assertQueued(StoryAlert::class, 1);

        Mail::fake(); // reset
        (new SendStoryEmail($storyRegular->id, $client->id))->handle();
        Mail::assertNothingQueued();
    }

    public function test_no_alert_prefs_sends_for_all_stories()
    {
        Mail::fake();

        $client = $this->createClientWithEmailPrefs([
            'on' => true,
            'list' => ['test@example.com'],
            'alerts' => [], // empty alerts
        ]);

        $story = Story::factory()->create(['is_breaking' => false]);

        (new SendStoryEmail($story->id, $client->id))->handle();

        Mail::assertQueued(StoryAlert::class, 1);
    }

    public function test_empty_recipient_list_no_emails_sent()
    {
        Mail::fake();

        $client = $this->createClientWithEmailPrefs([
            'on' => true,
            'list' => [], // no recipients
            'alerts' => ['breaking' => true],
        ]);

        $story = Story::factory()->create(['is_breaking' => true]);

        (new SendStoryEmail($story->id, $client->id))->handle();

        Mail::assertNothingQueued();
    }
}
