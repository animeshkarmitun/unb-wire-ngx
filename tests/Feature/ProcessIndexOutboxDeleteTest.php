<?php

namespace Tests\Feature;

use App\Jobs\ProcessIndexOutbox;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessIndexOutboxDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_branch_calls_meili_delete_and_marks_done(): void
    {
        Http::fake(['meili.example.test/*' => Http::response(['ok' => true], 200)]);
        config(['services.meilisearch.host' => 'https://meili.example.test', 'services.meilisearch.key' => 'k']);

        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'killed',
            'version' => 1,
        ]);

        DB::table('index_outbox')->insert([
            'index_name' => 'main',
            'op' => 'delete',
            'document_id' => $story->public_id,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);

        (new ProcessIndexOutbox)->handle();

        Http::assertSent(function ($request) use ($story) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/indexes/main/documents/'.$story->public_id);
        });
        $this->assertDatabaseHas('index_outbox', [
            'op' => 'delete',
            'document_id' => $story->public_id,
            'status' => 'done',
        ]);
    }

    public function test_delete_branch_with_no_host_marks_done(): void
    {
        config(['services.meilisearch.host' => null, 'services.meilisearch.key' => null]);

        $rowId = DB::table('index_outbox')->insertGetId([
            'index_name' => 'main',
            'op' => 'delete',
            'document_id' => '01NOHOSTDELETE000000001',
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);

        (new ProcessIndexOutbox)->handle();

        // Without a Meili host configured, the row is marked done to avoid queue pile-up
        // (matches the upsert no-host behaviour). Name the contract so it cannot be mistaken for a real delete.
        $row = DB::table('index_outbox')->where('id', $rowId)->first();
        $this->assertSame('done', $row->status);
    }
}
