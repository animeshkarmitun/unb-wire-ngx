<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\ProcessIndexOutbox)->everyMinute()->withoutOverlapping();
Schedule::call(function(){
    \App\Models\Story::where('embargo_until','<=', now())->where('status','approved')->each(function($s){
        \Illuminate\Support\Facades\DB::transaction(function() use($s){
            $s->update(['status'=>'published','published_at'=>now()]);
            \Illuminate\Support\Facades\DB::table('index_outbox')->insert(['index_name'=>'main','op'=>'upsert','document_id'=>$s->public_id,'status'=>'pending','attempts'=>0,'created_at'=>now()]);
        });
        dispatch(new \App\Jobs\FanoutStory($s->id));
        dispatch(new \App\Jobs\ProcessIndexOutbox());
    });
})->everyMinute()->name('embargo-lift')->withoutOverlapping();
Schedule::call(function(){
    \App\Models\Story::where('status','published')->where('published_at','<', now()->subMonths(12))->chunkById(100, function($stories){
        foreach($stories as $s){
            \Illuminate\Support\Facades\DB::transaction(function() use($s){
                $s->update(['status'=>'archived']);
                \Illuminate\Support\Facades\DB::table('index_outbox')->insert([
                    ['index_name'=>'main','op'=>'delete','document_id'=>$s->public_id,'status'=>'pending','attempts'=>0,'created_at'=>now()],
                    ['index_name'=>'archive','op'=>'upsert','document_id'=>$s->public_id,'status'=>'pending','attempts'=>0,'created_at'=>now()],
                ]);
            });
        }
        dispatch(new \App\Jobs\ProcessIndexOutbox());
    });
})->daily()->name('archive-sweep')->withoutOverlapping();
Schedule::call(function(){
    \Illuminate\Support\Facades\DB::table('upload_sessions')->where('expires_at','<', now())->delete();
})->daily()->name('upload-janitor');
Schedule::command('monitor:outbox-lag')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
