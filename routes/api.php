<?php

use App\Http\Requests\AiAssistRequest;
use App\Http\Requests\FeedRequest;
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
        $stories = \Illuminate\Support\Facades\Cache::remember($key, 60, fn()=> Story::with('category')->where('status', 'published')->orderByDesc('published_at')->orderByDesc('id')->limit(20)->get()->map(fn($s)=>[
            'public_id' => $s->public_id, 'headline' => $s->headline, 'sub_head' => $s->sub_head, 'brief' => $s->brief, 'body_html' => $s->body_html,
            'category' => $s->category->name_en ?? '—', 'language' => $s->language, 'published_at' => $s->published_at?->toIso8601String(), 'status' => $s->status, 'is_breaking' => $s->is_breaking,
        ]));
        return response()->json(['data' => $stories])->header('Cache-Control','public, max-age=60');
    })->middleware('throttle:60,1');

    Route::get('/story/{publicId}', function (string $publicId) {
        $s = Story::with('category')->where('public_id', $publicId)->where('status','published')->firstOrFail();
        return response()->json(['data' => [
            'public_id'=>$s->public_id,'headline'=>$s->headline,'sub_head'=>$s->sub_head,'brief'=>$s->brief,'body_html'=>$s->body_html,'category'=>$s->category->name_en ?? '—','language'=>$s->language,'published_at'=>$s->published_at?->toIso8601String(),'status'=>$s->status,
        ]]);
    })->middleware('throttle:120,1');
});

Route::prefix('v1')->group(function(){
    Route::get('/feed', function (FeedRequest $request) {
        $validated = $request->validated();
        $since = $validated['since'] ?? null;
        $limit = min(50, (int)($validated['limit'] ?? 20));
        $q = Story::with('category')->where('status','published');
        if($since){
            try {
                $decoded = base64_decode($since, true);
                if ($decoded && str_contains($decoded, '|')) { [$ts,$id] = explode('|', $decoded, 2); $dt = \Carbon\Carbon::parse($ts); $q->where(fn($qq)=> $qq->where('published_at','<',$dt)->orWhere(fn($q2)=> $q2->where('published_at',$dt)->where('id','<',$id))); }
                else { $dt = \Carbon\Carbon::parse(base64_decode($since)); $q->where('published_at','>', $dt); }
            } catch (\Throwable $e) {}
        }
        $cacheKey = 'feed:v1:'.md5($request->fullUrl());
        $payload = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function() use ($q, $limit) {
            $stories = (clone $q)->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()->map(fn($s)=>[
                'public_id'=>$s->public_id,'headline'=>$s->headline,'brief'=>$s->brief,'language'=>$s->language,'published_at'=>$s->published_at?->toIso8601String(),'is_breaking'=>$s->is_breaking,
            ]);
            $last = $stories->last();
            $cursor = $last ? base64_encode($last['published_at'].'|'.$last['public_id']) : null;
            return ['data'=>$stories,'cursor'=>$cursor];
        });
        return response()->json($payload)->header('Cache-Control','public, max-age=60');
    })->middleware(['client.api:feed:read','throttle:60,1']);
});

Route::middleware(['auth','throttle:60,1'])->group(function () {
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

Route::middleware('auth:sanctum')->post('/ai/{kind}', function (string $kind, AiAssistRequest $request, AiService $svc) {
    $pack=$svc->call($kind, $request->validated(), $request->user()->id, $request->input('story_id'));
    return response()->json(['pack'=>$pack]);
})->middleware('throttle:30,1');
Route::middleware('auth')->post('/ai/{kind}', function (string $kind, AiAssistRequest $request, AiService $svc) {
    $pack=$svc->call($kind, $request->validated(), auth()->id(), $request->input('story_id'));
    return response()->json(['pack'=>$pack]);
})->middleware('throttle:30,1');
