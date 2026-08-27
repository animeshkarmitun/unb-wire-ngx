<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessIndexOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public function __construct(){ $this->onQueue('outbox'); }

    public function handle(): void
    {
        $rows = DB::table('index_outbox')->where('status','pending')->orderBy('id')->limit(50)->get();
        foreach($rows as $row){
            try {
                DB::table('index_outbox')->where('id',$row->id)->update(['attempts'=> $row->attempts+1]);
                if(env('MEILISEARCH_HOST')){
                    $host = env('MEILISEARCH_HOST');
                    $key = env('MEILISEARCH_KEY');
                    Http::withHeaders(['Authorization'=>"Bearer {$key}"])->post("{$host}/indexes/{$row->index_name}/documents", [$row->document_id]);
                }
                DB::table('index_outbox')->where('id',$row->id)->update(['status'=>'done','processed_at'=>now()]);
            } catch (\Throwable $e){
                Log::error('index_outbox failed', ['id'=>$row->id,'error'=>$e->getMessage()]);
                DB::table('index_outbox')->where('id',$row->id)->update(['status'=>'failed']);
            }
        }
        DB::table('index_outbox')->where('status','done')->where('processed_at','<', now()->subDays(7))->delete();
    }
}
