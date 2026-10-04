<?php

namespace App\Jobs;

use App\Models\MediaAsset;
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

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $variants = ['thumb' => 400, 'small' => 800, 'medium' => 1200, 'large' => 1600];
        $derivatives = $asset->derivatives ?? [];

        foreach ($variants as $variant => $maxWidth) {
            $scale = $width > 0 ? min(1, $maxWidth / $width) : 1;
            $tw = max(1, (int) round($width * $scale));
            $th = max(1, (int) round($height * $scale));

            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $width, $height);

            ob_start();
            imagewebp($dst, null, 82);
            $data = ob_get_clean();
            imagedestroy($dst);

            $path = "media/derivatives/{$asset->public_id}/{$variant}.webp";
            Storage::disk($asset->storage_disk)->put($path, $data);
            $derivatives[$variant] = ['path' => $path, 'width' => $tw, 'height' => $th];
        }

        imagedestroy($src);
        $asset->update(['derivatives' => $derivatives]);
    }
}
