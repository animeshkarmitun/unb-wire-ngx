<?php

namespace App\Services;

use App\Models\AiGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiService
{
    public function call(string $kind, array $payload, int $userId, ?int $storyId = null): array
    {
        $settings = DB::table('settings')->where('key','ai.desk')->first();
        $cfg = $settings ? (is_string($settings->value) ? json_decode($settings->value, true) : $settings->value) : [];
        if(!empty($cfg['killed'])){
            return ['error'=>'AI kill switch is ON'];
        }
        $deskKey = ($payload['language'] ?? 'en') === 'bn' ? 'preeditBn' : 'preeditEn';
        if(isset($cfg[$deskKey]) && ! $cfg[$deskKey]){
            return ['error'=>'AI disabled for this desk'];
        }
        $cap = (int)($cfg['monthlyCap'] ?? 500000);
        $monthStart = now()->startOfMonth()->toDateString();
        $used = (int) DB::table('ai_token_usage_daily')->where('date','>=',$monthStart)->sum('tokens');
        if($used >= $cap){
            return ['error'=>'Monthly AI token budget exceeded'];
        }

        $pack = match($kind){
            'preedit' => ['headline'=>$payload['headline']??'AI: '.$payload['text']??Str::limit($payload['text']??'',60).' — polished','brief'=>'AI brief — '.$payload['text']??'','category'=>['name'=>'Business'],'tags'=>['economy','bangladesh'],'body'=>'<p>AI polished body for: '.e($payload['text']??'').'</p>'],
            'tags' => ['category'=>['name'=>'Sports'],'tags'=>['cricket','world-cup']],
            'translate' => ['headline'=>'বাংলা শিরোনাম','body'=>'<p>বাংলা অনুবাদ</p>'],
            'generate' => ['headline'=>'AI Generated headline','brief'=>'AI brief','body'=>'<p>AI generated body from raw: '.e($payload['text']??'').'</p>','category'=>['name'=>'Bangladesh'],'tags'=>['breaking']],
            default => ['note'=>'unknown kind'],
        };

        AiGeneration::create([
            'story_id'=>$storyId,'user_id'=>$userId,'kind'=>$kind,'prompt_version'=>'v1','model'=>$cfg['model']??'stub','input_hash'=>hash('sha256', json_encode($payload)),'pack'=>$pack,'new_facts'=>null,'tokens_in'=>100,'tokens_out'=>200,'cost_micros'=>1000,'applied'=>null,'created_at'=>now(),
        ]);

        DB::table('ai_token_usage_daily')->upsert([
            'date'=>now()->toDateString(),'scope'=>'desk:en','kind'=>$kind,'tokens'=>300,'cost_micros'=>1000,
        ],['date','scope','kind'],['tokens'=>DB::raw('ai_token_usage_daily.tokens + 300'),'cost_micros'=>DB::raw('ai_token_usage_daily.cost_micros + 1000')]);

        return $pack;
    }
}
