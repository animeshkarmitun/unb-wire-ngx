<?php

namespace Tests\Feature;

use App\Livewire\Admin\PhotoManager;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\Role;
use App\Models\User;
use App\Repositories\MediaRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MediaDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $editorRole = Role::where('name', 'Editor')->first();
        $this->user = User::factory()->create(['role_id' => $editorRole?->id]);
    }

    private function imageBytes(int $w = 300, int $h = 200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 30, 90, 200));
        ob_start();
        imagejpeg($im, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    private function makeAsset(string $checksum, ?int $batchId = null): MediaAsset
    {
        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'batch_id' => $batchId,
            'kind' => 'photo',
            'status' => 'field',
            'source' => 'field',
            'title' => 'Existing photo',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => $checksum,
            'storage_disk' => 'public',
            'original_path' => 'media/uploads/x.jpg',
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_find_duplicate_of_matches_checksum(): void
    {
        $checksum = hash('sha256', 'same-bytes');
        $existing = $this->makeAsset($checksum);
        $newcomer = $this->makeAsset($checksum);
        $unique = $this->makeAsset(hash('sha256', 'other-bytes'));

        $repo = app(MediaRepository::class);
        $this->assertSame($existing->id, $repo->findDuplicateOf($newcomer)->id);
        $this->assertNull($repo->findDuplicateOf($unique));
    }

    public function test_desk_upload_warns_with_link_to_existing_asset(): void
    {
        Storage::fake('public');
        $bytes = $this->imageBytes();
        $this->makeAsset(hash('sha256', $bytes));

        $component = Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->set('uploads', [UploadedFile::fake()->createWithContent('shot.jpg', $bytes)])
            ->assertDispatched('toast', message: 'Possible duplicates detected - verify before publishing');

        $duplicates = $component->get('uploadDuplicates');
        $this->assertCount(1, $duplicates);
        $this->assertSame('Existing photo', $duplicates[0]['existing_title']);
    }

    public function test_upload_without_duplicate_sets_no_warnings(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->set('uploads', [UploadedFile::fake()->createWithContent('unique.jpg', $this->imageBytes())]);

        $this->assertSame([], $component->get('uploadDuplicates'));
    }

    public function test_intake_queue_marks_possible_duplicates(): void
    {
        Storage::fake('public');
        $checksum = hash('sha256', 'intake-dup');
        $batch = MediaBatch::create(['uploader_id' => $this->user->id, 'event_label' => 'Dup check', 'urgency' => 'routine', 'status' => 'pending', 'submitted_at' => now()]);
        $this->makeAsset($checksum, $batch->id);
        $this->makeAsset($checksum);

        Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->set('tab', 'field')
            ->assertSee('Possible duplicate');
    }
}
