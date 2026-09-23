<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateNewsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);

        $editorRole = Role::where('name', 'Editor')->first();
        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
        $this->cat = Category::where('name_en', 'Bangladesh')->first() ?? Category::first();
    }

    private function makePublished(string $headline, string $body, string $lang = 'en'): Story
    {
        return Story::factory()->create([
            'status' => 'published',
            'language' => $lang,
            'headline' => $headline,
            'body_text' => $body,
            'body_fingerprint' => DuplicateDetectionService::fingerprint($body),
            'published_at' => now()->subDay(),
            'category_id' => $this->cat->id,
        ]);
    }

    // ─── Service ─────────────────────────────────────────────────

    public function test_exact_normalized_title_match_is_high(): void
    {
        $this->makePublished('Cabinet approves budget!', 'The cabinet approved the annual budget on Tuesday.');
        $svc = app(DuplicateDetectionService::class);

        $matches = $svc->matches('cabinet approves budget', 'Completely different text about cricket scores and player transfers.');

        $this->assertNotEmpty($matches);
        $this->assertSame(DuplicateDetectionService::LEVEL_HIGH, $matches[0]['level']);
        $this->assertSame(1.0, $matches[0]['title_score']);
    }

    public function test_fuzzy_title_match_is_detected(): void
    {
        $this->makePublished('Cabinet approves national budget for 2027', 'Some body text here about fiscal policy and spending.');
        $svc = app(DuplicateDetectionService::class);

        $matches = $svc->matches('Cabinet approves national budget for 2026', 'Totally unrelated sports reporting from the stadium.');

        $this->assertNotEmpty($matches);
        $this->assertSame(DuplicateDetectionService::LEVEL_HIGH, $matches[0]['level']);
    }

    public function test_body_similarity_detected_with_different_title(): void
    {
        $body = 'The finance minister unveiled the annual budget in parliament on Tuesday afternoon outlining new tax measures and infrastructure spending plans for the coming fiscal year across all districts.';
        $this->makePublished('Budget unveiled in parliament', $body);
        $svc = app(DuplicateDetectionService::class);

        $edited = 'The finance minister unveiled the annual budget in parliament on Tuesday afternoon outlining new tax measures and infrastructure spending plans for the coming fiscal year across every district.';
        $matches = $svc->matches('Tax measures announced', $edited);

        $this->assertNotEmpty($matches);
        $this->assertSame(DuplicateDetectionService::LEVEL_HIGH, $matches[0]['level']);
        $this->assertGreaterThan(0.7, $matches[0]['body_score']);
    }

    public function test_unrelated_story_is_not_flagged(): void
    {
        $this->makePublished('Cricket team wins final', 'The national cricket team won the final match by six wickets on Sunday evening at the packed stadium.');
        $svc = app(DuplicateDetectionService::class);

        $matches = $svc->matches('New metro rail line opens', 'Authorities opened a new metro rail line in the capital on Monday to ease commuter traffic congestion significantly.');

        $this->assertSame([], $matches);
    }

    public function test_only_recent_published_stories_considered(): void
    {
        $old = $this->makePublished('Same old headline', 'Identical body text used for both stories in this test case.');
        $old->update(['published_at' => now()->subDays(45)]);
        $draft = Story::factory()->create([
            'status' => 'draft',
            'language' => 'en',
            'headline' => 'Same old headline',
            'body_text' => 'Identical body text used for both stories in this test case.',
            'category_id' => $this->cat->id,
        ]);

        $matches = app(DuplicateDetectionService::class)->matches('Same old headline', 'Identical body text used for both stories in this test case.');

        $this->assertSame([], $matches);
        $this->assertNotNull($draft->id);
    }

    public function test_other_language_candidates_excluded(): void
    {
        $this->makePublished('Breaking festival news', 'The annual book fair opened with great fanfare and thousands of visitors on day one.', 'bn');
        $matches = app(DuplicateDetectionService::class)->matches('Breaking festival news', 'The annual book fair opened with great fanfare and thousands of visitors on day one.', 'en');
        $this->assertSame([], $matches);
    }

    // ─── Fingerprint write path ──────────────────────────────────

    public function test_fingerprint_stored_on_draft_create_and_update(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Fingerprint story')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Original body content for fingerprinting.</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $story = Story::find($cmp->get('storyId'));
        $this->assertSame(DuplicateDetectionService::fingerprint('Original body content for fingerprinting.'), $story->body_fingerprint);

        $cmp->set('bodyHtml', '<p>Completely rewritten body content.</p>')->call('autosave');
        $story->refresh();
        $this->assertSame(DuplicateDetectionService::fingerprint('Completely rewritten body content.'), $story->body_fingerprint);
    }

    // ─── Wizard: warning + block + override ──────────────────────

    public function test_autosave_sets_non_blocking_warning(): void
    {
        $this->actingAs($this->editor);
        $this->makePublished('Cabinet approves budget!', 'The cabinet approved the annual budget on Tuesday morning in parliament.');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'cabinet approves budget')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Totally different sports content about cricket and players.</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $dupes = $cmp->get('dupMatches');
        $this->assertNotEmpty($dupes);
        $this->assertFalse($cmp->get('dupBlocked'));
        $this->assertNotNull($cmp->get('storyId'));
    }

    public function test_publish_blocked_on_high_similarity_without_override(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);
        $this->makePublished('Cabinet approves budget!', 'The cabinet approved the annual budget on Tuesday morning in parliament.');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Cabinet approves budget!')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Totally different sports content about cricket and players.</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $this->assertTrue($cmp->get('dupBlocked'));
        $story = Story::find($cmp->get('storyId'));
        $this->assertNotEquals('published', $story->status);
    }

    public function test_override_with_reason_publishes_and_audits(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);
        $this->makePublished('Cabinet approves budget!', 'The cabinet approved the annual budget on Tuesday morning in parliament.');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Cabinet approves budget!')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Totally different sports content about cricket and players.</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $this->assertTrue($cmp->get('dupBlocked'));

        $cmp->set('dupOverrideReason', 'Follow-up angle on the same announcement — distinct story')
            ->call('publish');

        $story = Story::find($cmp->get('storyId'));
        $this->assertEquals('published', $story->status);
        $this->assertFalse($cmp->get('dupBlocked'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'publish.duplicate_override', 'entity_id' => $story->id]);
    }

    public function test_override_without_reason_stays_blocked(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);
        $this->makePublished('Cabinet approves budget!', 'The cabinet approved the annual budget on Tuesday morning in parliament.');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Cabinet approves budget!')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Totally different sports content about cricket and players.</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->set('dupOverrideReason', 'x')
            ->call('publish');

        $this->assertTrue($cmp->get('dupBlocked'));
        $story = Story::find($cmp->get('storyId'));
        $this->assertNotEquals('published', $story->status);
    }
}
