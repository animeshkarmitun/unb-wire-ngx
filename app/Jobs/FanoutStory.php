<?php

namespace App\Jobs;

use App\Models\Story;
use App\Repositories\ClientRepository;
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

    public function handle(?ClientRepository $clients = null): void
    {
        $clients = $clients ?? app(ClientRepository::class);
        $story = Story::with('media')->find($this->storyId);
        if (! $story || $story->status !== 'published') {
            return;
        }

        $raw = DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.status', 'active')
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
                try {
                    DB::table('deliveries')->insert([
                        'deliverable_type' => 'story', 'deliverable_id' => $story->id, 'client_id' => $row->client_id, 'channel_id' => $ch->id, 'status' => 'queued', 'attempt_count' => 0, 'idempotency_key' => $key, 'payload_hash' => $payloadHash, 'created_at' => now(),
                    ]);
                    $config = is_string($ch->config) ? json_decode($ch->config, true) : $ch->config;
                    if ($ch->type === 'webhook' && ! empty($config['url'])) {
                        Http::timeout(5)->post($config['url'], ['public_id' => $story->public_id, 'headline' => $story->headline]);
                        DB::table('deliveries')->where('idempotency_key', $key)->update(['status' => 'sent', 'sent_at' => now()]);
                    }
                    $clients->recordChannelSuccess($ch->id);
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'Unique')) {
                        continue;
                    }
                    Log::warning('fanout failed', ['client' => $row->client_id, 'channel' => $ch->id, 'error' => $e->getMessage()]);
                    $clients->recordChannelFailure($ch->id);
                }
            }
        }
    }
}
