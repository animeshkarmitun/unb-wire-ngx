<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\PresignedUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_presigned_url_service_generates_url(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/test.jpg', 'fake-content');
        $user = User::factory()->create();
        $a = MediaAsset::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Test photo',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 12345,
            'checksum' => hash('sha256', 'test'),
            'storage_disk' => 's3',
            'original_path' => 'originals/test.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);
        $svc = app(PresignedUrlService::class);
        $url = $svc->forAsset($a, 'original', 5);
        $this->assertNotEmpty($url);
    }

    public function test_generate_derivatives_job(): void
    {
        $user = User::factory()->create();
        $a = MediaAsset::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'T',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'x'),
            'storage_disk' => 's3',
            'original_path' => 'originals/x.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);
        (new \App\Jobs\GenerateDerivatives($a->id))->handle();
        $this->assertNotEmpty($a->refresh()->derivatives['thumb']);
    }

    public function test_tus_create_and_patch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $resp = $this->postJson('/api/uploads', ['upload_length' => 11, 'filename' => 'test.bin']);
        $resp->assertCreated();
        $loc = $resp->headers->get('Location');
        $id = basename($loc);
        $patch = $this->call('PATCH', '/api/uploads/'.$id, [], [], [], ['HTTP_Upload-Offset' => '0', 'CONTENT_TYPE' => 'application/offset+octet-stream'], 'hello world');
        $patch->assertStatus(204);
        $this->assertDatabaseHas('upload_sessions', ['id' => $id, 'status' => 'completed']);
    }
}
