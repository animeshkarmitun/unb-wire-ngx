<?php

use App\Console\Scheduling\LiftEmbargoedStories;
use App\Console\Scheduling\PruneUploadSessions;
use App\Console\Scheduling\SweepArchivedStories;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(PruneUploadSessions::class)->handle())
    ->daily()
    ->name('upload-janitor')
    ->withoutOverlapping();

Schedule::call(fn () => app(LiftEmbargoedStories::class)->handle())
    ->everyMinute()
    ->name('embargo-lift')
    ->withoutOverlapping();

Schedule::call(fn () => app(SweepArchivedStories::class)->handle())
    ->daily()
    ->name('archive-sweep')
    ->withoutOverlapping();

Schedule::command('monitor:outbox-lag')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('delivery:process')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('backup:run')->dailyAt((string) config('backup.schedule', '02:00'))->withoutOverlapping()->name('db-backup');
