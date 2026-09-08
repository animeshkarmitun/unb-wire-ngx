<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditQueryService
{
    public function __construct(
        private AuditLogRepository $repo,
    ) {}

    public function forEntity(string $entityType, int|string $entityId, int $perPage = 25): LengthAwarePaginator
    {
        return $this->repo->forEntity($entityType, $entityId, $perPage);
    }

    public function search(
        ?int $actorId = null,
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return $this->repo->search($actorId, $action, $entityType, $entityId, $dateFrom, $dateTo, $perPage);
    }
}
