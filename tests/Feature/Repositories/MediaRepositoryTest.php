<?php

namespace Tests\Feature\Repositories;

use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\Story;
use App\Models\User;
use App\Repositories\MediaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MediaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MediaRepository $repo;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(MediaRepository::class);
        $this->user = User::factory()->create();
    }

    public function test_find_by_id_returns_asset(): void
    {
        $asset = MediaAsset::factory()->create();

        $result = $this->repo->findById($asset->id);

        $this->assertNotNull($result);
        $this->assertEquals($asset->id, $result->id);
    }

    public function test_find_by_public_id_returns_asset(): void
    {
        $asset = MediaAsset::factory()->create(['public_id' => 'test-123']);

        $result = $this->repo->findByPublicId('test-123');

        $this->assertNotNull($result);
    }

    public function test_find_with_relations_loads_relations(): void
    {
        $asset = MediaAsset::factory()->create();

        $result = $this->repo->findWithRelations($asset->id);

        $this->assertTrue($result->relationLoaded('category'));
    }

    public function test_library_assets_returns_library_status(): void
    {
        MediaAsset::factory()->create(['status' => 'library']);
        MediaAsset::factory()->create(['status' => 'field']);

        $result = $this->repo->libraryAssets();

        $this->assertCount(1, $result);
    }

    public function test_get_workflow_tab_counts_returns_counts(): void
    {
        MediaAsset::factory()->create(['status' => 'library', 'approved_at' => now()]);

        $result = $this->repo->getWorkflowTabCounts();

        $this->assertArrayHasKey('all', $result);
        $this->assertArrayHasKey('library', $result);
        $this->assertEquals(1, $result['library']);
    }

    public function test_paginate_assets_returns_paginated(): void
    {
        MediaAsset::factory()->create(['status' => 'library', 'approved_at' => now()]);

        $result = $this->repo->paginateAssets('all', '', 'all', 'all', false, 'new');

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_assets_filters_by_tab(): void
    {
        MediaAsset::factory()->create(['status' => 'library', 'approved_at' => now()]);

        $result = $this->repo->paginateAssets('library', '', 'all', 'all', false, 'new');

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_assets_searches(): void
    {
        MediaAsset::factory()->create(['status' => 'library', 'caption' => 'Test caption']);

        $result = $this->repo->paginateAssets('all', 'Test caption', 'all', 'all', false, 'new');

        $this->assertEquals(1, $result->total());
    }

    public function test_get_pending_batches_returns_collection(): void
    {
        $result = $this->repo->getPendingBatches();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_create_creates_asset(): void
    {
        $asset = MediaAsset::factory()->make();
        $result = $this->repo->create($asset->toArray());

        $this->assertInstanceOf(MediaAsset::class, $result);
        $this->assertDatabaseHas('media_assets', ['id' => $result->id]);
    }

    public function test_update_updates_asset(): void
    {
        $asset = MediaAsset::factory()->create(['caption' => 'Old']);

        $result = $this->repo->update($asset, ['caption' => 'New']);

        $this->assertEquals('New', $result->caption);
    }

    public function test_bulk_update_status_updates_multiple(): void
    {
        $a1 = MediaAsset::factory()->create(['status' => 'field']);
        $a2 = MediaAsset::factory()->create(['status' => 'field']);

        $count = $this->repo->bulkUpdateStatus([$a1->id, $a2->id], 'library');

        $this->assertEquals(2, $count);
    }

    public function test_link_to_story_creates_record(): void
    {
        $story = Story::factory()->create();
        $asset = MediaAsset::factory()->create();

        $this->repo->linkToStory($story->id, $asset->id, 'featured');

        $this->assertDatabaseHas('story_media', ['story_id' => $story->id, 'asset_id' => $asset->id, 'role' => 'featured']);
    }

    public function test_find_batch_with_assets_loads_assets(): void
    {
        $batch = MediaBatch::factory()->create();

        $result = $this->repo->findBatchWithAssets($batch->id);

        $this->assertTrue($result->relationLoaded('assets'));
    }

    public function test_update_batch_updates_batch(): void
    {
        $batch = MediaBatch::factory()->create(['status' => 'pending']);

        $result = $this->repo->updateBatch($batch, ['status' => 'reviewed']);

        $this->assertEquals('reviewed', $result->status);
    }
}
