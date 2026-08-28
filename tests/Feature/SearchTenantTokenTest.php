<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchTenantTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_token_returns_jwt_structure(): void
    {
        $resp = $this->postJson('/api/v1/portal/search-token');
        $resp->assertOk();
        $json = $resp->json();
        $this->assertNotEmpty($json['token']);
        $this->assertStringContainsString('.', $json['token']);
        $this->assertEquals('language IN [en, bn]', $json['filter']);
    }

    public function test_search_token_with_client_entitlement(): void
    {
        $client = Client::factory()->create();
        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['en'], 'category_ids' => null]]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()]);
        $raw = 'test-raw-key-'.\Illuminate\Support\Str::random(16);
        DB::table('client_api_keys')->insert(['client_id' => $client->id, 'name' => 'test', 'key_hash' => hash('sha256', $raw), 'scopes' => json_encode(['feed:read']), 'rate_limit_rpm' => 60, 'created_at' => now(), 'updated_at' => now()]);

        $resp = $this->postJson('/api/v1/portal/search-token', [], ['Authorization' => 'Bearer '.$raw]);
        $resp->assertOk();
        $this->assertStringContainsString('language IN [en]', $resp->json('filter'));
    }

    public function test_outbox_processes_upsert_with_payload(): void
    {
        Http::fake();
        $cat = \App\Models\Category::factory()->create();
        $user = \App\Models\User::factory()->create();
        $story = \App\Models\Story::factory()->create(['status' => 'published', 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);
        DB::table('index_outbox')->insert(['index_name' => 'main', 'op' => 'upsert', 'document_id' => $story->public_id, 'status' => 'pending', 'attempts' => 0, 'created_at' => now()]);
        config(['services.meilisearch.host' => 'http://localhost:7700', 'services.meilisearch.key' => 'testkey']);
        (new \App\Jobs\ProcessIndexOutbox())->handle();
        $this->assertDatabaseHas('index_outbox', ['document_id' => $story->public_id, 'status' => 'done']);
    }

    public function test_outbox_retries_then_fails(): void
    {
        Http::fake(fn() => throw new \Exception('network'));
        DB::table('index_outbox')->insert(['index_name' => 'main', 'op' => 'upsert', 'document_id' => '0123456789ULIDFAKE0000000', 'status' => 'pending', 'attempts' => 2, 'created_at' => now()]);
        config(['services.meilisearch.host' => 'http://localhost:7700', 'services.meilisearch.key' => 'testkey']);
        (new \App\Jobs\ProcessIndexOutbox())->handle();
        $this->assertDatabaseHas('index_outbox', ['document_id' => '0123456789ULIDFAKE0000000', 'status' => 'failed']);
    }
}
