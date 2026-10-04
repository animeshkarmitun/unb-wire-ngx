<?php

namespace App\Console\Scheduling;

use Illuminate\Support\Facades\DB;

class PruneUploadSessions
{
    public function handle(): int
    {
        return DB::table('upload_sessions')->where('expires_at', '<', now())->delete();
    }
}
