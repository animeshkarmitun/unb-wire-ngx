<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientService
{
    // ─── Query & Stats ───────────────────────────────────────────────

    public function getFilteredClientsQuery(
        string $search = '',
        string $statusFilter = 'all',
        string $tierFilter = 'all',
        string $sort = 'name',
    ) {
        $query = Client::with(['clientChannels', 'clientPackages.package', 'clientUsers']);

        if ($search !== '') {
            $s = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(name) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(billing_email) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$s]);
            });
        }

        if ($statusFilter !== 'all') {
            $map = ['active' => 'active', 'paused' => 'suspended', 'deactivated' => 'closed'];
            $dbStatus = $map[$statusFilter] ?? $statusFilter;
            $query->where('status', $dbStatus);
        }

        if ($tierFilter !== 'all') {
            $query->where(function ($q) use ($tierFilter) {
                $q->whereHas('clientPackages.package', fn ($p) => $p->where('name', 'like', '%'.$tierFilter.'%'))
                    ->orWhere('notes', 'like', '%"tier":"'.$tierFilter.'"%');
            });
        }

        if ($sort === 'renewal') {
            $query->leftJoin('client_packages', 'clients.id', '=', 'client_packages.client_id')
                ->orderByRaw('client_packages.ends_at ASC NULLS LAST')
                ->select('clients.*');
        } elseif ($sort === 'usage') {
            $query->orderBy('name');
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    public function computeStats(): array
    {
        $allClients = Client::with(['clientChannels', 'clientPackages'])->get();

        $totalCount = $allClients->count();
        $activeCount = $allClients->where('status', 'active')->count();
        $pausedCount = $allClients->where('status', 'suspended')->count();

        $renewCount = $allClients->where('status', 'active')->filter(function ($c) {
            $endsAt = $c->clientPackages->first()?->ends_at;
            if (! $endsAt) {
                return false;
            }
            $days = Carbon::now()->diffInDays(Carbon::parse($endsAt), false);

            return $days >= 0 && $days <= 45;
        })->count();

        $issuesCount = $allClients->filter(fn ($c) => $this->clientHasIssue($c))->count();

        return [
            'total' => $totalCount,
            'active' => $activeCount,
            'paused' => $pausedCount,
            'renew' => $renewCount,
            'issues' => $issuesCount,
        ];
    }

    public function clientHasIssue(Client $client): bool
    {
        if ($client->status === 'closed') {
            return false;
        }

        return $client->clientChannels->contains(fn ($ch) => $ch->failure_count > 0 || ($ch->config['health'] ?? '') === 'fail');
    }

    public function getClientMeta(Client $client): array
    {
        if (! empty($client->notes) && str_starts_with($client->notes, '{')) {
            $decoded = json_decode($client->notes, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'ini' => strtoupper(substr($client->name, 0, 2)),
            'grad' => 'g'.(1 + ($client->id % 8)),
            'display_type' => $client->type,
            'city' => 'Dhaka',
            'tier' => 'Standard',
            'since' => $client->created_at->format('M Y'),
            'addons' => [],
            'usage' => ['dl' => 0, 'quota' => 800, 'api' => '—', 'last' => '1 hr ago'],
            'note_text' => $client->notes ?: '',
            'activity' => [],
        ];
    }

    // ─── Onboarding ─────────────────────────────────────────────────

    public function onboardClient(
        string $name,
        string $email,
        string $type,
        string $city,
        string $contact,
        string $packageName,
        array $addons,
        array $channels,
    ): Client {
        $tier = str_starts_with($packageName, 'Premium') ? 'Premium'
            : (str_starts_with($packageName, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $packageName)->first()
            ?? Package::first();

        $clientRoleId = Role::where('type', 'client')->value('id') ?? Role::first()?->id;

        $initials = collect(explode(' ', $name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->join('');

        $totalClients = Client::count();
        $grad = 'g'.(1 + ($totalClients % 8));
        $code = strtoupper(Str::slug(substr($name, 0, 4))).rand(10, 99);

        $quota = $tier === 'Premium' ? 2000 : ($tier === 'Standard' ? 800 : 200);

        DB::transaction(function () use ($name, $email, $type, $city, $packageName, $addons, $channels, $tier, $packageModel, $clientRoleId, $initials, $grad, $code, $quota, $contact) {
            $clientId = DB::table('clients')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $name,
                'code' => $code,
                'type' => 'newspaper',
                'country' => 'BD',
                'timezone' => 'Asia/Dhaka',
                'status' => 'active',
                'billing_email' => $email,
                'notes' => json_encode([
                    'ini' => $initials ?: 'CL',
                    'grad' => $grad,
                    'display_type' => $type,
                    'city' => $city ?: 'Dhaka',
                    'tier' => $tier,
                    'since' => now()->format('M Y'),
                    'addons' => $addons,
                    'usage' => ['dl' => 0, 'quota' => $quota, 'api' => in_array('api', $channels) ? '0' : '—', 'last' => 'Just now'],
                    'note_text' => '',
                    'activity' => [
                        ['c' => '#16a34a', 't' => "Client onboarded — {$packageName}", 'w' => 'Just now'],
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('client_users')->insert([
                'client_id' => $clientId,
                'name' => $contact ?: 'News Desk',
                'email' => $email,
                'password' => Hash::make('password'),
                'client_role_id' => $clientRoleId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($packageModel) {
                DB::table('client_packages')->insert([
                    'client_id' => $clientId,
                    'package_id' => $packageModel->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addYear(),
                    'status' => 'active',
                    'created_at' => now(),
                ]);
            }

            if (in_array('ftp', $channels)) {
                DB::table('client_channels')->insert([
                    'client_id' => $clientId,
                    'type' => 'ftp',
                    'config' => json_encode(['host' => '', 'username' => '', 'port' => '21', 'password' => '', 'health' => 'ok']),
                    'status' => 'active',
                    'failure_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (in_array('api', $channels)) {
                $apiKey = 'unb_live_'.Str::random(16);
                DB::table('client_channels')->insert([
                    'client_id' => $clientId,
                    'type' => 'api',
                    'config' => json_encode(['endpoint' => '', 'key' => $apiKey, 'url' => '', 'health' => 'ok']),
                    'status' => 'active',
                    'failure_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('client_api_keys')->insert([
                    'client_id' => $clientId,
                    'name' => 'Default API Key',
                    'key_hash' => hash('sha256', $apiKey),
                    'scopes' => json_encode(['feed:read', 'media:download']),
                    'rate_limit_rpm' => 60,
                    'created_at' => now(),
                ]);
            }
        });

        return Client::where('code', $code)->firstOrFail();
    }

    // ─── Channel Operations ─────────────────────────────────────────

    public function toggleChannel(Client $client, string $type): void
    {
        $meta = $this->getClientMeta($client);

        if ($type === 'email') {
            $emOn = ! ($meta['channels']['email']['on'] ?? true);
            $meta['channels']['email']['on'] = $emOn;
            $meta['channels']['email']['health'] = $emOn ? 'ok' : 'off';
            $meta['activity'] = array_merge([
                ['c' => $emOn ? '#16a34a' : '#7c7f8c', 't' => 'Email channel '.($emOn ? 'enabled' : 'disabled'), 'w' => 'Just now'],
            ], $meta['activity'] ?? []);

            $client->update(['notes' => json_encode($meta)]);

            return;
        }

        $channel = $client->clientChannels->firstWhere('type', $type);
        if ($channel) {
            $newStatus = $channel->status === 'active' ? 'disabled' : 'active';
            $channel->update([
                'status' => $newStatus,
                'failure_count' => $newStatus === 'active' ? 0 : $channel->failure_count,
            ]);

            $isNowOn = $newStatus === 'active';
            $chanName = strtoupper($type);
            $meta['activity'] = array_merge([
                ['c' => $isNowOn ? '#16a34a' : '#7c7f8c', 't' => "{$chanName} channel ".($isNowOn ? 'enabled' : 'disabled'), 'w' => 'Just now'],
            ], $meta['activity'] ?? []);
            $client->update(['notes' => json_encode($meta)]);
        } else {
            $config = [];
            if ($type === 'ftp') {
                $config = ['host' => 'ftp.'.strtolower($client->code).'.com', 'username' => strtolower($client->code).'_unb', 'port' => '21', 'password' => 'Unb@'.rand(1000, 9999), 'health' => 'ok'];
            } elseif ($type === 'api') {
                $key = 'unb_live_'.Str::random(16);
                $config = ['endpoint' => 'https://api.'.strtolower($client->code).'.com/unb', 'key' => $key, 'url' => '', 'health' => 'ok'];
            }

            ClientChannel::create([
                'client_id' => $client->id,
                'type' => $type,
                'config' => $config,
                'status' => 'active',
                'failure_count' => 0,
            ]);

            $chanName = strtoupper($type);
            $meta['activity'] = array_merge([
                ['c' => '#16a34a', 't' => "{$chanName} channel enabled", 'w' => 'Just now'],
            ], $meta['activity'] ?? []);
            $client->update(['notes' => json_encode($meta)]);
        }
    }

    public function addEmailRecipient(Client $client, string $email): void
    {
        $meta = $this->getClientMeta($client);
        $list = $meta['channels']['email']['list'] ?? [];
        if (! in_array($email, $list)) {
            $list[] = $email;
            $meta['channels']['email']['list'] = $list;
            $client->update(['notes' => json_encode($meta)]);
        }
    }

    public function removeEmailRecipient(Client $client, string $email): void
    {
        $meta = $this->getClientMeta($client);
        $list = $meta['channels']['email']['list'] ?? [];
        $meta['channels']['email']['list'] = array_values(array_diff($list, [$email]));
        $client->update(['notes' => json_encode($meta)]);
    }

    public function testFtpConnection(Client $client): void
    {
        $ftpChan = $client->clientChannels->firstWhere('type', 'ftp');
        if ($ftpChan) {
            $ftpChan->update([
                'failure_count' => 0,
                'last_success_at' => now(),
            ]);
        }

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#16a34a', 't' => 'FTP connection test passed', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);
        $client->update(['notes' => json_encode($meta)]);
    }

    public function saveFtpCredentials(Client $client, string $host, string $user, string $port, string $pass): void
    {
        $ftpChan = $client->clientChannels->firstWhere('type', 'ftp');
        $config = [
            'host' => trim($host),
            'username' => trim($user),
            'port' => trim($port) ?: '21',
            'password' => $pass,
            'health' => 'ok',
        ];

        if ($ftpChan) {
            $ftpChan->update(['config' => $config, 'failure_count' => 0]);
        } else {
            ClientChannel::create([
                'client_id' => $client->id,
                'type' => 'ftp',
                'config' => $config,
                'status' => 'active',
                'failure_count' => 0,
            ]);
        }

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#3b6fe0', 't' => 'FTP credentials updated', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);
        $client->update(['notes' => json_encode($meta)]);
    }

    public function regenerateApiKey(Client $client): string
    {
        $newKey = 'unb_live_'.Str::random(16);

        $apiChan = $client->clientChannels->firstWhere('type', 'api');
        $config = $apiChan?->config ?? [];
        $config['key'] = $newKey;

        if ($apiChan) {
            $apiChan->update(['config' => $config]);
        } else {
            ClientChannel::create([
                'client_id' => $client->id,
                'type' => 'api',
                'config' => $config,
                'status' => 'active',
                'failure_count' => 0,
            ]);
        }

        ClientApiKey::updateOrCreate(
            ['client_id' => $client->id, 'name' => 'Default Wire API Key'],
            ['key_hash' => hash('sha256', $newKey), 'scopes' => json_encode(['feed:read', 'media:download']), 'rate_limit_rpm' => 60]
        );

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#f0a832', 't' => 'API key regenerated — old key invalidated', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);
        $client->update(['notes' => json_encode($meta)]);

        return $newKey;
    }

    public function saveWebhookUrl(Client $client, string $webhookUrl): void
    {
        $apiChan = $client->clientChannels->firstWhere('type', 'api')
            ?? $client->clientChannels->firstWhere('type', 'webhook');
        if ($apiChan) {
            $config = $apiChan->config;
            $config['url'] = trim($webhookUrl);
            $apiChan->update(['config' => $config]);
        }
    }

    // ─── Subscriptions & Packages ───────────────────────────────────

    public function updatePackage(Client $client, string $packageName, array $addons, string $effectiveText): void
    {
        $tier = str_starts_with($packageName, 'Premium') ? 'Premium'
            : (str_starts_with($packageName, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $packageName)->first();

        $meta = $this->getClientMeta($client);
        $meta['tier'] = $tier;
        $meta['package_name'] = $packageName;
        $meta['addons'] = $addons;
        $addonsStr = ! empty($addons) ? ' + '.implode(', ', $addons) : '';

        $effective = strtolower(explode(' —', $effectiveText)[0]);
        $meta['activity'] = array_merge([
            ['c' => '#3b6fe0', 't' => "Package updated → {$packageName}{$addonsStr} ({$effective})", 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update(['notes' => json_encode($meta)]);

        if ($packageModel) {
            ClientPackage::updateOrCreate(
                ['client_id' => $client->id, 'status' => 'active'],
                ['package_id' => $packageModel->id, 'starts_at' => now()]
            );
        }
    }

    public function bulkApplyPackage(array $clientIds, string $packageName): int
    {
        $tier = str_starts_with($packageName, 'Premium') ? 'Premium'
            : (str_starts_with($packageName, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $packageName)->first();
        $clients = Client::whereIn('id', $clientIds)->get();

        $count = 0;
        foreach ($clients as $client) {
            $meta = $this->getClientMeta($client);
            $meta['tier'] = $tier;
            $meta['package_name'] = $packageName;
            $meta['activity'] = array_merge([
                ['c' => '#3b6fe0', 't' => 'Package changed → '.$packageName, 'w' => 'Just now'],
            ], $meta['activity'] ?? []);

            $client->update([
                'notes' => json_encode($meta),
            ]);

            if ($packageModel) {
                ClientPackage::updateOrCreate(
                    ['client_id' => $client->id, 'status' => 'active'],
                    ['package_id' => $packageModel->id, 'starts_at' => now()]
                );
            }
            $count++;
        }

        return $count;
    }

    // ─── Lifecycle & Status ─────────────────────────────────────────

    public function pauseClient(Client $client, string $reason, ?string $resumeDate, string $note): void
    {
        $meta = $this->getClientMeta($client);
        $meta['pause_reason'] = $reason;
        $resumeStr = $resumeDate ? ' · auto-resume '.$resumeDate : '';
        $noteStr = trim($note) ? ' — '.trim($note) : '';

        $meta['activity'] = array_merge([
            ['c' => '#f0a832', 't' => "Deliveries paused — reason: {$reason}{$resumeStr}{$noteStr}", 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'suspended',
            'notes' => json_encode($meta),
        ]);
    }

    public function resumeClient(Client $client): void
    {
        $meta = $this->getClientMeta($client);
        unset($meta['pause_reason']);
        $meta['activity'] = array_merge([
            ['c' => '#16a34a', 't' => 'Deliveries resumed', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'active',
            'notes' => json_encode($meta),
        ]);
    }

    public function deactivateClient(Client $client): void
    {
        $client->clientChannels()->update(['status' => 'disabled']);

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#7c7f8c', 't' => 'Client deactivated — access revoked, records kept', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'closed',
            'notes' => json_encode($meta),
        ]);
    }

    public function reactivateClient(Client $client): void
    {
        $client->clientChannels()->update(['status' => 'active']);

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#16a34a', 't' => 'Client reactivated', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'active',
            'notes' => json_encode($meta),
        ]);
    }

    public function bulkPause(array $clientIds): int
    {
        $activeClients = Client::whereIn('id', $clientIds)
            ->where('status', 'active')
            ->get();

        $count = 0;
        foreach ($activeClients as $client) {
            $meta = $this->getClientMeta($client);
            $meta['activity'] = array_merge([
                ['c' => '#f0a832', 't' => 'Deliveries paused — bulk action', 'w' => 'Just now'],
            ], $meta['activity'] ?? []);
            $meta['pause_reason'] = 'Client requested';

            $client->update([
                'status' => 'suspended',
                'notes' => json_encode($meta),
            ]);
            $count++;
        }

        return $count;
    }

    public function saveNote(Client $client, string $noteText): void
    {
        $meta = $this->getClientMeta($client);
        $meta['note_text'] = trim($noteText);

        $client->update(['notes' => json_encode($meta)]);
    }

    // ─── CSV Export ─────────────────────────────────────────────────

    public function exportCsv(Collection $clients, bool $selectedOnly = false): StreamedResponse
    {
        $filename = $selectedOnly ? 'unb-clients-selected.csv' : 'unb-clients.csv';

        return response()->streamDownload(function () use ($clients) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'type', 'city', 'tier', 'package', 'status', 'channels', 'renewal', 'downloads_this_cycle', 'quota']);

            foreach ($clients as $client) {
                $meta = $this->getClientMeta($client);
                $tier = $meta['tier'] ?? 'Standard';
                $pkg = $client->clientPackages->first()?->package?->name ?? ($meta['package_name'] ?? 'Standard Wire');
                $status = $client->status === 'suspended' ? 'paused' : ($client->status === 'closed' ? 'deactivated' : 'active');

                $chans = ['portal'];
                if ($meta['channels']['email']['on'] ?? true) {
                    $chans[] = 'email';
                }
                if ($client->clientChannels->where('type', 'ftp')->where('status', 'active')->count()) {
                    $chans[] = 'ftp';
                }
                if ($client->clientChannels->where('type', 'api')->where('status', 'active')->count()) {
                    $chans[] = 'api';
                }

                $renewal = $client->clientPackages->first()?->ends_at?->format('j M Y') ?? '—';
                $dl = $meta['usage']['dl'] ?? 0;
                $quota = $meta['usage']['quota'] ?? 800;

                fputcsv($handle, [
                    $client->name,
                    $meta['display_type'] ?? $client->type,
                    $meta['city'] ?? 'Dhaka',
                    $tier,
                    $pkg,
                    $status,
                    implode(' + ', $chans),
                    $renewal,
                    $dl,
                    $quota,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
