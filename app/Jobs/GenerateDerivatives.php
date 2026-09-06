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
        if (! $asset) {
            return;
        }
        if (! empty($asset->derivatives['thumb'])) {
            return;
        }

        $derivatives = [
            'thumb' => ['path' => "d/{$asset->public_id}/thumb.webp", 'width' => 400],
            'preview' => ['path' => "d/{$asset->public_id}/preview.webp", 'width' => 800],
            'large' => ['path' => "d/{$asset->public_id}/large.webp", 'width' => 1600],
        ];

        try {
            Storage::disk($asset->storage_disk)->exists($asset->original_path);
        } catch (\Throwable $e) {
        }

        $asset->update(['derivatives' => $derivatives]);
    }
}
