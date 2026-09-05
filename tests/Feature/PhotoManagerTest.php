<?php

namespace Tests\Feature;

use App\Livewire\Admin\PhotoManager;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\MediaReview;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $photographer;

    protected Category $category;

    protected Package $stdPkg;

    protected Package $excPkg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CategorySeeder::class,
            PackageSeeder::class,
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        $uploaderRole = Role::where('name', 'Uploader-English')->first();

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'name' => 'Nahar Khan']);
        $this->photographer = User::factory()->create(['role_id' => $uploaderRole->id, 'name' => 'Enamul Haque']);

        $this->category = Category::where('slug', 'bangladesh')->first();
        $this->stdPkg = Package::where('code', 'STANDARD-NEWS')->first();
        $this->excPkg = Package::where('code', 'PREMIUM-BUNDLE')->first();
    }

    public function test_photo_manager_renders_and_computes_7_workflow_tab_counts(): void
    {
        $this->actingAs($this->admin);

        // 1. Library photo (approved)
        MediaAsset::create([
            'title' => 'Library photo',
            'caption' => 'In library photo',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'library',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'p1'),
            'storage_disk' => 'public',
            'original_path' => 'p1.jpg',
            'uploaded_by' => $this->photographer->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now()->subDay(),
        ]);

        // 2. Needs review photo (desk upload, approved_at is null)
        MediaAsset::create([
            'title' => 'Review photo',
            'caption' => 'Needs review photo',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'p2'),
            'storage_disk' => 'public',
            'original_path' => 'p2.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        // 3. Field batch photo
        $batch = MediaBatch::create([
            'uploader_id' => $this->photographer->id,
            'event_label' => 'City flood',
            'urgency' => 'urgent',
            'status' => 'pending',
            'submitted_at' => now()->subMinutes(15),
        ]);

        MediaAsset::create([
            'title' => 'Field batch photo',
            'caption' => 'Batch intake photo',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'batch_id' => $batch->id,
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'p3'),
            'storage_disk' => 'public',
            'original_path' => 'p3.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        // 4. Embargoed photo
        MediaAsset::create([
            'title' => 'Embargo photo',
            'caption' => 'Embargoed photo',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'library',
            'embargo_until' => now()->addHours(2),
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'p4'),
            'storage_disk' => 'public',
            'original_path' => 'p4.jpg',
            'uploaded_by' => $this->photographer->id,
            'approved_at' => now()->subHour(),
        ]);

        $component = Livewire::test(PhotoManager::class);
        $component->assertStatus(200);

        $counts = $component->viewData('counts');
        $this->assertArrayHasKey('all', $counts);
        $this->assertArrayHasKey('field', $counts);
        $this->assertArrayHasKey('review', $counts);
        $this->assertArrayHasKey('library', $counts);
        $this->assertArrayHasKey('packaged', $counts);
        $this->assertArrayHasKey('published', $counts);
        $this->assertArrayHasKey('embargo', $counts);

        $this->assertEquals(1, $counts['field']);
        $this->assertEquals(1, $counts['review']);
        $this->assertEquals(2, $counts['library']);
        $this->assertEquals(1, $counts['embargo']);
    }

    public function test_search_and_filters(): void
    {
        $this->actingAs($this->admin);

        $a1 = MediaAsset::create([
            'title' => 'Padma Bridge Sunrise',
            'caption' => 'Glorious sunrise at Padma bridge',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'library',
            'category_id' => $this->category->id,
            'location_city' => 'Mawa',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'padma'),
            'storage_disk' => 'public',
            'original_path' => 'padma.jpg',
            'uploaded_by' => $this->photographer->id,
            'approved_at' => now(),
            'download_count' => 50,
        ]);

        $a2 = MediaAsset::create([
            'title' => 'Chittagong Port Trade',
            'caption' => 'Cargo ships dock at port',
            'credit_line' => 'Photo: Mahmud Hossain / UNB',
            'kind' => 'photo',
            'status' => 'library',
            'location_city' => 'Chattogram',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'port'),
            'storage_disk' => 'public',
            'original_path' => 'port.jpg',
            'uploaded_by' => $this->admin->id,
            'approved_at' => now(),
            'download_count' => 10,
        ]);

        $component = Livewire::test(PhotoManager::class);

        // Search
        $component->set('search', 'Padma');
        $this->assertTrue($component->viewData('assets')->contains($a1));
        $this->assertFalse($component->viewData('assets')->contains($a2));

        // Photographer filter
        $component->set('search', '');
        $component->set('photographer', 'Enamul Haque');
        $this->assertTrue($component->viewData('assets')->contains($a1));
        $this->assertFalse($component->viewData('assets')->contains($a2));

        // Sort by downloads
        $component->set('photographer', 'all');
        $component->set('sort', 'dl');
        $firstAsset = $component->viewData('assets')->first();
        $this->assertEquals($a1->id, $firstAsset->id);
    }

    public function test_inspector_selection_and_metadata_save(): void
    {
        $this->actingAs($this->admin);

        $asset = MediaAsset::create([
            'title' => 'Original title',
            'caption' => 'Original caption',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'location_city' => 'Dhaka',
            'en_tags' => ['old_tag'],
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'insp_test'),
            'storage_disk' => 'public',
            'original_path' => 'insp.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $component = Livewire::test(PhotoManager::class);
        $component->call('selectAsset', $asset->id);

        $this->assertEquals($asset->id, $component->get('selectedAssetId'));
        $this->assertEquals('Original caption', $component->get('inspCaption'));

        // Edit metadata
        $component->set('inspCaption', 'Updated descriptive caption');
        $component->set('inspPhotographer', 'Sadia Rahman');
        $component->set('inspLocation', 'Sylhet');
        $component->set('inspKeywords', 'flood, monsoon, sylhet');
        $component->set('inspPackage', 'Standard');
        $component->call('saveAssetMetadata');

        $asset->refresh();
        $this->assertEquals('Updated descriptive caption', $asset->caption);
        $this->assertEquals('Photo: Sadia Rahman / UNB', $asset->credit_line);
        $this->assertEquals('Sylhet', $asset->location_city);
        $this->assertEquals(['flood', 'monsoon', 'sylhet'], $asset->en_tags);
        $this->assertTrue($asset->packages()->where('code', 'STANDARD-NEWS')->exists());

        // Approve via inspector
        $component->call('approveInspected');
        $asset->refresh();
        $this->assertEquals('library', $asset->status);
        $this->assertNotNull($asset->approved_at);
    }

    public function test_burst_stack_expansion_and_cover_selection(): void
    {
        $this->actingAs($this->admin);

        $asset = MediaAsset::create([
            'title' => 'Burst series lead',
            'caption' => 'Burst series headline frame',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'library',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'burst_test'),
            'storage_disk' => 'public',
            'original_path' => 'burst.jpg',
            'uploaded_by' => $this->photographer->id,
            'approved_at' => now(),
            'derivatives' => [
                'stack' => 9,
                'r' => 1.5,
                'grad' => 'g1',
            ],
        ]);

        $component = Livewire::test(PhotoManager::class);
        $component->call('toggleStack', $asset->id);
        $this->assertEquals($asset->id, $component->get('expandedStackId'));

        $component->call('setStackCover', $asset->id, 3);
        $asset->refresh();
        $this->assertEquals(1.78, $asset->derivatives['r']);
        $this->assertEquals('g4', $asset->derivatives['grad']);
    }

    public function test_bulk_approve_and_bulk_assign_package(): void
    {
        $this->actingAs($this->admin);

        $a1 = MediaAsset::create([
            'title' => 'Review 1',
            'caption' => 'Pending review 1',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'b1'),
            'storage_disk' => 'public',
            'original_path' => 'b1.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $a2 = MediaAsset::create([
            'title' => 'Review 2',
            'caption' => 'Pending review 2',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'b2'),
            'storage_disk' => 'public',
            'original_path' => 'b2.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $component = Livewire::test(PhotoManager::class);
        $component->call('toggleSelect', $a1->id);
        $component->call('toggleSelect', $a2->id);
        $this->assertCount(2, $component->get('selectedIds'));

        // Bulk approve
        $component->call('bulkApprove');
        $this->assertEquals('library', $a1->refresh()->status);
        $this->assertEquals('library', $a2->refresh()->status);
        $this->assertEmpty($component->get('selectedIds'));

        // Select again & assign package
        $component->call('toggleSelect', $a1->id);
        $component->call('toggleSelect', $a2->id);
        $component->call('bulkAssignPackage', 'Exclusive');

        $this->assertTrue($a1->packages()->where('code', 'PREMIUM-BUNDLE')->exists());
        $this->assertTrue($a2->packages()->where('code', 'PREMIUM-BUNDLE')->exists());
    }

    public function test_field_intake_queue_approval_batch_and_single(): void
    {
        $this->actingAs($this->admin);

        $batch = MediaBatch::create([
            'uploader_id' => $this->photographer->id,
            'event_label' => 'Norwester storm',
            'urgency' => 'urgent',
            'status' => 'pending',
            'submitted_at' => now()->subMinutes(10),
        ]);

        $f1 = MediaAsset::create([
            'title' => 'F1',
            'caption' => 'Tree fallen',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'batch_id' => $batch->id,
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'f1'),
            'storage_disk' => 'public',
            'original_path' => 'f1.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $f2 = MediaAsset::create([
            'title' => 'F2',
            'caption' => 'Road flooded',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'batch_id' => $batch->id,
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'f2'),
            'storage_disk' => 'public',
            'original_path' => 'f2.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $component = Livewire::test(PhotoManager::class);
        $component->call('setTab', 'field');

        // Approve single frame
        $component->call('approveFieldAsset', $f1->id);
        $this->assertEquals('library', $f1->refresh()->status);
        $this->assertDatabaseHas('media_reviews', [
            'asset_id' => $f1->id,
            'action' => 'approve',
        ]);

        // Approve remaining batch
        $component->call('approveFieldBatch', $batch->id);
        $this->assertEquals('library', $f2->refresh()->status);
        $this->assertEquals('reviewed', $batch->refresh()->status);
        $this->assertDatabaseHas('media_reviews', [
            'asset_id' => $f2->id,
            'action' => 'approve',
        ]);
    }

    public function test_field_intake_rejection_and_reedit_reason_modal(): void
    {
        $this->actingAs($this->admin);

        $batch = MediaBatch::create([
            'uploader_id' => $this->photographer->id,
            'event_label' => 'Hilsa landing',
            'urgency' => 'routine',
            'status' => 'pending',
            'submitted_at' => now()->subMinutes(20),
        ]);

        $photo = MediaAsset::create([
            'title' => 'Hilsa photo',
            'caption' => 'Fishermen landing hilsa',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'batch_id' => $batch->id,
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'hilsa'),
            'storage_disk' => 'public',
            'original_path' => 'hilsa.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $component = Livewire::test(PhotoManager::class);

        // Open reject photo modal
        $component->call('openModal', 'reject_photo', null, $photo->id);
        $this->assertTrue($component->get('modalOpen'));
        $this->assertEquals('Reject photo', $component->get('modalTitle'));

        $component->set('selectedReason', 'Out of focus / soft at full size');
        $component->set('reasonNote', 'Zoom 100% shows motion blur');
        $component->call('confirmModalAction');

        $this->assertFalse($component->get('modalOpen'));
        $this->assertEquals('rejected', $photo->refresh()->status);

        $review = MediaReview::where('asset_id', $photo->id)->first();
        $this->assertNotNull($review);
        $this->assertEquals('reject', $review->action);
        $this->assertStringContainsString('motion blur', $review->note);

        // Re-edit batch test
        $photo2 = MediaAsset::create([
            'title' => 'Auction photo',
            'caption' => 'Hilsa auction',
            'credit_line' => 'Photo: Enamul Haque / UNB',
            'kind' => 'photo',
            'status' => 'field',
            'batch_id' => $batch->id,
            'mime' => 'image/jpeg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', 'auction'),
            'storage_disk' => 'public',
            'original_path' => 'auction.jpg',
            'uploaded_by' => $this->photographer->id,
        ]);

        $component->call('openModal', 'reedit_batch', $batch->id, null);
        $this->assertEquals('reedit_batch', $component->get('modalType'));
        $component->set('selectedReason', 'Captions need names / places filled in');
        $component->set('reasonNote', 'Include name of auctioneer');
        $component->call('confirmModalAction');

        $this->assertEquals('reedit', $photo2->refresh()->status);
        $this->assertEquals('reviewed', $batch->refresh()->status);
        $this->assertDatabaseHas('media_reviews', [
            'asset_id' => $photo2->id,
            'action' => 'reedit',
        ]);
    }
}
