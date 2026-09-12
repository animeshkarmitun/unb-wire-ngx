<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Services\Media\MediaZipExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportMediaZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int|string>  $assetIds
     */
    public function __construct(
        public readonly array $assetIds,
        public readonly Client $client,
        public readonly ?int $clientUserId = null,
        public readonly string $variant = 'original',
    ) {}

    public function handle(MediaZipExportService $service): array
    {
        $assets = MediaAsset::whereIn('public_id', $this->assetIds)
            ->when(
                count(array_filter($this->assetIds, 'is_numeric')) > 0,
                fn ($q) => $q->orWhereIn('id', array_filter($this->assetIds, 'is_numeric'))
            )
            ->get();

        return $service->export($assets, $this->client, $this->clientUserId, $this->variant);
    }
}
