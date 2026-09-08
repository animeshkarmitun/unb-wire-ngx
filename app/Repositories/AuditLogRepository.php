<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogRepository
{
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $diff = [],
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $correlationId = null,
    ): void {
        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff' => ! empty($diff) ? json_encode($diff) : null,
            'ip' => $ip ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'correlation_id' => $correlationId ?? request()?->header('X-Correlation-Id') ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    public function forEntity(string $entityType, int|string $entityId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function search(
        ?int $actorId = null,
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 25,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $q = AuditLog::query();
        if ($actorId) {
            $q->where('actor_id', $actorId);
        }
        if ($action) {
            $q->where('action', $action);
        }
        if ($entityType) {
            $q->where('entity_type', $entityType);
        }
        if ($entityId) {
            $q->where('entity_id', $entityId);
        }
        if ($dateFrom) {
            $q->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $q->where('created_at', '<=', $dateTo);
        }

        return $q->orderByDesc('created_at')->paginate($perPage);
    }

    public function recentForModule(string $module, int $limit = 15): Collection
    {
        return AuditLog::where('entity_type', $module)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function recent(int $limit = 15): Collection
    {
        return DB::table('audit_logs')->orderByDesc('created_at')->limit($limit)->get();
    }
}
