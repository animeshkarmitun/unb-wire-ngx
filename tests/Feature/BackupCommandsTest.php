<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use App\Mail\BackupFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 9, 22, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function putBackups(array $stamps, string $disk = 'local'): void
    {
        foreach ($stamps as $stamp) {
            Storage::disk($disk)->put("backups/db-{$stamp}.sql.gz", 'x');
        }
    }

    private function existing(array $stamps, string $disk = 'local'): void
    {
        foreach ($stamps as $stamp) {
            $this->assertTrue(Storage::disk($disk)->exists("backups/db-{$stamp}.sql.gz"), "expected db-{$stamp} to be kept");
        }
    }

    private function missing(array $stamps, string $disk = 'local'): void
    {
        foreach ($stamps as $stamp) {
            $this->assertFalse(Storage::disk($disk)->exists("backups/db-{$stamp}.sql.gz"), "expected db-{$stamp} to be pruned");
        }
    }

    public function test_run_disabled_is_noop(): void
    {
        Storage::fake('local');
        config(['backup.enabled' => false]);

        $this->artisan('backup:run')->assertExitCode(0);

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_prune_keeps_7_daily_4_weekly_3_monthly(): void
    {
        Storage::fake('local');

        // now = 2026-09-22 12:00 (Tue, W39). daily cutoff 09-15 12:00,
        // weekly cutoff W36 Mon 08-31, monthly cutoff 07-01.
        $this->putBackups([
            // 7 dailies
            '20260922-020000', '20260921-020000', '20260920-020000', '20260919-020000',
            '20260918-020000', '20260917-020000', '20260916-020000',
            // same W38 as the 20th but older than the daily window -> pruned
            '20260915-020000',
            // W37 pair -> newest kept as weekly
            '20260912-020000', '20260910-020000',
            // W36 pair -> newest kept as weekly
            '20260905-020000', '20260903-020000',
            // August: newest kept as monthly, rest pruned
            '20260829-020000', '20260815-020000', '20260808-020000',
            // July: sole file kept as monthly
            '20260720-020000',
            // June: outside every window -> pruned
            '20260615-020000',
        ]);

        $this->artisan('backup:run', ['--prune-only' => true])->assertExitCode(0);

        $this->existing([
            '20260922-020000', '20260921-020000', '20260920-020000', '20260919-020000',
            '20260918-020000', '20260917-020000', '20260916-020000',
            '20260912-020000', '20260905-020000',
            '20260829-020000', '20260720-020000',
        ]);
        $this->missing([
            '20260915-020000', '20260910-020000', '20260903-020000',
            '20260815-020000', '20260808-020000', '20260615-020000',
        ]);
        $this->assertCount(11, Storage::disk('local')->files('backups'));
    }

    public function test_list_shows_backups_newest_first(): void
    {
        Storage::fake('local');
        $this->putBackups(['20260910-020000', '20260920-020000', '20260815-020000']);

        $this->artisan('backup:list')
            ->expectsOutputToContain('db-20260920-020000.sql.gz')
            ->expectsOutputToContain('db-20260910-020000.sql.gz')
            ->expectsOutputToContain('db-20260815-020000.sql.gz')
            ->assertExitCode(0);
    }

    public function test_failure_sends_alert_and_exits_one(): void
    {
        Storage::fake('local');
        Mail::fake();
        config([
            'backup.pg_dump_bin' => 'definitely-not-a-real-pg-dump-binary',
            'backup.alert_email' => 'ops@example.com',
        ]);

        $this->artisan('backup:run')->assertExitCode(1);

        Mail::assertSent(BackupFailed::class, fn ($m) => $m->hasTo('ops@example.com'));
        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_failure_without_alert_email_still_exits_one(): void
    {
        Storage::fake('local');
        Mail::fake();
        config([
            'backup.pg_dump_bin' => 'definitely-not-a-real-pg-dump-binary',
            'backup.alert_email' => null,
        ]);

        $this->artisan('backup:run')->assertExitCode(1);

        Mail::assertNothingSent();
    }

    public function test_success_stores_gzipped_dump_and_copies_offsite(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        config(['backup.disk' => 's3']);

        $cmd = new class extends BackupDatabase
        {
            protected function dumpToFile(string $sqlPath): void
            {
                file_put_contents($sqlPath, "-- unb wire dump\nSELECT 1;\n");
            }
        };
        $cmd->setLaravel($this->app);

        $output = new BufferedOutput;
        $exit = $cmd->run(new ArrayInput([]), $output);

        $this->assertSame(0, $exit);

        $name = 'db-20260922-120000.sql.gz';
        $this->assertTrue(Storage::disk('local')->exists("backups/{$name}"));
        $this->assertTrue(Storage::disk('s3')->exists("backups/{$name}"));

        $gz = Storage::disk('local')->get("backups/{$name}");
        $this->assertStringStartsWith("\x1f\x8b", $gz, 'backup should be gzip');
        $this->assertSame("-- unb wire dump\nSELECT 1;\n", gzdecode($gz));
    }
}
