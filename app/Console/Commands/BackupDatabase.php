<?php

namespace App\Console\Commands;

use App\Mail\BackupFailed;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run {--prune-only : Skip the dump and only apply retention} {--force : Run even when backups are disabled}';

    protected $description = 'Dump the database to a gzipped SQL backup, copy it offsite, and apply retention';

    public function handle(): int
    {
        if (! config('backup.enabled') && ! $this->option('force')) {
            $this->info('Backups disabled (BACKUP_ENABLED=false) — skipping.');

            return 0;
        }

        $disks = ['local'];
        $offsite = config('backup.disk');
        if ($offsite && $offsite !== 'local') {
            $disks[] = $offsite;
        }

        try {
            if (! $this->option('prune-only')) {
                $name = $this->store($disks);
                $this->info("Backup stored: backups/{$name}");
            }
            foreach ($disks as $disk) {
                $this->info("Pruned {$this->prune($disk)} old backup(s) on [{$disk}]");
            }
        } catch (\Throwable $e) {
            report($e);
            if ($to = config('backup.alert_email')) {
                Mail::to($to)->send(new BackupFailed($e->getMessage()));
            }
            $this->error('Backup failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function store(array $disks): string
    {
        $sqlPath = tempnam(sys_get_temp_dir(), 'unb-backup-sql-');
        $gzPath = tempnam(sys_get_temp_dir(), 'unb-backup-gz-');

        try {
            $this->dumpToFile($sqlPath);
            $this->gzip($sqlPath, $gzPath);

            $name = 'db-'.now()->format('Ymd-His').'.sql.gz';
            $stream = fopen($gzPath, 'rb');
            try {
                Storage::disk('local')->writeStream('backups/'.$name, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            foreach (array_diff($disks, ['local']) as $disk) {
                $copy = Storage::disk('local')->readStream('backups/'.$name);
                try {
                    Storage::disk($disk)->writeStream('backups/'.$name, $copy);
                } finally {
                    if (is_resource($copy)) {
                        fclose($copy);
                    }
                }
            }

            return $name;
        } finally {
            @unlink($sqlPath);
            @unlink($gzPath);
        }
    }

    protected function dumpToFile(string $sqlPath): void
    {
        $db = config('database.connections.pgsql');
        $host = $db['write']['host'] ?? $db['host'] ?? '127.0.0.1';

        $process = new Process([
            config('backup.pg_dump_bin', 'pg_dump'),
            '--host', (string) $host,
            '--port', (string) ($db['port'] ?? 5432),
            '--username', (string) ($db['username'] ?? 'postgres'),
            '--dbname', (string) ($db['database'] ?? 'unb_wire'),
            '--no-password',
            '--file', $sqlPath,
        ], null, ['PGPASSWORD' => (string) ($db['password'] ?? '')]);
        $process->setTimeout(3600);
        $process->mustRun();
    }

    private function gzip(string $src, string $dest): void
    {
        $in = fopen($src, 'rb');
        $out = gzopen($dest, 'wb6');
        try {
            while (! feof($in)) {
                gzwrite($out, fread($in, 1024 * 1024));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    private function prune(string $disk): int
    {
        $entries = collect(Storage::disk($disk)->files('backups'))
            ->map(function ($f) {
                if (! preg_match('/db-(\d{8}-\d{6})\.sql\.gz$/', basename($f), $m)) {
                    return null;
                }

                return ['path' => $f, 'time' => Carbon::createFromFormat('Ymd-His', $m[1])];
            })
            ->filter()
            ->sortByDesc(fn ($e) => $e['time']->timestamp)
            ->values();

        $dailyCutoff = now()->subDays(config('backup.retention_days', 7));
        $weeklyCutoff = now()->startOfWeek()->subWeeks(3);
        $monthlyCutoff = now()->startOfMonth()->subMonthsNoOverflow(2);

        $keep = collect();
        foreach ($entries as $e) {
            if ($e['time']->gte($dailyCutoff)) {
                $keep->push($e['path']);
            }
        }
        foreach ($entries->filter(fn ($e) => $e['time']->gte($weeklyCutoff))->groupBy(fn ($e) => $e['time']->format('o-W')) as $week) {
            $keep->push($week->first()['path']);
        }
        foreach ($entries->filter(fn ($e) => $e['time']->gte($monthlyCutoff))->groupBy(fn ($e) => $e['time']->format('Y-m')) as $month) {
            $keep->push($month->first()['path']);
        }

        $deleted = 0;
        foreach ($entries as $e) {
            if (! $keep->contains($e['path'])) {
                Storage::disk($disk)->delete($e['path']);
                $deleted++;
            }
        }

        return $deleted;
    }
}
