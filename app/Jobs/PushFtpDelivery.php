<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Models\Story;
use App\Repositories\ClientRepository;
use App\Services\Delivery\FtpDiskFactory;
use App\Services\Delivery\WireFormatFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushFtpDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('fanout');
    }

    public function handle(
        ClientRepository $clients,
        WireFormatFactory $wireFormatFactory,
        FtpDiskFactory $ftpDiskFactory
    ): void {
        $delivery = Delivery::with(['channel', 'client'])->find($this->deliveryId);
        if (! $delivery || $delivery->deliverable_type !== 'story') {
            return;
        }

        $story = Story::with(['category', 'tags', 'media'])->find($delivery->deliverable_id);
        if (! $story) {
            return;
        }

        $channel = $delivery->channel;
        if (! $channel || $channel->type !== 'ftp') {
            return;
        }

        $config = is_string($channel->config) ? json_decode($channel->config, true) : ($channel->config ?? []);
        $format = $config['wire_format'] ?? 'json-unb-v1';

        try {
            $wireOutput = $wireFormatFactory->generate($story, $format);
            $disk = $ftpDiskFactory->make($channel);

            $disk->put($wireOutput->filename, $wireOutput->content);

            // TODO: Media push if channel.config.push_media is true, per requirements

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $clients->recordChannelSuccess($channel->id);
        } catch (\Throwable $e) {
            $delivery->increment('attempt_count');
            $delivery->update(['error' => $e->getMessage()]);
            if ($delivery->channel_id) {
                $clients->recordChannelFailure($delivery->channel_id);
            }
            throw $e;
        }
    }
}
