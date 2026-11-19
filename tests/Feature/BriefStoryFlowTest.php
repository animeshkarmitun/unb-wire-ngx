<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BriefStoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doc_import_generates_sentence_bounded_brief(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);
        $editor = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $this->actingAs($editor);

        $body = str_repeat("Para one has a full sentence. It ends here.\r\n", 8).'and cut mid word trailing';
        Livewire::test(AddNews::class)
            ->set('docImport', [
                'blocks' => [['kind' => 'paragraph', 'text' => $body]],
                'assignment' => ['headline'],
                'author' => null,
                'dateline_city' => null,
                'dateline_date' => null,
                'heading_found' => false,
                'subhead_found' => false,
                'byline_found' => false,
                'words' => 10,
                'file_name' => 'probe.docx',
            ])
            ->set('docAssignment', ['headline'])
            ->call('applyDocImport');

        $brief = Livewire::test(AddNews::class)->instance()->brief;
        $this->assertStringNotContainsString("\r", $brief);
        $this->assertFalse(str_ends_with($brief, 'here. It'), 'no mid-sentence fixed-length cut');
    }

    public function test_repair_command_fixes_truncated_briefs(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $cat = Category::first() ?? Category::factory()->create();

        // 264 chars + \r\n artefact (stories.brief is varchar(280) — CI runs pgsql).
        $bad = str_repeat('Story body sentence. ', 12)."\r\nPartial trailing wor";
        $story = Story::factory()->create([
            'status' => 'published',
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'brief' => $bad,
        ]);

        $this->artisan('stories:repair-briefs')->assertSuccessful();
        $story->refresh();

        $this->assertStringNotContainsString("\r", $story->brief);
        $this->assertStringNotContainsString("\n", $story->brief);
        $this->assertNotSame($bad, $story->brief);
        // Either a complete sentence inside the budget, or a word-boundary cut with ellipsis.
        $this->assertTrue(
            str_ends_with($story->brief, '.') || str_ends_with($story->brief, '…'),
            'brief ends on a sentence or word boundary'
        );
    }
}
