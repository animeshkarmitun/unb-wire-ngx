<?php

namespace App\Console\Commands;

use App\Support\DeliveryPartitions;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureDeliveryPartitions extends Command
{
    protected $signature = 'partitions:ensure {--months=1 : months to create ahead of the current month}';

    protected $description = 'Ensure monthly deliveries partitions exist (current month + N ahead; default-partition-safe attach)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->info('Non-pgsql driver — nothing to do');

            return self::SUCCESS;
        }

        $ahead = max(0, (int) $this->option('months'));
        $created = [];
        $cursor = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        for ($i = 0; $i <= $ahead; $i++) {
            $ym = $cursor->format('Ym');
            if (! DeliveryPartitions::partitionExists($ym)) {
                DeliveryPartitions::ensureMonth($cursor);
                $created[] = $ym;
            }
            $cursor = $cursor->add(new DateInterval('P1M'));
        }

        $this->info($created === [] ? 'All deliveries partitions present' : 'Created partitions: '.implode(', ', $created));

        return self::SUCCESS;
    }
}
