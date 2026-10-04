<?php

namespace Tests\Feature\Repositories;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(CategoryRepository::class);
    }

    public function test_root_categories_returns_parent_only(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'sort_order' => 1]);
        Category::factory()->create(['parent_id' => $root->id, 'sort_order' => 1]);

        $result = $this->repo->rootCategories();

        $this->assertCount(1, $result);
    }

    public function test_subcategories_of_returns_children(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $root->id]);

        $result = $this->repo->subcategoriesOf($root->id);

        $this->assertCount(1, $result);
    }

    public function test_categories_tree_loads_children(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $root->id]);

        $result = $this->repo->categoriesTree();

        $this->assertTrue($result->first()->relationLoaded('children'));
    }

    public function test_find_first_root_returns_first(): void
    {
        Category::factory()->create(['parent_id' => null, 'sort_order' => 1]);

        $result = $this->repo->findFirstRoot();

        $this->assertNotNull($result);
    }

    public function test_find_by_id_returns_category(): void
    {
        $cat = Category::factory()->create();

        $result = $this->repo->findById($cat->id);

        $this->assertEquals($cat->id, $result->id);
    }

    public function test_find_by_slug_returns_category(): void
    {
        Category::factory()->create(['slug' => 'test-slug']);

        $result = $this->repo->findBySlug('test-slug');

        $this->assertNotNull($result);
        $this->assertEquals('test-slug', $result->slug);
    }

    public function test_find_by_name_like_returns_category(): void
    {
        Category::factory()->create(['name_en' => 'Test Category']);

        $result = $this->repo->findByNameLike('Test');

        $this->assertNotNull($result);
    }

    public function test_with_published_story_counts_returns_counts(): void
    {
        Category::factory()->create();

        $result = $this->repo->withPublishedStoryCounts('en');

        $this->assertNotEmpty($result);
    }

    public function test_categories_with_published_stories_filters(): void
    {
        Category::factory()->create();

        $result = $this->repo->categoriesWithPublishedStories('en');

        $this->assertInstanceOf(Collection::class, $result);
    }
}
