<?php

use App\Models\Story;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/portal')->group(function () {
    Route::post('/search-token', function (Request $request) {
        $entitlement = ['languages' => ['en', 'bn'], 'category_ids' => null, 'media_kinds' => ['photo']];
        $filter = 'language IN [en, bn]';
        return response()->json([
            'token' => base64_encode(json_encode(['filter' => $filter, 'exp' => now()->addMinutes(30)->timestamp])),
            'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
            'index' => 'main',
            'filter' => $filter,
            'entitlement' => $entitlement,
        ]);
    });

    Route::get('/feed', function (Request $request) {
        $stories = Story::with('category')->where('status', 'published')->orderByDesc('published_at')->limit(20)->get()->map(fn($s)=>[
            'public_id' => $s->public_id, 'headline' => $s->headline, 'sub_head' => $s->sub_head, 'brief' => $s->brief, 'body_html' => $s->body_html,
            'category' => $s->category->name_en ?? '—', 'language' => $s->language, 'published_at' => $s->published_at, 'status' => $s->status, 'is_breaking' => $s->is_breaking,
        ]);
        return response()->json(['data' => $stories]);
    });

    Route::get('/story/{publicId}', function (string $publicId) {
        $s = Story::with('category')->where('public_id', $publicId)->where('status','published')->firstOrFail();
        return response()->json(['data' => [
            'public_id'=>$s->public_id,'headline'=>$s->headline,'sub_head'=>$s->sub_head,'brief'=>$s->brief,'body_html'=>$s->body_html,'category'=>$s->category->name_en ?? '—','language'=>$s->language,'published_at'=>$s->published_at,'status'=>$s->status,
        ]]);
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
