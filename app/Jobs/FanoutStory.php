<?php

namespace App\Jobs;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FanoutStory implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(public int $storyId) { $this->onQueue('fanout'); }

    public function handle(): void
    {
        $story = Story::find($this->storyId);
        if(!$story || $story->status!=='published') return;

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($isSqlite) {
            $raw = DB::table('client_packages')
                ->join('packages','packages.id','=','client_packages.package_id')
                ->where('client_packages.status','active')
                ->select('client_packages.client_id','packages.entitlement_filter')->get();
            $clients = $raw->filter(function ($r) use ($story) {
                $f = is_string($r->entitlement_filter) ? json_decode($r->entitlement_filter, true) : $r->entitlement_filter;
                $langs = $f['languages'] ?? [];
                return in_array($story->language, (array) $langs, true);
            })->values();
        } else {
            $clients = DB::table('client_packages')
                ->join('packages','packages.id','=','client_packages.package_id')
                ->where('client_packages.status','active')
                ->where(function($q) use($story){
                    $q->whereRaw("(packages.entitlement_filter->>'languages')::jsonb ? ?", [$story->language]);
                })
                ->select('client_packages.client_id','packages.entitlement_filter')->get();
        }

        foreach($clients as $row){
            $channels = DB::table('client_channels')->where('client_id',$row->client_id)->where('status','active')->get();
            foreach($channels as $ch){
                $key = hash('sha256', $story->id.'-'.$ch->id.'-'.$story->version);
                $payloadHash = hash('sha256', $story->body_text ?? '');
                try {
                    DB::table('deliveries')->insert([
                        'deliverable_type'=>'story','deliverable_id'=>$story->id,'client_id'=>$row->client_id,'channel_id'=>$ch->id,'status'=>'queued','attempt_count'=>0,'idempotency_key'=>$key,'payload_hash'=>$payloadHash,'created_at'=>now(),
                    ]);
                    $config = is_string($ch->config) ? json_decode($ch->config,true) : $ch->config;
                    if($ch->type==='webhook' && !empty($config['url'])){
                        Http::timeout(5)->post($config['url'], ['public_id'=>$story->public_id,'headline'=>$story->headline]);
                        DB::table('deliveries')->where('idempotency_key',$key)->update(['status'=>'sent','sent_at'=>now()]);
                    }
                    DB::table('client_channels')->where('id',$ch->id)->update(['last_success_at'=>now(),'failure_count'=>0]);
                } catch (\Throwable $e){
                    if(str_contains($e->getMessage(),'duplicate') || str_contains($e->getMessage(),'Unique')) continue;
                    Log::warning('fanout failed', ['client'=>$row->client_id,'channel'=>$ch->id,'error'=>$e->getMessage()]);
                    DB::table('client_channels')->where('id',$ch->id)->increment('failure_count');
                    $failures = DB::table('client_channels')->where('id',$ch->id)->value('failure_count');
                    if($failures >= 5){
                        DB::table('client_channels')->where('id',$ch->id)->update(['status'=>'paused']);
                    }
                }
            }
        }
    }
}
