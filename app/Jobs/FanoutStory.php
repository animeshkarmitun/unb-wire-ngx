<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Story;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;
use App\Services\Delivery\TriggerMatcher;
use App\Services\Delivery\WebhookPayloadBuilder;
use App\Services\Delivery\WebhookSigner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FanoutStory implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public int $storyId)
    {
        $this->onQueue('fanout');
    }

    public function handle(?ClientRepository $clients = null, ?DeliveryRepository $deliveries = null): void
    {
        $clients = $clients ?? app(ClientRepository::class);
        $deliveries = $deliveries ?? app(DeliveryRepository::class);
        $story = Story::with(['media', 'category', 'tags', 'owner'])->find($this->storyId);
        if (! $story) {
            return;
        }

        if ($story->status === 'killed') {
            $this->sendKillNotices($story, $clients, $deliveries);

            return;
        }
        if ($story->status !== 'published') {
            return;
        }

        $raw = DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->join('clients', 'clients.id', '=', 'client_packages.client_id')
            ->where('client_packages.status', 'active')
            ->where('clients.status', 'active')
            ->select('client_packages.client_id', 'packages.entitlement_filter')->get();

        $matchedClients = $raw->filter(function ($r) use ($story) {
            $f = is_string($r->entitlement_filter) ? json_decode($r->entitlement_filter, true) : $r->entitlement_filter;
            if (! is_array($f)) {
                return false;
            }

            $langs = (array) ($f['languages'] ?? []);
            if (! empty($langs) && ! in_array($story->language, $langs, true)) {
                return false;
            }

            $cats = (array) ($f['category_ids'] ?? []);
            if (! empty($cats) && ! in_array($story->category_id, $cats, true)) {
                return false;
            }

            $kinds = (array) ($f['media_kinds'] ?? []);
            if (! empty($kinds)) {
                $storyKinds = $story->media->pluck('kind')->unique()->all();
                if (empty(array_intersect($kinds, $storyKinds))) {
                    return false;
                }
            }

            return true;
        })->values();

        foreach ($matchedClients as $row) {
            $channels = $clients->activeChannelsFor($row->client_id);
            foreach ($channels as $ch) {
                $key = hash('sha256', $story->id.'-'.$ch->id.'-'.$story->version);
                $payloadHash = hash('sha256', $story->body_text ?? '');

                if (DB::table('deliveries')->where('idempotency_key', $key)->exists()) {
                    continue;
                }

                try {
                    $event = $deliveries->hasPriorSuccess($story->id, $ch->id) ? 'story.updated' : 'story.published';
                    $deliveryId = DB::table('deliveries')->insertGetId([
                        'deliverable_type' => 'story', 'deliverable_id' => $story->id, 'client_id' => $row->client_id, 'channel_id' => $ch->id, 'status' => 'queued', 'attempt_count' => 0, 'idempotency_key' => $key, 'payload_hash' => $payloadHash, 'created_at' => now(),
                    ]);
                    $config = is_string($ch->config) ? json_decode($ch->config, true) : $ch->config;
                    if ($ch->type === 'webhook' && ! empty($config['url'])) {
                        $triggers = $config['triggers'] ?? [];
                        if (! app(TriggerMatcher::class)->shouldFire($story, $triggers)) {
                            DB::table('deliveries')->where('idempotency_key', $key)->update(['status' => 'skipped_entitlement']);

                            continue;
                        }

                        $this->sendWebhook($story, $ch, $config, $event, $key, $clients);
                    } elseif ($ch->type === 'ftp') {
                        PushFtpDelivery::dispatch($deliveryId);
                    }
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'Unique')) {
                        continue;
                    }
                    Log::warning('fanout failed', ['client' => $row->client_id, 'channel' => $ch->id, 'error' => $e->getMessage()]);
                    $clients->recordChannelFailure($ch->id);
                }
            }
        }

        // Email delivery (via clients.notes, not client_channels)
        $emailClients = Client::whereNotNull('notes')
            ->where('status', 'active')
            ->get()
            ->filter(function ($c) {
                $notes = is_string($c->notes) ? json_decode($c->notes, true) : ($c->notes ?? []);

                return ! empty($notes['channels']['email']['on']);
            });

        foreach ($emailClients as $emailClient) {
            SendStoryEmail::dispatch($story->id, $emailClient->id);
        }
    }

    private function sendKillNotices(Story $story, ClientRepository $clients, DeliveryRepository $deliveries): void
    {
        $recipients = DB::table('deliveries')
            ->where('deliverable_type', 'story')
            ->where('deliverable_id', $story->id)
            ->whereIn('status', ['sent', 'delivered'])
            ->distinct()
            ->get(['client_id', 'channel_id']);

        if ($recipients->isEmpty()) {
            return;
        }

        $channels = ClientChannel::whereIn('id', $recipients->pluck('channel_id')->unique())->get()->keyBy('id');

        foreach ($recipients as $r) {
            $ch = $channels->get($r->channel_id);
            if (! $ch) {
                continue;
            }

            $key = hash('sha256', $story->id.'-'.$ch->id.'-'.$story->version.'-killed');
            if (DB::table('deliveries')->where('idempotency_key', $key)->exists()) {
                continue;
            }

            try {
                $deliveryId = DB::table('deliveries')->insertGetId([
                    'deliverable_type' => 'story', 'deliverable_id' => $story->id, 'client_id' => $r->client_id, 'channel_id' => $ch->id, 'status' => 'queued', 'attempt_count' => 0, 'idempotency_key' => $key, 'payload_hash' => hash('sha256', 'killed:'.(string) $story->public_id), 'created_at' => now(),
                ]);
                $config = is_string($ch->config) ? json_decode($ch->config, true) : $ch->config;
                if ($ch->type === 'webhook' && ! empty($config['url'])) {
                    $this->sendWebhook($story, $ch, $config, 'story.killed', $key, $clients);
                } elseif ($ch->type === 'ftp') {
                    PushFtpDelivery::dispatch($deliveryId);
                }
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'Unique')) {
                    continue;
                }
                Log::warning('fanout kill notice failed', ['client' => $r->client_id, 'channel' => $ch->id, 'error' => $e->getMessage()]);
                $clients->recordChannelFailure($ch->id);
            }
        }
    }

    private function sendWebhook(Story $story, ClientChannel $ch, array $config, string $event, string $idempotencyKey, ClientRepository $clients): void
    {
        $payload = app(WebhookPayloadBuilder::class)->build($story, $event);
        $jsonPayload = json_encode($payload);
        $secret = $config['signing_secret'] ?? '';
        $headers = app(WebhookSigner::class)->headers($jsonPayload, $secret, $event);

        $response = Http::withHeaders($headers)->timeout(5)->post($config['url'], $payload);
        if ($response->successful()) {
            DB::table('deliveries')->where('idempotency_key', $idempotencyKey)->update(['status' => 'sent', 'sent_at' => now()]);
            $clients->recordChannelSuccess($ch->id);
        } else {
            $clients->recordChannelFailure($ch->id);
        }
    }
}
