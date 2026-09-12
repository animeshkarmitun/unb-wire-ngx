<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessIndexOutbox)->everyMinute()->withoutOverlapping();
Schedule::call(function () {
    Story::where('embargo_until', '<=', now())->where('status', 'approved')->cursor()->each(function ($s) {
        DB::transaction(function () use ($s) {
            $s->update(['status' => 'published', 'published_at' => now()]);
            DB::table('index_outbox')->insert(['index_name' => 'main', 'op' => 'upsert', 'document_id' => $s->public_id, 'status' => 'pending', 'attempts' => 0, 'created_at' => now()]);
            Cache::forget('portal:feed:*');
            Cache::forget('feed:v1:*');
        });
        dispatch(new FanoutStory($s->id));
        dispatch(new ProcessIndexOutbox);
    });
})->everyMinute()->name('embargo-lift')->withoutOverlapping();
Schedule::call(function () {
    Story::where('status', 'published')->where('published_at', '<', now()->subMonths(12))->chunkById(100, function ($stories) {
        foreach ($stories as $s) {
            DB::transaction(function () use ($s) {
                $s->update(['status' => 'archived']);
                DB::table('index_outbox')->insert([
                    ['index_name' => 'main', 'op' => 'delete', 'document_id' => $s->public_id, 'status' => 'pending', 'attempts' => 0, 'created_at' => now()],
                    ['index_name' => 'archive', 'op' => 'upsert', 'document_id' => $s->public_id, 'status' => 'pending', 'attempts' => 0, 'created_at' => now()],
                ]);
            });
        }
        dispatch(new ProcessIndexOutbox);
    });
})->daily()->name('archive-sweep')->withoutOverlapping();
Schedule::call(function () {
    DB::table('upload_sessions')->where('expires_at', '<', now())->delete();
})->daily()->name('upload-janitor');
Schedule::command('monitor:outbox-lag')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('delivery:process')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
