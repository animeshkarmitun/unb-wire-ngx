<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientRepository
{
    // ─── Read Methods ───────────────────────────────────────────

    public function findOrFail(int $id): Client
    {
        return Client::findOrFail($id);
    }

    public function findWithRelations(int $id, array $relations = []): ?Client
    {
        return Client::with($relations)->find($id);
    }

    public function findWithFullRelations(int $id): ?Client
    {
        return Client::with(['clientChannels', 'clientPackages.package', 'clientUsers'])->find($id);
    }

    public function findByCode(string $code): ?Client
    {
        return Client::where('code', $code)->first();
    }

    public function firstByName(string $name): ?Client
    {
        return Client::where('name', 'like', '%'.$name.'%')->first();
    }

    public function firstOrCreate(): Client
    {
        return Client::first();
    }

    public function allOrdered(): Collection
    {
        return Client::orderBy('name')->get();
    }

    public function activeCount(): int
    {
        return Client::where('status', 'active')->count();
    }

    public function count(): int
    {
        return Client::count();
    }

    public function activeClientsWithPackages(): Collection
    {
        return Client::where('status', 'active')
            ->with(['clientPackages.package'])
            ->get();
    }

    public function topClients(Carbon $today, int $limit = 3): Collection
    {
        return Client::where('status', 'active')
            ->with(['clientPackages.package'])
            ->withCount(['downloads as downloads_today_count' => function ($q) use ($today) {
                $q->whereDate('created_at', $today);
            }])
            ->withCount('downloads')
            ->orderByDesc('downloads_today_count')
            ->orderByDesc('downloads_count')
            ->limit($limit)
            ->get();
    }

    public function activeClientsThisWeek(Carbon $startOfWeek): int
    {
        return Client::where('status', 'active')->where('created_at', '>=', $startOfWeek)->count();
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->refresh();
    }

    // ─── API Keys ──────────────────────────────────────────────

    public function findActiveKeyByHash(string $hash): ?ClientApiKey
    {
        return ClientApiKey::where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();
    }

    public function findActiveKeyForClient(int $clientId): ?ClientApiKey
    {
        return ClientApiKey::where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->latest()
            ->first();
    }

    public function createKey(array $data): ClientApiKey
    {
        return ClientApiKey::create($data);
    }

    public function rotateKey(ClientApiKey $old, string $newHash): ClientApiKey
    {
        $new = ClientApiKey::create([
            'client_id' => $old->client_id,
            'name' => $old->name.'-rotated',
            'key_hash' => $newHash,
            'scopes' => $old->scopes,
            'rate_limit_rpm' => $old->rate_limit_rpm,
        ]);
        $old->update(['expires_at' => now()->addHour()]);

        return $new;
    }

    public function revokeKey(ClientApiKey $key): void
    {
        $key->update(['revoked_at' => now()]);
    }

    public function touchLastUsed(ClientApiKey $key): void
    {
        $key->update(['last_used_at' => now()]);
    }

    // ─── Channels ──────────────────────────────────────────────

    public function activeChannelsFor(int $clientId): Collection
    {
        return ClientChannel::where('client_id', $clientId)
            ->where('status', 'active')
            ->get();
    }

    public function findChannel(int $clientId, string $type): ?ClientChannel
    {
        return ClientChannel::where('client_id', $clientId)
            ->where('type', $type)
            ->first();
    }

    public function createChannel(array $data): ClientChannel
    {
        return ClientChannel::create($data);
    }

    public function updateChannel(ClientChannel $channel, array $data): ClientChannel
    {
        $channel->update($data);

        return $channel;
    }

    public function recordChannelSuccess(int $channelId): void
    {
        DB::table('client_channels')
            ->where('id', $channelId)
            ->update(['last_success_at' => now(), 'failure_count' => 0]);
    }

    public function recordChannelFailure(int $channelId, int $autoPauseAfter = 5): void
    {
        DB::table('client_channels')->where('id', $channelId)->increment('failure_count');
        $failures = DB::table('client_channels')->where('id', $channelId)->value('failure_count');
        if ($failures >= $autoPauseAfter) {
            DB::table('client_channels')->where('id', $channelId)->update(['status' => 'paused']);
        }
    }

    // ─── Subscriptions ─────────────────────────────────────────

    public function activeSubscriptionsFor(int $clientId): Collection
    {
        return ClientPackage::where('client_id', $clientId)
            ->where('status', 'active')
            ->get();
    }

    public function createSubscription(int $clientId, int $packageId, ?string $endsAt = null): ClientPackage
    {
        return ClientPackage::create([
            'client_id' => $clientId,
            'package_id' => $packageId,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'status' => 'active',
        ]);
    }

    public function updateOrCreateSubscription(int $clientId, int $packageId): ClientPackage
    {
        return ClientPackage::updateOrCreate(
            ['client_id' => $clientId, 'status' => 'active'],
            ['package_id' => $packageId, 'starts_at' => now()]
        );
    }

    // ─── Downloads ─────────────────────────────────────────────

    public function recentDownloads(int $clientId, int $limit = 10): Collection
    {
        return \App\Models\Download::where('client_id', $clientId)
            ->with(['mediaAsset', 'clientUser'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
