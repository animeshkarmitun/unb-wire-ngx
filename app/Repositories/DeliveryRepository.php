<?php

namespace App\Repositories;

use App\Models\Delivery;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DeliveryRepository
{
    // ─── Read Methods ───────────────────────────────────────────

    public function paginateWithFilters(string $status, string $search, int $perPage = 20): LengthAwarePaginator
    {
        $q = Delivery::with(['client', 'channel'])->orderByDesc('created_at');
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($search !== '') {
            $q->where('payload_hash', 'like', '%'.$search.'%');
        }

        return $q->paginate($perPage);
    }

    public function getDistributionCounts(): array
    {
        return [
            'total' => Delivery::count(),
            'failed' => Delivery::where('status', 'failed')->count(),
            'delivered' => Delivery::where('status', 'delivered')->count(),
        ];
    }

    public function failedCount(): int
    {
        return Delivery::where('status', 'failed')->count();
    }

    public function dlqCount(): int
    {
        return DB::table('deliveries')->where('status', 'failed')->count();
    }

    public function recentCount(Carbon $since): int
    {
        return Delivery::where('created_at', '>=', $since)->count();
    }

    public function recentDeliveredCount(Carbon $since): int
    {
        return Delivery::where('created_at', '>=', $since)->where('status', 'delivered')->count();
    }

    public function countBetween(Carbon $from, Carbon $to): int
    {
        return Delivery::whereBetween('created_at', [$from, $to])->count();
    }

    public function deliveredCountBetween(Carbon $from, Carbon $to): int
    {
        return Delivery::whereBetween('created_at', [$from, $to])->where('status', 'delivered')->count();
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function createQueued(array $data): void
    {
        DB::table('deliveries')->insert(array_merge($data, [
            'status' => 'queued',
            'attempt_count' => 0,
            'created_at' => now(),
        ]));
    }

    public function markSent(string $idempotencyKey): void
    {
        DB::table('deliveries')
            ->where('idempotency_key', $idempotencyKey)
            ->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function markQueued(int $id): void
    {
        Delivery::where('id', $id)->update(['status' => 'queued', 'attempt_count' => 0]);
    }
}
