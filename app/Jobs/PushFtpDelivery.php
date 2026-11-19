<?php

namespace App\Jobs;

use App\Models\ClientUser;
use App\Models\Delivery;
use App\Models\Story;
use App\Notifications\ClientStoryNotification;
use App\Repositories\ClientRepository;
use App\Services\Delivery\FtpDiskFactory;
use App\Services\Delivery\WireFormatFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        // A delivery that already reached a terminal state (sent/failed) is
        // done — replayed or duplicate jobs must not re-push or re-notify.
        if (in_array($delivery->status, ['sent', 'failed'], true)) {
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

            // Permanent failure after the final attempt → honest 'failed' state
            // in the distribution log instead of an eternally queued row.
            if ($delivery->attempt_count >= $this->tries) {
                $delivery->update(['status' => 'failed']);
                $this->notifyClientOfFailure($delivery, $story, $channel);
            }

            throw $e;
        }
    }

    /**
     * COS-77 follow-up: when an FTP delivery fails permanently, tell the
     * client's portal users which story did not reach them so they can pull
     * it from the portal instead.
     */
    private function notifyClientOfFailure(Delivery $delivery, Story $story, $channel): void
    {
        try {
            $users = ClientUser::where('client_id', $delivery->client_id)
                ->where('status', 'active')
                ->get();
            if ($users->isEmpty()) {
                return;
            }

            Notification::sendNow($users, new ClientStoryNotification('story.ftp_failed', [
                'title' => 'FTP delivery failed — '.$story->headline,
                'message' => 'This story could not be delivered to your FTP channel after repeated attempts. You can read or download it from the portal.',
                'story_id' => $story->id,
                'public_id' => (string) $story->public_id,
                'category' => $story->category?->name_en ?? 'General',
                'urgency' => 'regular',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify client users of FTP delivery failure', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
