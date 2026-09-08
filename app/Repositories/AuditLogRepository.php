<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditLogRepository
{
    public function log(string $action, string $entityType, ?int $entityId, array $diff): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff' => json_encode($diff),
            'created_at' => now(),
        ]);
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
