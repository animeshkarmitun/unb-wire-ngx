<?php

namespace Tests\Feature;

use App\Mail\StoryAlert;
use App\Models\Story;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoryAlertMailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailable_renders_without_errors()
    {
        $story = Story::factory()->create([
            'headline' => 'Test Headline',
            'brief' => 'Test summary',
        ]);
        
        $mailable = new StoryAlert($story, 'Acme Corp', false);
        $html = $mailable->render();
        
        $this->assertStringContainsString('Test Headline', $html);
        $this->assertStringContainsString('Test summary', $html);
        $this->assertStringContainsString('Acme Corp', $html);
    }

    public function test_breaking_story_has_breaking_in_subject_and_body()
    {
        $story = Story::factory()->create([
            'headline' => 'Big Earthquake',
        ]);
        
        $mailable = new StoryAlert($story, 'Acme Corp', true);
        
        $this->assertEquals('[UNB Wire] Breaking: Big Earthquake', $mailable->envelope()->subject);
        
        $html = $mailable->render();
        $this->assertStringContainsString('BREAKING', $html);
    }

    public function test_normal_story_does_not_have_breaking_in_subject()
    {
        $story = Story::factory()->create([
            'headline' => 'Normal News',
        ]);
        
        $mailable = new StoryAlert($story, 'Acme Corp', false);
        
        $this->assertEquals('[UNB Wire] Normal News', $mailable->envelope()->subject);
        
        $html = $mailable->render();
        $this->assertStringNotContainsString('BREAKING', $html);
    }

    public function test_content_includes_author_and_category()
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $category = Category::factory()->create(['name_en' => 'Politics']);
        
        $story = Story::factory()->create([
            'headline' => 'Policy Change',
            'brief' => 'A new policy was announced.',
            'owner_id' => $user->id,
            'category_id' => $category->id,
        ]);
        
        $mailable = new StoryAlert($story, 'Acme Corp', false);
        $html = $mailable->render();
        
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Politics', $html);
    }

    public function test_mail_fake_and_assert_sent_works()
    {
        Mail::fake();
        
        $story = Story::factory()->create();
        
        Mail::to('client@example.com')->send(new StoryAlert($story, 'Acme Corp'));
        
        Mail::assertSent(StoryAlert::class, function ($mail) use ($story) {
            return $mail->story->id === $story->id &&
                   $mail->clientName === 'Acme Corp';
        });
    }
}
