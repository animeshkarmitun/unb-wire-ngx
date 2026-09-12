<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\Story;
use App\Repositories\StoryRepository;
use App\Services\Search\TenantTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    public function __construct(private StoryRepository $stories) {}

    public function searchToken(Request $request, TenantTokenIssuer $issuer): JsonResponse
    {
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
    }

    public function feed(Request $request): JsonResponse
    {
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

            return $q->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()->map(fn ($s) => self::formatStory($s));
        });

        return response()->json(['data' => $stories])->header('Cache-Control', 'public, max-age=60');
    }

    public function context(Request $request, \App\Services\ApiKeyService $apiSvc): JsonResponse
    {
        $client = null;
        $raw = $request->bearerToken() ?? $request->header('X-API-Key');
        if ($raw) {
            $key = $apiSvc->authenticate($raw);
            if ($key && $key->client) {
                $client = $key->client;
            }
        }

        if (! $client) {
            return response()->json([
                'client' => null,
                'saved_searches' => [],
            ]);
        }

        $activeSub = \App\Models\ClientPackage::where('client_id', $client->id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where('status', 'active')
            ->with('package')
            ->first();

        $notes = is_string($client->notes) ? json_decode($client->notes, true) : (is_array($client->notes) ? $client->notes : []);
        $tierQuotas = $notes['tier_quotas'] ?? [];
        
        $storiesUsed = \App\Models\Delivery::where('client_id', $client->id)
            ->where('deliverable_type', 'story')
            ->where('status', 'sent')
            ->whereMonth('created_at', now()->month)
            ->count();

        $mediaUsed = \App\Models\Download::where('client_id', $client->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        $initials = collect(explode(' ', $client->name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->implode('');

        return response()->json([
            'client' => [
                'name' => $client->name,
                'initials' => $initials,
                'tier' => $activeSub?->package?->name,
                'renews_at' => $activeSub?->ends_at?->format('Y-m-d'),
                'stories_quota' => $tierQuotas['stories_quota'] ?? null,
                'stories_used' => $storiesUsed,
                'media_quota' => $tierQuotas['media_quota'] ?? null,
                'media_used' => $mediaUsed,
            ],
            'saved_searches' => $notes['saved_searches'] ?? [],
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        $s = $this->stories->findPublishedByPublicId($publicId);

        if (! $s) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        return response()->json(['data' => self::formatStory($s)]);
    }

    private static function formatStory($s): array
    {
        return [
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
        ];
    }
}
