<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TusUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $businessUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $editorRole = Role::where('name', 'Editor')->first();
        $businessRole = Role::where('name', 'Business Team')->first();

        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
        $this->businessUser = User::factory()->create(['role_id' => $businessRole->id]);
    }

    public function test_guest_cannot_create_upload_session(): void
    {
        $this->postJson('/api/uploads', [
            'upload_length' => 1024,
            'filename' => 'test.jpg',
        ])->assertStatus(401);
    }

    public function test_unauthorized_role_cannot_create_upload_session(): void
    {
        $this->actingAs($this->businessUser);

        $this->postJson('/api/uploads', [
            'upload_length' => 1024,
            'filename' => 'test.jpg',
        ])->assertStatus(403);
    }

    public function test_authorized_user_can_create_upload_session(): void
    {
        $this->actingAs($this->editor);

        $response = $this->postJson('/api/uploads', [
            'upload_length' => 2048,
            'filename' => 'photo.jpg',
        ]);

        $response->assertCreated();
        $response->assertHeader('Tus-Resumable', '1.0.0');
        $response->assertHeader('Location');

        $location = $response->headers->get('Location');
        $id = basename($location);

        $this->assertDatabaseHas('upload_sessions', [
            'id' => $id,
            'user_id' => $this->editor->id,
            'size_bytes' => 2048,
            'offset_bytes' => 0,
            'status' => 'active',
        ]);
    }

    public function test_head_returns_upload_offset_and_length(): void
    {
        $this->actingAs($this->editor);

        $createResponse = $this->postJson('/api/uploads', [
            'upload_length' => 4096,
            'filename' => 'doc.pdf',
        ]);

        $id = basename($createResponse->headers->get('Location'));

        $headResponse = $this->call('HEAD', '/api/uploads/'.$id);

        $headResponse->assertOk();
        $headResponse->assertHeader('Upload-Offset', '0');
        $headResponse->assertHeader('Upload-Length', '4096');
        $headResponse->assertHeader('Tus-Resumable', '1.0.0');
    }

    public function test_patch_appends_chunk_and_updates_offset(): void
    {
        $this->actingAs($this->editor);

        $createResponse = $this->postJson('/api/uploads', [
            'upload_length' => 20,
            'filename' => 'test.bin',
        ]);

        $id = basename($createResponse->headers->get('Location'));

        $patchResponse = $this->call('PATCH', '/api/uploads/'.$id, [], [], [], [
            'HTTP_Upload-Offset' => '0',
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], 'hello ');

        $patchResponse->assertStatus(204);
        $patchResponse->assertHeader('Upload-Offset', '6');

        $this->assertDatabaseHas('upload_sessions', [
            'id' => $id,
            'offset_bytes' => 6,
        ]);
    }

    public function test_patch_offset_mismatch_returns_409_conflict(): void
    {
        $this->actingAs($this->editor);

        $createResponse = $this->postJson('/api/uploads', [
            'upload_length' => 100,
            'filename' => 'test.bin',
        ]);

        $id = basename($createResponse->headers->get('Location'));

        $patchResponse = $this->call('PATCH', '/api/uploads/'.$id, [], [], [], [
            'HTTP_Upload-Offset' => '5',
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], 'data');

        $patchResponse->assertStatus(409);
        $patchResponse->assertHeader('Tus-Resumable', '1.0.0');
    }

    public function test_patch_completes_when_bytes_match_length(): void
    {
        $this->actingAs($this->editor);

        $createResponse = $this->postJson('/api/uploads', [
            'upload_length' => 11,
            'filename' => 'test.bin',
        ]);

        $id = basename($createResponse->headers->get('Location'));

        $this->call('PATCH', '/api/uploads/'.$id, [], [], [], [
            'HTTP_Upload-Offset' => '0',
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], 'hello world');

        $this->assertDatabaseHas('upload_sessions', [
            'id' => $id,
            'status' => 'completed',
            'offset_bytes' => 11,
        ]);
    }

    public function test_user_cannot_access_or_patch_another_users_upload_session(): void
    {
        $this->actingAs($this->editor);

        $createResponse = $this->postJson('/api/uploads', [
            'upload_length' => 100,
            'filename' => 'test.bin',
        ]);

        $id = basename($createResponse->headers->get('Location'));

        $this->actingAs($this->businessUser);

        // Grant business user a role with media:create so PATCH doesn't fail on form request
        // But the controller ownership check should still block
        $adminRole = Role::where('name', 'Admin')->first();
        $otherEditor = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($otherEditor);

        $this->call('HEAD', '/api/uploads/'.$id)->assertStatus(403);

        $this->call('PATCH', '/api/uploads/'.$id, [], [], [], [
            'HTTP_Upload-Offset' => '0',
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], 'data')->assertStatus(403);
    }
}
