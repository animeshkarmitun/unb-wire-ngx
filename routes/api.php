<?php

use App\Models\ClientApiKey;
use App\Models\Story;
use App\Services\AiService;
use App\Services\Search\TenantTokenIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/portal')->group(function () {
    Route::post('/search-token', function (Request $request, TenantTokenIssuer $issuer) {
        $client = null;
        $raw = $request->bearerToken() ?? $request->header('X-API-Key');
        if ($raw) {
            $hash = hash('sha256', $raw);
            $key = ClientApiKey::where('key_hash', $hash)->whereNull('revoked_at')->first();
            if ($key && (! $key->expires_at || $key->expires_at->isFuture())) {
                $key->update(['last_used_at' => now()]);
                $client = $key->client;
            }
        }
        $data = $issuer->issueFor($client);
        return response()->json($data);
    })->middleware('throttle:60,1');

    Route::get('/feed', function (Request $request) {
        $key = 'portal:feed:'.md5($request->fullUrl());
        $stories = \Illuminate\Support\Facades\Cache::remember($key, 60, fn()=> Story::with('category')->where('status', 'published')->orderByDesc('published_at')->limit(20)->get()->map(fn($s)=>[
            'public_id' => $s->public_id, 'headline' => $s->headline, 'sub_head' => $s->sub_head, 'brief' => $s->brief, 'body_html' => $s->body_html,
            'category' => $s->category->name_en ?? '—', 'language' => $s->language, 'published_at' => $s->published_at?->toIso8601String(), 'status' => $s->status, 'is_breaking' => $s->is_breaking,
        ]));
        return response()->json(['data' => $stories])->header('Cache-Control','public, max-age=60');
    });

    Route::get('/story/{publicId}', function (string $publicId) {
        $s = Story::with('category')->where('public_id', $publicId)->where('status','published')->firstOrFail();
        return response()->json(['data' => [
            'public_id'=>$s->public_id,'headline'=>$s->headline,'sub_head'=>$s->sub_head,'brief'=>$s->brief,'body_html'=>$s->body_html,'category'=>$s->category->name_en ?? '—','language'=>$s->language,'published_at'=>$s->published_at?->toIso8601String(),'status'=>$s->status,
        ]]);
    });
});

Route::prefix('v1')->group(function(){
    Route::get('/feed', function (Request $request) {
        $since = $request->query('since');
        $q = Story::with('category')->where('status','published');
        if($since){
            try { $dt = \Carbon\Carbon::parse(base64_decode($since)); $q->where('published_at','>', $dt); } catch (\Throwable $e) {}
        }
        $stories = $q->orderByDesc('published_at')->limit(min(50, (int)$request->query('limit', 20)))->get()->map(fn($s)=>[
            'public_id'=>$s->public_id,'headline'=>$s->headline,'brief'=>$s->brief,'language'=>$s->language,'published_at'=>$s->published_at?->toIso8601String(),'is_breaking'=>$s->is_breaking,
        ]);
        $cursor = $stories->last() ? base64_encode($stories->last()['published_at']) : null;
        return response()->json(['data'=>$stories,'cursor'=>$cursor]);
    })->middleware('client.api:feed:read');
});

Route::middleware('auth')->group(function () {
    Route::post('/uploads', [\App\Http\Controllers\TusController::class, 'create']);
    Route::patch('/uploads/{id}', [\App\Http\Controllers\TusController::class, 'patch']);
    Route::match(['head'], '/uploads/{id}', [\App\Http\Controllers\TusController::class, 'head']);
    Route::get('/media/{id}/presigned', function (string $id, \App\Services\Media\PresignedUrlService $svc) {
        $asset = \App\Models\MediaAsset::where('public_id', $id)->firstOrFail();
        $url = $svc->forAsset($asset, request('variant', 'original'));
        $svc->recordDownload($asset, null, null, request('variant', 'original'));
        return response()->json(['url' => $url, 'expires_in' => 300]);
    });
});

Route::middleware('auth:sanctum')->post('/ai/{kind}', function (string $kind, Request $request, AiService $svc) {
    $request->validate(['text'=>'nullable|string','story_id'=>'nullable|integer']);
    $pack=$svc->call($kind, $request->all(), $request->user()->id, $request->input('story_id'));
    return response()->json(['pack'=>$pack]);
});
Route::middleware('auth')->post('/ai/{kind}', function (string $kind, Request $request, AiService $svc) {
    $pack=$svc->call($kind, $request->all(), auth()->id(), $request->input('story_id'));
    return response()->json(['pack'=>$pack]);
});
