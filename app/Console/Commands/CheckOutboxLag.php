<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIndexOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckOutboxLag extends Command
{
    protected $signature = 'monitor:outbox-lag';

    protected $description = 'Alert if index_outbox lag >30s or DLQ >0';

    public function handle(): int
    {
        $lag = ProcessIndexOutbox::lagSeconds();
        $failed = DB::table('index_outbox')->where('status', 'failed')->count();
        $dlq = DB::table('deliveries')->where('status', 'failed')->count();
        if ($lag > 30 || $failed > 0 || $dlq > 0) {
            Log::warning('outbox lag alert', compact('lag', 'failed', 'dlq'));
            $this->warn("LAG={$lag}s FAILED_OUTBOX={$failed} DLQ={$dlq}");

            return 1;
        }
        $this->info("OK lag={$lag}s");

        return 0;
    }
}
