<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateDerivatives;
use App\Jobs\ProcessIndexOutbox;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_generate_derivatives_populates_asset(): void
    {
        $user = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Test photo',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 50000,
            'checksum' => hash('sha256', 'deriv-test'),
            'storage_disk' => 's3',
            'original_path' => 'originals/deriv-test.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);

        (new GenerateDerivatives($asset->id))->handle();

        $asset->refresh();
        $this->assertArrayHasKey('thumb', $asset->derivatives);
        $this->assertArrayHasKey('preview', $asset->derivatives);
        $this->assertArrayHasKey('large', $asset->derivatives);
        $this->assertStringContainsString('thumb.webp', $asset->derivatives['thumb']['path']);
    }

    public function test_generate_derivatives_skips_if_already_processed(): void
    {
        $user = User::factory()->create();
        $existing = ['thumb' => ['path' => 'd/existing/thumb.webp', 'width' => 400]];
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Already done',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'skip-test'),
            'storage_disk' => 's3',
            'original_path' => 'originals/skip-test.jpg',
            'derivatives' => $existing,
            'uploaded_by' => $user->id,
        ]);

        (new GenerateDerivatives($asset->id))->handle();

        $this->assertEquals($existing, $asset->fresh()->derivatives);
    }

    public function test_process_index_outbox_marks_done(): void
    {
        $rowId = DB::table('index_outbox')->insertGetId([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => (string) Str::ulid(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);

        // No Meilisearch config set — job should skip HTTP and mark done
        (new ProcessIndexOutbox)->handle();

        $row = DB::table('index_outbox')->where('id', $rowId)->first();
        $this->assertEquals('done', $row->status);
        $this->assertNotNull($row->processed_at);
    }

    public function test_process_index_outbox_failure_after_max_attempts(): void
    {
        $this->app['config']->set('services.meilisearch.host', 'http://localhost:7700');
        $this->app['config']->set('services.meilisearch.key', 'test-key');

        Http::fake(fn () => Http::response('Server Error', 500));

        $rowId = DB::table('index_outbox')->insertGetId([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => (string) Str::ulid(),
            'status' => 'pending',
            'attempts' => 2,
            'created_at' => now(),
        ]);

        (new ProcessIndexOutbox)->handle();

        $row = DB::table('index_outbox')->where('id', $rowId)->first();
        $this->assertEquals('failed', $row->status);
        $this->assertEquals(3, $row->attempts);
    }

    public function test_process_index_outbox_retries_on_transient_failure(): void
    {
        $this->app['config']->set('services.meilisearch.host', 'http://localhost:7700');
        $this->app['config']->set('services.meilisearch.key', 'test-key');

        Http::fake(fn () => Http::response('Timeout', 504));

        $rowId = DB::table('index_outbox')->insertGetId([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => (string) Str::ulid(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);

        (new ProcessIndexOutbox)->handle();

        $row = DB::table('index_outbox')->where('id', $rowId)->first();
        // attempts was 0, incremented to 1, still < 3 so stays pending for retry
        $this->assertEquals('pending', $row->status);
        $this->assertEquals(1, $row->attempts);
    }
}
