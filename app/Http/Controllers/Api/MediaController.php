<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\PresignedUrlService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function presigned(string $id, PresignedUrlService $svc): JsonResponse
    {
        $asset = MediaAsset::where('public_id', $id)->firstOrFail();
        $variant = request('variant', 'original');
        $url = $svc->forAsset($asset, $variant);
        $svc->recordDownload($asset, null, null, $variant);

        return response()->json(['url' => $url, 'expires_in' => 300]);
    }
}
