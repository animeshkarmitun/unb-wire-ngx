<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FR-AI-005 — New-facts warning.
 *
 * Covers the Livewire + Blade paths that FactGuardTest does not exercise:
 * the AI drawer renders the flagged count and the publish gate modal
 * includes the verifier copy with the live count.
 */
class AddNewsFactGuardUiTest extends TestCase
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

        $this->cat = Category::where('name_en', 'Bangladesh')->first() ?? Category::first() ?? Category::factory()->create();
    }

    private function attachFakeNewFacts(int $count): void
    {
        $facts = [];
        for ($i = 0; $i < $count; $i++) {
            $facts[] = "Test fact $i that does not appear in source";
        }
        $existing = DB::table('settings')->where('key', 'ai.desk')->value('value');
        $cfg = is_string($existing) ? json_decode($existing, true) : [];
        DB::table('settings')->where('key', 'ai.desk')->update([
            'value' => json_encode(array_merge($cfg ?? [], [
                'new_facts' => $facts,
                'preeditEn' => true,
                'autoPublish' => false,
            ])),
        ]);
    }

    public function test_ai_drawer_renders_fact_guard_count_when_pack_has_new_facts(): void
    {
        $this->attachFakeNewFacts(3);
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('aiPack', [
                'headline' => 'AI headline',
                'brief' => 'AI brief',
                'body' => '<p>x</p>',
                'category' => ['name' => 'Bangladesh'],
                'tags' => [],
                'style_lint' => [],
                'new_facts' => ['abc-fact-1', 'abc-fact-2', 'abc-fact-3'],
            ]);

        $cmp->assertSee('3 new facts — verify before use');
        $cmp->assertSee('abc-fact-1');
        $cmp->assertSee('abc-fact-2');
        $cmp->assertSee('abc-fact-3');
    }

    public function test_ai_drawer_omits_new_facts_panel_when_empty(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('aiPack', [
                'headline' => 'AI headline',
                'brief' => 'AI brief',
                'body' => '<p>x</p>',
                'category' => ['name' => 'Bangladesh'],
                'tags' => [],
                'style_lint' => [],
                'new_facts' => [],
            ]);

        $cmp->assertDontSee('new facts — verify before use');
    }

    public function test_publish_gate_modal_renders_fact_count_via_livewire_render(): void
    {
        $this->attachFakeNewFacts(7);
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('aiPack', [
                'headline' => 'AI headline',
                'brief' => 'AI brief',
                'body' => '<p>x</p>',
                'category' => ['name' => 'Bangladesh'],
                'tags' => [],
                'style_lint' => [],
                'new_facts' => array_fill(0, 7, 'gate-fact'),
            ]);

        $cmp->assertSee('7 facts flagged');
        $cmp->assertSee('AI Human Review Checklist');
    }
}
