<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use App\Models\MediaAsset;
use App\Services\Media\PresignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function clientPresigned(Request $request, string $id, PresignedUrlService $svc): JsonResponse
    {
        $asset = MediaAsset::where('public_id', $id)
            ->when(is_numeric($id), fn ($q) => $q->orWhere('id', (int) $id))
            ->firstOrFail();
        $variant = (string) $request->input('variant', 'original');
        $url = $svc->forAsset($asset, $variant);

        $client = $request->attributes->get('client');
        $apiKey = $request->attributes->get('clientApiKey');
        $clientId = $client?->id ?? $apiKey?->client_id;

        $svc->recordDownload($asset, $clientId, null, $variant);

        return response()->json(['url' => $url, 'expires_in' => 300]);
    }
}
