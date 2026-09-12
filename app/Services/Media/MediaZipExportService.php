<?php

namespace App\Services\Media;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Services\Billing\QuotaService;
use App\Services\Search\EntitlementResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use ZipArchive;

class MediaZipExportService
{
    public function __construct(
        private EntitlementResolver $entitlementResolver,
        private QuotaService $quotaService,
    ) {}

    /**
     * Packages selected media assets into a temporary ZIP, uploads to storage,
     * ledgers downloads, and returns presigned URL and metadata.
     *
     * @param  Collection<int, MediaAsset>  $assets
     * @return array{download_url: string, expires_in: int, filename: string, asset_count: int, size_bytes: int}
     */
    public function export(Collection $assets, Client $client, ?int $clientUserId = null, string $variant = 'original'): array
    {
        if ($assets->isEmpty()) {
            throw new \InvalidArgumentException('No assets provided for export.');
        }

        // 1. Entitlement check
        $ent = $this->entitlementResolver->forClient($client);
        if (! empty($ent['media_kinds'])) {
            foreach ($assets as $asset) {
                if (! in_array($asset->kind, $ent['media_kinds'], true)) {
                    throw new AccessDeniedHttpException("Client is not entitled to '{$asset->kind}' media assets.");
                }
            }
        }

        // 2. Quota check
        $usage = $this->quotaService->getUsage($client);
        if ($usage['media_quota'] !== null && ($usage['media_used'] + $assets->count()) > $usage['media_quota']) {
            throw new HttpException(429, 'Media download quota insufficient for requested number of assets.');
        }

        // 3. Build ZIP
        $ulid = (string) Str::ulid();
        $exportFilename = "media-export-{$ulid}.zip";
        $tempPath = tempnam(sys_get_temp_dir(), 'unb_zip_');

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to initialize temporary ZIP archive.');
        }

        $now = now();
        $ledgerRows = [];
        $assetIdsToIncrement = [];

        foreach ($assets as $asset) {
            $disk = $asset->storage_disk ?: 's3';
            $path = $asset->original_path;
            if ($variant !== 'original' && ! empty($asset->derivatives[$variant])) {
                $path = is_string($asset->derivatives[$variant]) ? $asset->derivatives[$variant] : ($asset->derivatives[$variant]['path'] ?? $path);
            }

            // Get content from disk
            if (Storage::disk($disk)->exists($path)) {
                $content = Storage::disk($disk)->get($path);
                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
                $entryName = "{$asset->public_id}_{$variant}.{$ext}";
                $zip->addFromString($entryName, $content);

                $ledgerRows[] = [
                    'client_id' => $client->id,
                    'client_user_id' => $clientUserId,
                    'item_type' => 'media',
                    'item_id' => $asset->id,
                    'format' => $variant,
                    'size_bytes' => $asset->size_bytes,
                    'ip' => request()->ip(),
                    'created_at' => $now,
                ];
                $assetIdsToIncrement[] = $asset->id;
            }
        }

        $zip->close();

        $zipSize = file_exists($tempPath) ? filesize($tempPath) : 0;
        $storageDisk = 's3';
        $destinationPath = "exports/{$exportFilename}";

        // Upload to storage
        $stream = fopen($tempPath, 'r');
        Storage::disk($storageDisk)->put($destinationPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tempPath);

        // Bulk insert download ledger records and increment counts
        if (! empty($ledgerRows)) {
            DB::table('downloads')->insert($ledgerRows);
            MediaAsset::whereIn('id', $assetIdsToIncrement)->increment('download_count');
        }

        // Generate temporary URL
        try {
            $downloadUrl = Storage::disk($storageDisk)->temporaryUrl($destinationPath, now()->addMinutes(5));
        } catch (\Throwable $e) {
            Log::warning('Media export presigned URL fallback', ['path' => $destinationPath, 'error' => $e->getMessage()]);
            $cdn = rtrim(config("filesystems.disks.{$storageDisk}.url") ?? env('AWS_URL', ''), '/');
            $downloadUrl = $cdn ? "{$cdn}/{$destinationPath}?expires=".now()->addMinutes(5)->timestamp : url("/storage/{$destinationPath}");
        }

        return [
            'download_url' => $downloadUrl,
            'expires_in' => 300,
            'filename' => $exportFilename,
            'asset_count' => count($assetIdsToIncrement),
            'size_bytes' => $zipSize,
        ];
    }
}
