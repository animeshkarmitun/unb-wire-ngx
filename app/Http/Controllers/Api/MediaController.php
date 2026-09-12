<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportMediaZipJob;
use App\Models\ClientUser;
use App\Models\MediaAsset;
use App\Services\Billing\QuotaService;
use App\Services\Media\MediaZipExportService;
use App\Services\Media\PresignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MediaController extends Controller
{
    public function presigned(Request $request, string $id, PresignedUrlService $svc): JsonResponse
    {
        $asset = MediaAsset::where('public_id', $id)
            ->when(is_numeric($id), fn ($q) => $q->orWhere('id', (int) $id))
            ->firstOrFail();
        $variant = (string) $request->input('variant', 'original');
        $url = $svc->forAsset($asset, $variant);

        // If the authenticated user has an associated client, extract their client ID.
        // Internal staff users (editorial/admin) do not have a client association;
        // passing null skips ledgering since internal staff downloads do not count
        // toward client billing/quotas.
        $user = auth()->user();
        $clientId = $user?->client_id ?? ($user?->client?->id ?? null);
        $clientUserId = ($user instanceof ClientUser) ? $user->id : null;
        $svc->recordDownload($asset, $clientId, $clientUserId, $variant);

        return response()->json(['url' => $url, 'expires_in' => 300]);
    }

    public function clientPresigned(Request $request, string $id, PresignedUrlService $svc, QuotaService $quotaSvc): JsonResponse
    {
        // Scope check for API key users (portal users have implicit access)
        $apiKey = $request->attributes->get('clientApiKey');
        if ($apiKey && ! in_array('media:read', (array) $apiKey->scopes, true)) {
            return response()->json(['message' => 'Insufficient scope'], 403);
        }

        $client = $request->attributes->get('client');
        if ($client) {
            $quotaSvc->assertCanDownloadMedia($client);
        }

        $asset = MediaAsset::where('public_id', $id)
            ->when(is_numeric($id), fn ($q) => $q->orWhere('id', (int) $id))
            ->firstOrFail();
        $variant = (string) $request->input('variant', 'original');
        $url = $svc->forAsset($asset, $variant);

        $clientUser = $request->attributes->get('clientUser');
        $svc->recordDownload($asset, $client?->id, $clientUser?->id, $variant);

        return response()->json(['url' => $url, 'expires_in' => 300]);
    }

    public function export(Request $request, MediaZipExportService $exportService): JsonResponse
    {
        $validated = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1', 'max:50'],
            'asset_ids.*' => ['required'],
            'variant' => ['nullable', 'string', 'in:original,large,medium,small,thumb'],
            'async' => ['nullable', 'boolean'],
        ]);

        $client = $request->attributes->get('client');
        if (! $client) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Scope check for API key users
        $apiKey = $request->attributes->get('clientApiKey');
        if ($apiKey && ! in_array('media:read', (array) $apiKey->scopes, true)) {
            return response()->json(['message' => 'Insufficient scope'], 403);
        }

        $clientUser = $request->attributes->get('clientUser');
        $variant = $validated['variant'] ?? 'original';
        $assetIds = $validated['asset_ids'];

        $assets = MediaAsset::whereIn('public_id', $assetIds)
            ->when(
                count(array_filter($assetIds, 'is_numeric')) > 0,
                fn ($q) => $q->orWhereIn('id', array_filter($assetIds, 'is_numeric'))
            )
            ->get();

        if ($assets->isEmpty()) {
            return response()->json(['error' => 'None of the requested media assets were found'], 404);
        }

        if ($request->boolean('async')) {
            ExportMediaZipJob::dispatch($assetIds, $client, $clientUser?->id, $variant);

            return response()->json([
                'message' => 'Export job queued',
                'asset_count' => $assets->count(),
            ], 202);
        }

        try {
            $result = $exportService->export($assets, $client, $clientUser?->id, $variant);

            return response()->json($result);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
