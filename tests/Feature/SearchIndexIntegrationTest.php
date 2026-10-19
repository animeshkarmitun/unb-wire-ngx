<?php

namespace Tests\Feature;

use App\Jobs\ProcessIndexOutbox;
use App\Models\Category;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchIndexIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_sends_correct_document_payload_to_meilisearch(): void
    {
        $category = Category::factory()->create();

        $story = Story::factory()->published()->create([
            'headline' => 'Sample Breaking Headline',
            'brief' => 'Sample brief content for test',
            'body_text' => 'Sample full body text content for index testing.',
            'language' => 'en',
            'category_id' => $category->id,
            'is_breaking' => false,
        ]);

        DB::table('index_outbox')->insert([
            'index_name' => 'main',
            'document_id' => $story->public_id,
            'op' => 'upsert',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        config([
            'services.meilisearch.host' => 'http://meilisearch.test',
            'services.meilisearch.key' => 'test-key',
        ]);

        Http::fake([
            'http://meilisearch.test/*' => Http::response(['taskUid' => 1], 202),
        ]);

        (new ProcessIndexOutbox)->handle();

        Http::assertSent(function (Request $request) use ($story) {
            $doc = $request->data()[0] ?? null;

            return $request->url() === 'http://meilisearch.test/indexes/main/documents'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $doc !== null
                && $doc['id'] === $story->public_id
                && $doc['objectID'] === $story->public_id
                && $doc['headline'] === 'Sample Breaking Headline'
                && $doc['brief'] === 'Sample brief content for test'
                && $doc['body_text'] === 'Sample full body text content for index testing.'
                && $doc['language'] === 'en'
                && $doc['category_id'] === $story->category_id
                && $doc['is_breaking'] === false
                && $doc['status'] === 'published';
        });

        $row = DB::table('index_outbox')->where('document_id', $story->public_id)->first();
        $this->assertNotNull($row);
        $this->assertSame('done', $row->status);
        $this->assertNotNull($row->processed_at);
    }

    public function test_outbox_sends_delete_to_meilisearch_for_delete_op(): void
    {
        $story = Story::factory()->create([
            'status' => 'killed',
        ]);

        DB::table('index_outbox')->insert([
            'index_name' => 'main',
            'document_id' => $story->public_id,
            'op' => 'delete',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        config([
            'services.meilisearch.host' => 'http://meilisearch.test',
            'services.meilisearch.key' => 'test-key',
        ]);

        Http::fake([
            'http://meilisearch.test/*' => Http::response(['taskUid' => 1], 202),
        ]);

        (new ProcessIndexOutbox)->handle();

        Http::assertSent(function (Request $request) use ($story) {
            return $request->url() === "http://meilisearch.test/indexes/main/documents/{$story->public_id}"
                && $request->method() === 'DELETE'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });

        $row = DB::table('index_outbox')->where('document_id', $story->public_id)->first();
        $this->assertNotNull($row);
        $this->assertSame('done', $row->status);
    }
}
