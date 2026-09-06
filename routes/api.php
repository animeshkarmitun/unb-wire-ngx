<?php

use App\Http\Controllers\TusController;
use App\Http\Requests\AiAssistRequest;
use App\Http\Requests\FeedRequest;
use App\Models\ClientApiKey;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Services\AiService;
use App\Services\Media\PresignedUrlService;
use App\Services\Search\TenantTokenIssuer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $stories = Cache::remember($key, 60, function () use ($request) {
            $q = Story::with(['category', 'tags', 'media'])->where('status', 'published');

            if ($request->filled('category') && $request->query('category') !== 'All') {
                $cat = (string) $request->query('category');
                $q->whereHas('category', fn ($c) => $c->where('name_en', $cat)->orWhere('slug', Str::slug($cat)));
            }
            if ($request->filled('language') && in_array($request->query('language'), ['en', 'bn'], true)) {
                $q->where('language', $request->query('language'));
            }
            if ($request->boolean('is_breaking')) {
                $q->where('is_breaking', true);
            }
            if ($request->filled('search')) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($sub) => $sub->where('headline', 'ilike', $term)->orWhere('brief', 'ilike', $term));
            }
            $limit = min(100, max(1, (int) $request->query('limit', 50)));

            return $q->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()->map(fn ($s) => [
                'public_id' => $s->public_id,
                'headline' => $s->headline,
                'sub_head' => $s->sub_head,
                'brief' => $s->brief,
                'body_html' => $s->body_html,
                'category' => $s->category->name_en ?? 'General',
                'language' => $s->language,
                'published_at' => $s->published_at?->toIso8601String(),
                'status' => $s->status,
                'is_breaking' => (bool) $s->is_breaking,
                'tags' => $s->tags->pluck('name')->values()->all(),
                'caps' => $s->media->pluck('caption')->filter()->values()->all(),
                'media' => $s->media->map(fn ($m) => [
                    'id' => $m->id,
                    'public_id' => $m->public_id,
                    'caption' => $m->caption ?: $m->title,
                    'kind' => $m->kind,
                    'credit' => $m->credit ?? 'UNB',
                ])->values()->all(),
                'has_video' => $s->media->where('kind', 'video')->isNotEmpty(),
            ]);
        });

        return response()->json(['data' => $stories])->header('Cache-Control', 'public, max-age=60');
    })->middleware('throttle:60,1');

    Route::get('/context', function () {
        $client = App\Models\Client::where('name', 'like', '%Daily Star%')->first() ?? App\Models\Client::first();

        return response()->json([
            'client' => [
                'name' => $client ? $client->name : 'The Daily Star',
                'initials' => 'DS',
                'tier' => 'Premium',
                'renews_at' => '1 Oct 2026',
                'stories_quota' => 500,
                'stories_used' => 342,
                'media_quota' => 150,
                'media_used' => 87,
            ],
            'saved_searches' => [
                ['name' => 'National elections', 'q' => 'election'],
                ['name' => 'Chittagong port', 'q' => 'port'],
                ['name' => 'Solar energy', 'q' => 'solar'],
                ['name' => 'Padma bridge', 'q' => 'padma'],
            ],
        ]);
    })->middleware('throttle:60,1');

    Route::get('/story/{publicId}', function (string $publicId) {
        $s = Story::with(['category', 'tags', 'media'])->where('public_id', $publicId)->where('status', 'published')->firstOrFail();

        return response()->json(['data' => [
            'public_id' => $s->public_id,
            'headline' => $s->headline,
            'sub_head' => $s->sub_head,
            'brief' => $s->brief,
            'body_html' => $s->body_html,
            'category' => $s->category->name_en ?? 'General',
            'language' => $s->language,
            'published_at' => $s->published_at?->toIso8601String(),
            'status' => $s->status,
            'is_breaking' => (bool) $s->is_breaking,
            'tags' => $s->tags->pluck('name')->values()->all(),
            'caps' => $s->media->pluck('caption')->filter()->values()->all(),
            'media' => $s->media->map(fn ($m) => [
                'id' => $m->id,
                'public_id' => $m->public_id,
                'caption' => $m->caption ?: $m->title,
                'kind' => $m->kind,
                'credit' => $m->credit ?? 'UNB',
            ])->values()->all(),
        ]]);
    })->middleware('throttle:120,1');
});

Route::prefix('v1')->group(function () {
    Route::get('/feed', function (FeedRequest $request) {
        $validated = $request->validated();
        $since = $validated['since'] ?? null;
        $limit = min(50, (int) ($validated['limit'] ?? 20));
        $q = Story::with('category')->where('status', 'published');
        if ($since) {
            try {
                $decoded = base64_decode($since, true);
                if ($decoded && str_contains($decoded, '|')) {
                    [$ts,$id] = explode('|', $decoded, 2);
                    $dt = Carbon::parse($ts);
                    $q->where(fn ($qq) => $qq->where('published_at', '<', $dt)->orWhere(fn ($q2) => $q2->where('published_at', $dt)->where('id', '<', $id)));
                } else {
                    $dt = Carbon::parse(base64_decode($since));
                    $q->where('published_at', '>', $dt);
                }
            } catch (Throwable $e) {
            }
        }
        $cacheKey = 'feed:v1:'.md5($request->fullUrl());
        $payload = Cache::remember($cacheKey, 60, function () use ($q, $limit) {
            $stories = (clone $q)->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()->map(fn ($s) => [
                'public_id' => $s->public_id, 'headline' => $s->headline, 'brief' => $s->brief, 'language' => $s->language, 'published_at' => $s->published_at?->toIso8601String(), 'is_breaking' => $s->is_breaking,
            ]);
            $last = $stories->last();
            $cursor = $last ? base64_encode($last['published_at'].'|'.$last['public_id']) : null;

            return ['data' => $stories, 'cursor' => $cursor];
        });

        return response()->json($payload)->header('Cache-Control', 'public, max-age=60');
    })->middleware(['client.api:feed:read', 'throttle:60,1']);
});

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('/uploads', [TusController::class, 'create']);
    Route::patch('/uploads/{id}', [TusController::class, 'patch']);
    Route::match(['head'], '/uploads/{id}', [TusController::class, 'head']);
    Route::get('/media/{id}/presigned', function (string $id, PresignedUrlService $svc) {
        $asset = MediaAsset::where('public_id', $id)->firstOrFail();
        $url = $svc->forAsset($asset, request('variant', 'original'));
        $svc->recordDownload($asset, null, null, request('variant', 'original'));

        return response()->json(['url' => $url, 'expires_in' => 300]);
    });
});

Route::middleware('auth:sanctum')->post('/ai/{kind}', function (string $kind, AiAssistRequest $request, AiService $svc) {
    $pack = $svc->call($kind, $request->validated(), $request->user()->id, $request->input('story_id'));

    return response()->json(['pack' => $pack]);
})->middleware('throttle:30,1');
