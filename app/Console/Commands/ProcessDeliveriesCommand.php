<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Setting;
use App\Models\Story;
use App\Repositories\ClientRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProcessDeliveriesCommand extends Command
{
    protected $signature = 'delivery:process {--limit=50}';
    protected $description = 'Process queued and failed deliveries';

    public function handle(ClientRepository $clients)
    {
        $limit = $this->option('limit');
        $settings = Setting::where('key', 'delivery')->first()->value ?? [];
        $maxRetries = $settings['max_retries'] ?? 5;
        $backoffSeconds = $settings['backoff_seconds'] ?? 60;
        $autoPauseThreshold = $settings['auto_pause_threshold'] ?? 5;

        $deliveries = Delivery::with(['channel', 'client'])
            ->whereIn('status', ['queued', 'failed'])
            ->where('attempt_count', '<', $maxRetries)
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            if ($delivery->status === 'failed') {
                $waitSecs = $backoffSeconds * (pow(2, $delivery->attempt_count) - 1);
                if (now()->lessThan($delivery->created_at->addSeconds($waitSecs))) {
                    continue;
                }
            }

            $channel = $delivery->channel;
            if (! $channel || $channel->status !== 'active') {
                continue;
            }

            if ($channel->type === 'ftp') {
                continue;
            }

            if ($channel->type === 'webhook') {
                $config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
                if (empty($config['url'])) {
                    continue;
                }

                if ($delivery->deliverable_type === 'story') {
                    $story = Story::find($delivery->deliverable_id);
                    if (! $story) {
                        $delivery->update([
                            'status' => 'failed',
                            'error' => 'Story not found',
                            'attempt_count' => $delivery->attempt_count + 1
                        ]);
                        continue;
                    }

                    $payload = app(\App\Services\Delivery\WebhookPayloadBuilder::class)->build($story, 'story.published');
                    $jsonPayload = json_encode($payload);
                    $secret = $config['signing_secret'] ?? '';
                    $headers = app(\App\Services\Delivery\WebhookSigner::class)->headers($jsonPayload, $secret, 'story.published');
                    
                    try {
                        $response = Http::withHeaders($headers)->timeout(5)->post($config['url'], $payload);
                        
                        if ($response->successful()) {
                            $delivery->update([
                                'status' => 'sent',
                                'sent_at' => now(),
                                'response_code' => $response->status(),
                                'error' => null,
                            ]);
                            $clients->recordChannelSuccess($channel->id);
                        } else {
                            $this->handleFailure($delivery, $clients, $maxRetries, $autoPauseThreshold, $response->status(), $response->body());
                        }
                    } catch (\Throwable $e) {
                        $this->handleFailure($delivery, $clients, $maxRetries, $autoPauseThreshold, null, $e->getMessage());
                    }
                }
            }
        }
    }

    private function handleFailure(Delivery $delivery, ClientRepository $clients, int $maxRetries, int $autoPauseThreshold, ?int $code, string $error)
    {
        $newCount = $delivery->attempt_count + 1;
        
        $delivery->update([
            'status' => 'failed',
            'attempt_count' => $newCount,
            'response_code' => $code,
            'error' => substr($error, 0, 1000),
        ]);

        $clients->recordChannelFailure($delivery->channel_id, $autoPauseThreshold);
    }
}
