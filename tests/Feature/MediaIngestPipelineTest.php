<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Repositories\MediaRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaIngestPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $editorRole = Role::where('name', 'Editor')->first();
        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
    }

    private function createSession(int $length, string $filename = 'field-shot.jpg'): string
    {
        $response = $this->actingAs($this->editor)->postJson('/api/uploads', [
            'upload_length' => $length,
            'filename' => $filename,
        ]);

        return basename($response->headers->get('Location'));
    }

    private function patchChunk(string $id, string $bytes, int $offset)
    {
        return $this->call('PATCH', '/api/uploads/'.$id, [], [], [], [
            'HTTP_Upload-Offset' => (string) $offset,
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], $bytes);
    }

    public function test_patch_stores_bytes_and_hands_off_to_intake_queue(): void
    {
        Storage::fake('public');
        $payload = 'real-jpeg-bytes-not-discarded';
        $id = $this->createSession(strlen($payload));

        $response = $this->patchChunk($id, $payload, 0);
        $response->assertStatus(204);
        $this->assertSame((string) strlen($payload), $response->headers->get('Upload-Offset'));

        $this->assertDatabaseHas('upload_sessions', ['id' => $id, 'status' => 'completed']);
        $this->assertDatabaseHas('media_batches', [
            'uploader_id' => $this->editor->id,
            'status' => 'pending',
        ]);

        $asset = MediaAsset::where('uploaded_by', $this->editor->id)->where('status', 'field')->firstOrFail();
        $this->assertSame(hash('sha256', $payload), $asset->checksum);
        $this->assertSame('field', $asset->source);
        $this->assertNotNull($asset->batch_id);
        $this->assertSame($payload, Storage::disk('public')->get($asset->original_path));

        $pending = app(MediaRepository::class)->getPendingBatches();
        $this->assertTrue($pending->contains('id', $asset->batch_id));
    }

    public function test_chunked_resumable_upload_stores_all_bytes_in_order(): void
    {
        Storage::fake('public');
        $chunkA = str_repeat('A', 10);
        $chunkB = str_repeat('B', 6);
        $id = $this->createSession(strlen($chunkA) + strlen($chunkB), 'chunked.png');

        $this->patchChunk($id, $chunkA, 0)->assertStatus(204);
        $this->call('HEAD', '/api/uploads/'.$id)->assertHeader('Upload-Offset', '10');
        $this->patchChunk($id, $chunkB, 10)->assertStatus(204);

        $asset = MediaAsset::where('uploaded_by', $this->editor->id)->firstOrFail();
        $this->assertSame($chunkA.$chunkB, Storage::disk('public')->get($asset->original_path));
        $this->assertSame('image/png', $asset->mime);
        $this->assertSame(hash('sha256', $chunkA.$chunkB), $asset->checksum);
    }

    public function test_partial_upload_is_not_handed_off_yet(): void
    {
        Storage::fake('public');
        $id = $this->createSession(50, 'partial.jpg');

        $this->patchChunk($id, str_repeat('x', 20), 0)->assertStatus(204);

        $this->assertDatabaseHas('upload_sessions', ['id' => $id, 'status' => 'active']);
        $this->assertDatabaseCount('media_batches', 0);
    }

    public function test_completed_session_does_not_double_ingest_on_extra_patch(): void
    {
        Storage::fake('public');
        $payload = 'exact-bytes';
        $id = $this->createSession(strlen($payload), 'once.jpg');

        $this->patchChunk($id, $payload, 0)->assertStatus(204);

        $this->call('HEAD', '/api/uploads/'.$id)->assertHeader('Upload-Offset', (string) strlen($payload));
        $this->patchChunk($id, '', (int) strlen($payload))->assertStatus(204);

        $this->assertDatabaseCount('media_batches', 1);
        $this->assertDatabaseCount('media_assets', 1);
    }
}
