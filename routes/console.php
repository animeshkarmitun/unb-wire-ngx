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
        $s->update(['status'=>'published','published_at'=>now()]);
        dispatch(new \App\Jobs\FanoutStory($s->id));
    });
})->everyMinute()->name('embargo-lift');
Schedule::call(function(){
    \App\Models\Story::where('status','published')->where('published_at','<', now()->subMonths(12))->update(['status'=>'archived']);
})->daily()->name('archive-sweep');
Schedule::call(function(){
    \Illuminate\Support\Facades\DB::table('upload_sessions')->where('expires_at','<', now())->delete();
})->daily()->name('upload-janitor');
