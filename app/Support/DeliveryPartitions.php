<?php

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Monthly RANGE partition helpers for the deliveries table (DEC-018).
 * All bounds are UTC — immune to server/session timezone drift.
 */
class DeliveryPartitions
{
    /** Partition table name for a month, e.g. deliveries_202611. */
    public static function name(DateTimeImmutable $month): string
    {
        return 'deliveries_'.$month->format('Ym');
    }

    /**
     * Inclusive-from / exclusive-to UTC bounds covering the whole month.
     *
     * @return array{0: string, 1: string}
     */
    public static function bounds(DateTimeImmutable $month): array
    {
        if ($month->format('d') !== '01' || $month->format('H:i:s') !== '00:00:00') {
            $month = new DateTimeImmutable($month->format('Y-m-01 00:00:00'), new DateTimeZone('UTC'));
        } else {
            $month = $month->setTimezone(new DateTimeZone('UTC'));
        }

        return [
            $month->format('Y-m-d H:i:s+00'),
            $month->add(new DateInterval('P1M'))->format('Y-m-d H:i:s+00'),
        ];
    }

    /**
     * Month (first-day-UTC) from a partition table name, e.g. deliveries_202611.
     */
    public static function monthFromName(string $name): ?DateTimeImmutable
    {
        if (! preg_match('/^deliveries_(\d{4})(\d{2})$/', $name, $m)) {
            return null;
        }

        try {
            return new DateTimeImmutable("{$m[1]}-{$m[2]}-01 00:00:00", new DateTimeZone('UTC'));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function partitionExists(string $ym): bool
    {
        return DB::selectOne("SELECT to_regclass('public.deliveries_{$ym}') AS reg")?->reg !== null;
    }

    /**
     * DEFAULT-partition-safe attach: rows already sitting in deliveries_default for this
     * month (e.g. the ensure schedule was down) block a direct PARTITION OF create, so
     * create standalone, move strays, then attach. No-op safe only via partitionExists().
     */
    public static function ensureMonth(DateTimeImmutable $month): void
    {
        [$from, $to] = self::bounds($month);
        $name = self::name($month);

        DB::transaction(function () use ($name, $from, $to) {
            // INCLUDING CONSTRAINTS — ATTACH validates the child carries the parent's CHECKs
            DB::statement("CREATE TABLE {$name} (LIKE deliveries INCLUDING DEFAULTS INCLUDING CONSTRAINTS)");
            DB::statement("INSERT INTO {$name} SELECT * FROM deliveries_default WHERE created_at >= '{$from}' AND created_at < '{$to}'");
            DB::statement("DELETE FROM deliveries_default WHERE created_at >= '{$from}' AND created_at < '{$to}'");
            DB::statement("ALTER TABLE deliveries ATTACH PARTITION {$name} FOR VALUES FROM ('{$from}') TO ('{$to}')");
        });
    }
}
