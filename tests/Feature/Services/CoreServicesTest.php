<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\Media\PresignedUrlService;
use App\Services\NoteService;
use App\Services\NotificationService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_presigned_url_service_records_download(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Download test',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 25000,
            'checksum' => hash('sha256', 'dl-test'),
            'storage_disk' => 's3',
            'original_path' => 'originals/dl-test.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
            'download_count' => 0,
        ]);

        $svc = app(PresignedUrlService::class);
        $svc->recordDownload($asset, $client->id, null, 'original');

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'media',
            'item_id' => $asset->id,
            'format' => 'original',
        ]);

        $this->assertEquals(1, $asset->fresh()->download_count);
    }

    public function test_presigned_url_service_skips_download_when_no_client(): void
    {
        $user = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'No client test',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'no-client'),
            'storage_disk' => 's3',
            'original_path' => 'originals/no-client.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);

        $countBefore = DB::table('downloads')->count();

        $svc = app(PresignedUrlService::class);
        $svc->recordDownload($asset, null, null, 'original');

        $this->assertEquals($countBefore, DB::table('downloads')->count());
    }

    public function test_note_service_creates_internal_note(): void
    {
        $editorRole = Role::where('name', 'Editor')->first();
        $user = User::factory()->create(['role_id' => $editorRole->id]);
        $story = Story::factory()->create(['owner_id' => $user->id]);

        $svc = app(NoteService::class);
        $note = $svc->add($story, $user, 'This is an internal editorial note.');

        $this->assertTrue($note->is_internal);
        $this->assertEquals($user->id, $note->user_id);
        $this->assertEquals($story->id, $note->story_id);
        $this->assertEquals('note', $note->kind);
        $this->assertEquals('This is an internal editorial note.', $note->body);

        $this->assertDatabaseHas('story_notes', [
            'id' => $note->id,
            'is_internal' => true,
        ]);

        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'actor_id' => $user->id,
            'action' => 'note_added',
        ]);
    }

    public function test_notification_service_dispatches_to_editors_and_admins(): void
    {
        Notification::fake();

        $editorRole = Role::where('name', 'Editor')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        $editor = User::factory()->create(['role_id' => $editorRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $story = Story::factory()->create(['owner_id' => $editor->id]);

        $svc = app(NotificationService::class);
        $svc->notifyReviewRequested($story->id, $story->headline, $editor->id);

        Notification::assertSentTo(
            [$editor, $admin],
            StoryNotification::class,
        );
    }

    public function test_notification_service_skips_duplicate_within_burst_window(): void
    {
        Notification::fake();

        $editorRole = Role::where('name', 'Editor')->first();
        $editor = User::factory()->create(['role_id' => $editorRole->id]);
        $story = Story::factory()->create(['owner_id' => $editor->id]);

        $svc = app(NotificationService::class);
        $svc->notifyReviewRequested($story->id, $story->headline, $editor->id);
        $svc->notifyReviewRequested($story->id, $story->headline, $editor->id);

        // Second call should be deduped by burst cache.
        // assertSentTimes counts total dispatches (once per recipient); only 1 send op occurred.
        Notification::assertSentTo(
            $editor,
            StoryNotification::class,
            1,
        );
    }
}
