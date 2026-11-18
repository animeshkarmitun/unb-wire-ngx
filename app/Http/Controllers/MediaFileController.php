<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves media asset bytes without exposing raw /storage paths.
 *
 * - client(): signed, short-TTL URL route (media.file) — the URL is minted only
 *   after authentication + entitlement checks (MediaController::clientPresigned /
 *   MediaZipExportService), then embargo re-checked at fetch time.
 * - staff(): session-authenticated newsroom access (admin grids, previews).
 */
class MediaFileController extends Controller
{
    public function client(Request $request, string $asset): Response
    {
        $a = MediaAsset::clientVisible()
            ->where('public_id', $asset)
            ->when(is_numeric($asset), fn ($q) => $q->orWhere('id', (int) $asset))
            ->firstOrFail();

        return $this->serve($a, (string) $request->query('variant', 'original'));
    }

    public function staff(Request $request, string $asset): Response
    {
        $a = MediaAsset::where('public_id', $asset)
            ->when(is_numeric($asset), fn ($q) => $q->orWhere('id', (int) $asset))
            ->firstOrFail();

        return $this->serve($a, (string) $request->query('variant', 'original'));
    }

    private function serve(MediaAsset $a, string $variant): Response
    {
        $disk = $a->storage_disk ?: 's3';
        $path = $a->original_path;

        if ($variant !== 'original') {
            $derivative = $a->derivatives[$variant] ?? null;
            if ($derivative === null) {
                abort(404);
            }
            $path = is_string($derivative) ? $derivative : ($derivative['path'] ?? $path);
        }

        if (in_array($disk, ['local', 'public'], true)) {
            $fs = Storage::disk($disk);

            if (! $fs->exists($path)) {
                abort(404);
            }

            return response()->file($fs->path($path), [
                'Content-Type' => $a->mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($path).'"',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        try {
            return redirect()->away(Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(5)));
        } catch (\Throwable $e) {
            abort(404);
        }
    }
}
