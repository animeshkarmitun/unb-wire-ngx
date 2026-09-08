<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndexOutboxRepository
{
    public function getPendingBatch(int $limit = 50): Collection
    {
        return DB::table('index_outbox')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markProcessing(int $id, int $attempts): void
    {
        DB::table('index_outbox')
            ->where('id', $id)
            ->update(['attempts' => $attempts + 1]);
    }

    public function markDone(int $id): void
    {
        DB::table('index_outbox')
            ->where('id', $id)
            ->update(['status' => 'done', 'processed_at' => now()]);
    }

    public function markFailed(int $id): void
    {
        DB::table('index_outbox')
            ->where('id', $id)
            ->update(['status' => 'failed']);
    }

    public function markPending(int $id): void
    {
        DB::table('index_outbox')
            ->where('id', $id)
            ->update(['status' => 'pending']);
    }

    public function purgeCompleted(int $olderThanDays = 7): void
    {
        DB::table('index_outbox')
            ->where('status', 'done')
            ->where('processed_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }

    public function insert(array $data): void
    {
        DB::table('index_outbox')->insert(array_merge($data, [
            'created_at' => now(),
        ]));
    }

    public function getLagSeconds(): ?int
    {
        $oldest = DB::table('index_outbox')->where('status', 'pending')->min('created_at');

        return $oldest ? (int) abs(now()->diffInSeconds($oldest)) : 0;
    }

    public function getFailedCount(): int
    {
        return DB::table('index_outbox')->where('status', 'failed')->count();
    }
}
