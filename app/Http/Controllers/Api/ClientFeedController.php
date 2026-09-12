<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedRequest;
use App\Models\Story;
use App\Repositories\StoryRepository;
use App\Services\Search\EntitlementResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ClientFeedController extends Controller
{
    public function __construct(
        private StoryRepository $stories,
        private EntitlementResolver $entitlementResolver,
    ) {}

    public function index(FeedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $since = $validated['since'] ?? null;
        $limit = min(50, (int) ($validated['limit'] ?? 20));
        $q = Story::with('category')->where(function ($query) {
            $query->where('status', 'published')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'killed')->whereNotNull('published_at');
                });
        });

        $client = $request->attributes->get('client');
        if ($client) {
            $ent = $this->entitlementResolver->forClient($client);
            if (! empty($ent['languages'])) {
                $q->whereIn('language', $ent['languages']);
            }
            if (! empty($ent['category_ids'])) {
                $q->whereIn('category_id', $ent['category_ids']);
            }
        }

        if ($since) {
            try {
                $decoded = base64_decode($since, true);
                if ($decoded && str_contains($decoded, '|')) {
                    [$ts, $id] = explode('|', $decoded, 2);
                    $dt = Carbon::parse($ts);
                    $q->where(fn ($qq) => $qq->where('published_at', '<', $dt)->orWhere(fn ($q2) => $q2->where('published_at', $dt)->where('id', '<', $id)));
                } else {
                    $dt = Carbon::parse(base64_decode($since));
                    $q->where('published_at', '>', $dt);
                }
            } catch (\Throwable $e) {
            }
        }

        $clientId = $client?->id ?? 'guest';
        $cacheKey = 'feed:v1:'.$clientId.':'.md5($request->fullUrl());
        $payload = Cache::remember($cacheKey, 60, function () use ($q, $limit) {
            $stories = (clone $q)->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()->map(fn ($s) => [
                'public_id' => $s->public_id,
                'headline' => $s->headline,
                'brief' => $s->status === 'killed' ? 'STORY KILLED / RETRACTED' : $s->brief,
                'language' => $s->language,
                'published_at' => $s->published_at?->toIso8601String(),
                'status' => $s->status,
                'is_breaking' => (bool) $s->is_breaking,
                'is_killed' => $s->status === 'killed',
                'killed_at' => $s->status === 'killed' ? $s->updated_at?->toIso8601String() : null,
            ]);
            $last = $stories->last();
            $cursor = $last ? base64_encode($last['published_at'].'|'.$last['public_id']) : null;

            return ['data' => $stories, 'cursor' => $cursor];
        });

        return response()->json($payload)->header('Cache-Control', 'public, max-age=60');
    }
}
