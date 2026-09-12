<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientPackage;
use App\Models\Story;
use App\Repositories\StoryRepository;
use App\Services\Billing\QuotaService;
use App\Services\Download\DownloadGateService;
use App\Services\Search\EntitlementResolver;
use App\Services\Search\TenantTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PortalController extends Controller
{
    public function __construct(
        private StoryRepository $stories,
        private EntitlementResolver $entitlementResolver,
    ) {}

    public function searchToken(Request $request, TenantTokenIssuer $issuer): JsonResponse
    {
        $client = $request->attributes->get('client');
        $data = $issuer->issueFor($client);

        return response()->json($data);
    }

    public function feed(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $clientId = $client?->id ?? 'guest';
        $key = 'portal:feed:'.$clientId.':'.md5($request->fullUrl());

        $stories = Cache::remember($key, 60, function () use ($request, $client) {
            $q = Story::with(['category', 'tags', 'media'])->where('status', 'published');

            if ($client) {
                $ent = $this->entitlementResolver->forClient($client);
                if (! empty($ent['languages'])) {
                    $q->whereIn('language', $ent['languages']);
                }
                if (! empty($ent['category_ids'])) {
                    $q->whereIn('category_id', $ent['category_ids']);
                }
            }

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

    public function context(Request $request, QuotaService $quotaSvc): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (! $client) {
            return response()->json([
                'client' => null,
                'saved_searches' => [],
            ]);
        }

        $activeSub = ClientPackage::where('client_id', $client->id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where('status', 'active')
            ->with('package')
            ->first();

        $notes = is_string($client->notes) ? json_decode($client->notes, true) : (is_array($client->notes) ? $client->notes : []);
        $usage = $quotaSvc->getUsage($client);

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
                'stories_quota' => $usage['stories_quota'],
                'stories_used' => $usage['stories_used'],
                'media_quota' => $usage['media_quota'],
                'media_used' => $usage['media_used'],
            ],
            'saved_searches' => $notes['saved_searches'] ?? [],
        ]);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $s = $this->stories->findPublishedByPublicId($publicId);

        if (! $s) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        $client = $request->attributes->get('client');
        if ($client) {
            $ent = $this->entitlementResolver->forClient($client);
            if (! empty($ent['languages']) && ! in_array($s->language, $ent['languages'], true)) {
                return response()->json(['error' => 'Story not found'], 404);
            }
            if (! empty($ent['category_ids']) && ! in_array($s->category_id, $ent['category_ids'])) {
                return response()->json(['error' => 'Story not found'], 404);
            }
        }

        return response()->json(['data' => self::formatStory($s)]);
    }

    public function download(Request $request, string $publicId, DownloadGateService $gateService): Response
    {
        $story = $this->stories->findPublishedByPublicId($publicId);

        if (! $story) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        $client = $request->attributes->get('client');
        if (! $client) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $clientUser = $request->attributes->get('clientUser');
        $format = (string) $request->query('format', 'json');

        try {
            $output = $gateService->downloadStory($story, $client, $clientUser?->id, $format);

            return response($output->content, 200, [
                'Content-Type' => $output->contentType,
                'Content-Disposition' => "attachment; filename=\"{$output->filename}\"",
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
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
