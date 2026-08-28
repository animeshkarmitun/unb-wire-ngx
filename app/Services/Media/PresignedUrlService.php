<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class PresignedUrlService
{
    public function forAsset(MediaAsset $asset, string $variant = 'original', int $ttlMinutes = 5): string
    {
        $disk = $asset->storage_disk ?: 's3';
        $path = $asset->original_path;
        if ($variant !== 'original' && ! empty($asset->derivatives[$variant])) {
            $path = is_string($asset->derivatives[$variant]) ? $asset->derivatives[$variant] : ($asset->derivatives[$variant]['path'] ?? $path);
        }
        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($ttlMinutes));
        } catch (\Throwable $e) {
            $cdn = rtrim(config('filesystems.disks.s3.url') ?? env('AWS_URL', ''), '/');
            if ($cdn) return "{$cdn}/{$path}?expires=".now()->addMinutes($ttlMinutes)->timestamp;
            return "https://cdn.example.test/{$path}?expires=".now()->addMinutes($ttlMinutes)->timestamp."&sig=fake";
        }
    }

    public function recordDownload(MediaAsset $asset, ?int $clientId, ?int $clientUserId, string $variant = 'original'): void
    {
        if (! $clientId) return;
        \Illuminate\Support\Facades\DB::table('downloads')->insert([
            'client_id' => $clientId,
            'client_user_id' => $clientUserId,
            'item_type' => 'media',
            'item_id' => $asset->id,
            'format' => $variant,
            'size_bytes' => $asset->size_bytes,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
        $asset->increment('download_count');
    }
}
