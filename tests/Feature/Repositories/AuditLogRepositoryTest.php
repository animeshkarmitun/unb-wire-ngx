<?php

namespace Tests\Feature\Repositories;

use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(AuditLogRepository::class);
    }

    public function test_log_inserts_audit_record(): void
    {
        $this->repo->log('test.action', 'role', 1, ['message' => 'Test']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'entity_type' => 'role',
            'entity_id' => 1,
        ]);
    }

    public function test_log_inserts_with_ip_and_user_agent(): void
    {
        $this->repo->log('test.action', null, null, [], '127.0.0.1', 'TestAgent');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'ip' => '127.0.0.1',
            'user_agent' => 'TestAgent',
        ]);
    }

    public function test_for_entity_returns_paginated(): void
    {
        AuditLog::factory()->create(['entity_type' => 'story', 'entity_id' => 1]);

        $result = $this->repo->forEntity('story', 1);

        $this->assertEquals(1, $result->total());
    }

    public function test_search_filters_by_actor(): void
    {
        AuditLog::factory()->create(['actor_id' => 1]);
        AuditLog::factory()->create(['actor_id' => 2]);

        $result = $this->repo->search(actorId: 1);

        $this->assertEquals(1, $result->total());
    }

    public function test_search_filters_by_action(): void
    {
        AuditLog::factory()->create(['action' => 'role.created']);
        AuditLog::factory()->create(['action' => 'user.deactivated']);

        $result = $this->repo->search(action: 'role.created');

        $this->assertEquals(1, $result->total());
    }

    public function test_search_filters_by_entity_type(): void
    {
        AuditLog::factory()->create(['entity_type' => 'role']);
        AuditLog::factory()->create(['entity_type' => 'user']);

        $result = $this->repo->search(entityType: 'role');

        $this->assertEquals(1, $result->total());
    }

    public function test_search_filters_by_date_range(): void
    {
        AuditLog::factory()->create(['created_at' => now()->subDays(5)]);
        AuditLog::factory()->create(['created_at' => now()]);

        $result = $this->repo->search(dateFrom: now()->subDay()->toDateString());

        $this->assertEquals(1, $result->total());
    }

    public function test_recent_for_module_returns_limited(): void
    {
        AuditLog::factory()->count(3)->create(['entity_type' => 'story']);

        $result = $this->repo->recentForModule('story', 2);

        $this->assertCount(2, $result);
    }

    public function test_recent_returns_recent_audits(): void
    {
        AuditLog::factory()->count(3)->create();

        $result = $this->repo->recent(2);

        $this->assertCount(2, $result);
    }
}
