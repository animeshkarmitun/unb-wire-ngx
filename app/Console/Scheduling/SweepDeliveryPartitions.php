<?php

namespace App\Console\Scheduling;

use App\Support\DeliveryPartitions;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * NFR §4 archival sweep for deliveries: monthly partitions whose entire range is
 * older than the retention window (default 12 months) are detached from the hot
 * parent and moved to the `archive` schema — keeping the distribution log UI and
 * idempotency lookups lean (DEC-018).
 */
class SweepDeliveryPartitions
{
    public function handle(int $keepMonths = 12): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS archive');

        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('first day of this month')
            ->modify("-{$keepMonths} months");

        $swept = [];
        $partitions = DB::select(
            "SELECT c.relname AS name
             FROM pg_inherits i
             JOIN pg_class c ON c.oid = i.inhrelid
             WHERE i.inhparent = 'deliveries'::regclass"
        );

        foreach ($partitions as $p) {
            $month = DeliveryPartitions::monthFromName($p->name);
            if ($month === null) {
                continue; // deliveries_default — never swept
            }

            [$from, $to] = DeliveryPartitions::bounds($month);
            $toMonth = new DateTimeImmutable(substr($to, 0, 10), new DateTimeZone('UTC'));
            if ($toMonth > $cutoff) {
                continue; // month still inside the retention window
            }

            DB::statement("ALTER TABLE deliveries DETACH PARTITION {$p->name}");
            DB::statement("ALTER TABLE {$p->name} SET SCHEMA archive");
            $swept[] = $p->name;
        }

        return $swept;
    }
}
