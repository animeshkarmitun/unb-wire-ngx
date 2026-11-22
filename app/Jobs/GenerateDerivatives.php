<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Support\ImageWebpConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateDerivatives implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $assetId)
    {
        $this->onQueue('derivatives');
    }

    public function handle(): void
    {
        $asset = MediaAsset::find($this->assetId);
        if (! $asset || ! empty($asset->derivatives['thumb'])) {
            return;
        }
        if ($asset->kind !== 'photo') {
            return;
        }

        try {
            $bytes = Storage::disk($asset->storage_disk)->get($asset->original_path);
        } catch (\Throwable $e) {
            return;
        }

        if (! is_string($bytes) || $bytes === '') {
            return;
        }

        // COS-6: generate a 1:1 full-resolution WebP counterpart alongside the
        // original file for photo-library uploads, plus the 4 responsive WebP variants.
        $fullWebp = ImageWebpConverter::convertBytes($bytes, null, 85);
        if (! $fullWebp) {
            return;
        }

        $derivatives = $asset->derivatives ?? [];
        $fullPath = "media/derivatives/{$asset->public_id}/full.webp";
        Storage::disk($asset->storage_disk)->put($fullPath, $fullWebp['bytes']);
        $derivatives['webp'] = [
            'path' => $fullPath,
            'width' => $fullWebp['width'],
            'height' => $fullWebp['height'],
            'size_bytes' => $fullWebp['size_bytes'],
        ];

        $variants = ['thumb' => 400, 'small' => 800, 'medium' => 1200, 'large' => 1600];

        foreach ($variants as $variant => $maxWidth) {
            $converted = ImageWebpConverter::convertBytes($bytes, $maxWidth, 82);
            if (! $converted) {
                continue;
            }

            $path = "media/derivatives/{$asset->public_id}/{$variant}.webp";
            Storage::disk($asset->storage_disk)->put($path, $converted['bytes']);
            $derivatives[$variant] = [
                'path' => $path,
                'width' => $converted['width'],
                'height' => $converted['height'],
                'size_bytes' => $converted['size_bytes'],
            ];
        }

        $asset->update(['derivatives' => $derivatives]);
    }
}
