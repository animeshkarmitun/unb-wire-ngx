<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupList extends Command
{
    protected $signature = 'backup:list {disk=local : Disk to list backups from}';

    protected $description = 'List stored database backups (newest first)';

    public function handle(): int
    {
        $disk = $this->argument('disk');

        $rows = collect(Storage::disk($disk)->files('backups'))
            ->filter(fn ($f) => (bool) preg_match('/db-(\d{8}-\d{6})\.sql\.gz$/', basename($f)))
            ->map(function ($f) use ($disk) {
                preg_match('/db-(\d{8}-\d{6})\.sql\.gz$/', basename($f), $m);

                return [
                    basename($f),
                    $this->humanSize(Storage::disk($disk)->size($f)),
                    Carbon::createFromFormat('Ymd-His', $m[1])->format('Y-m-d H:i:s'),
                ];
            })
            ->sortByDesc(fn ($r) => $r[0])
            ->values()
            ->all();

        if (! $rows) {
            $this->info("No backups on [{$disk}].");

            return 0;
        }

        $this->table(['Backup', 'Size', 'Created'], $rows);

        return 0;
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
