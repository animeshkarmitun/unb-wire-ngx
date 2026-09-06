<?php

namespace Tests\Feature;

use App\Livewire\Admin\WireServiceView;
use App\Models\Category;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WireServiceViewTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $categoryNational;

    private Category $categoryWorld;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Editor',
            'type' => 'system',
            'description' => 'Editor with full story permissions',
            'is_locked' => false,
        ]);

        $role->permissions()->create([
            'module' => 'stories',
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
        ]);

        $role->permissions()->create([
            'module' => 'settings',
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
        ]);

        $this->editor = User::factory()->create(['role_id' => $role->id]);

        $this->categoryNational = Category::factory()->create([
            'slug' => 'national',
            'name_en' => 'National',
            'name_bn' => 'জাতীয়',
            'sort_order' => 1,
        ]);

        $this->categoryWorld = Category::factory()->create([
            'slug' => 'world',
            'name_en' => 'World',
            'name_bn' => 'আন্তর্জাতিক',
            'sort_order' => 2,
        ]);

        // Seed published English stories
        Story::create([
            'language' => 'en',
            'headline' => 'National Parliamentary Committee Meeting Begins',
            'brief' => 'First meeting begins at Sangsad Bhaban in Dhaka.',
            'body_html' => '<p>Parliamentary committee discusses diplomatic foreign priorities.</p>',
            'body_text' => 'Parliamentary committee discusses diplomatic foreign priorities.',
            'category_id' => $this->categoryNational->id,
            'dateline_city' => 'Dhaka',
            'status' => 'published',
            'is_breaking' => true,
            'published_at' => now()->subHours(1),
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
            'word_count' => 420,
        ]);

        Story::create([
            'language' => 'en',
            'headline' => 'Thousands Without Power After Strong Winds Hit Poland',
            'brief' => 'Emergency crews dispatched across central Europe.',
            'body_html' => '<p>Storm damage caused heavy outages.</p>',
            'body_text' => 'Storm damage caused heavy outages.',
            'category_id' => $this->categoryWorld->id,
            'dateline_city' => 'Warsaw',
            'status' => 'published',
            'is_breaking' => false,
            'published_at' => now()->subHours(2),
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
            'word_count' => 280,
        ]);

        // Seed published Bangla story
        Story::create([
            'language' => 'bn',
            'headline' => 'সাভারে জাতীয় স্মৃতিসৌধে রাষ্ট্রপতির শ্রদ্ধা নিবেদন',
            'brief' => 'বীর শহীদদের প্রতি বিনম্র শ্রদ্ধা জ্ঞাপন করেছেন মহামান্য রাষ্ট্রপতি।',
            'body_html' => '<p>স্মৃতিসৌধ প্রাঙ্গণে পুষ্পস্তবক অর্পণের মাধ্যমে শ্রদ্ধা নিবেদন করা হয়।</p>',
            'body_text' => 'স্মৃতিসৌধ প্রাঙ্গণে পুষ্পস্তবক অর্পণের মাধ্যমে শ্রদ্ধা নিবেদন করা হয়।',
            'category_id' => $this->categoryNational->id,
            'dateline_city' => 'ঢাকা',
            'status' => 'published',
            'is_breaking' => true,
            'published_at' => now()->subMinutes(30),
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
            'word_count' => 310,
        ]);
    }

    public function test_authorized_user_can_view_english_wire_service(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.service', 'en'));

        $response->assertOk();
        $response->assertSee('UNB');
        $response->assertSee('English Service');
        $response->assertSee('National Parliamentary Committee Meeting Begins');
    }

    public function test_authorized_user_can_view_bangla_wire_service(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.service', 'bn'));

        $response->assertOk();
        $response->assertSee('UNB');
        $response->assertSee('বাংলা সার্ভিস');
        $response->assertSee('সাভারে জাতীয় স্মৃতিসৌধে রাষ্ট্রপতির শ্রদ্ধা নিবেদন');
    }

    public function test_unauthorized_user_without_permission_is_forbidden(): void
    {
        $unauthRole = Role::create([
            'name' => 'Restricted',
            'type' => 'custom',
            'is_locked' => false,
        ]);

        $unauthRole->permissions()->create([
            'module' => 'stories',
            'can_view' => false,
            'can_create' => false,
            'can_edit' => false,
            'can_publish' => false,
            'can_delete' => false,
        ]);

        $user = User::factory()->create(['role_id' => $unauthRole->id]);

        $response = $this->actingAs($user)->get(route('admin.service', 'en'));
        $response->assertForbidden();
    }

    public function test_category_filter_and_search_reactivity(): void
    {
        Livewire::actingAs($this->editor)
            ->test(WireServiceView::class, ['service' => 'en'])
            ->assertSee('National Parliamentary Committee Meeting Begins')
            ->assertSee('Thousands Without Power After Strong Winds Hit Poland')
            ->call('setCategory', 'world')
            ->assertSet('activeCategory', 'world')
            ->assertSee('Thousands Without Power After Strong Winds Hit Poland')
            ->set('search', 'Poland')
            ->assertSee('Thousands Without Power After Strong Winds Hit Poland');
    }

    public function test_rail_tabs_switching(): void
    {
        Livewire::actingAs($this->editor)
            ->test(WireServiceView::class, ['service' => 'en'])
            ->assertSet('activeRailTab', 'latest')
            ->call('setRailTab', 'popular')
            ->assertSet('activeRailTab', 'popular')
            ->assertSee('National Parliamentary Committee Meeting Begins');
    }

    public function test_editor_can_save_wire_service_configuration(): void
    {
        Livewire::actingAs($this->editor)
            ->test(WireServiceView::class, ['service' => 'en'])
            ->set('wireName', 'UNB Global English Feed')
            ->set('description', 'High-speed real-time news wire distribution.')
            ->set('enabled', true)
            ->call('saveConfig')
            ->assertDispatched('toast', message: 'Wire configuration updated successfully.');

        $setting = Setting::where('key', 'service.en')->first();
        $this->assertNotNull($setting);
        $this->assertSame('UNB Global English Feed', $setting->value['wireName']);
        $this->assertSame('High-speed real-time news wire distribution.', $setting->value['description']);
    }
}
