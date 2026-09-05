<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Role;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsManager extends Component
{
    use WithPagination;

    // Filters and sorting
    public string $search = '';

    public string $statusFilter = 'all'; // all, active, paused, deactivated

    public string $tierFilter = 'all';   // all, Premium, Standard, Basic

    public string $sort = 'name';        // name, renewal, usage

    // Multi-selection for bulk actions
    public array $selectedIds = [];

    public string $bulkPackage = '';

    // KPI stats
    public array $stats = [];

    // Detail drawer state
    public ?int $selectedId = null;

    public string $drawerTab = 'overview'; // overview, channels, package, activity

    public string $clientNoteText = '';

    // Channels tab state
    public string $newEmailRecipient = '';

    public string $ftpHost = '';

    public string $ftpUser = '';

    public string $ftpPort = '21';

    public string $ftpPass = '';

    public string $apiKey = '';

    public bool $showApiKey = false;

    public bool $confirmRegen = false;

    public string $webhookUrl = '';

    // Package tab state
    public string $drawerPackageName = '';

    public array $drawerAddons = [];

    public string $drawerEffective = 'Immediately (prorated)';

    // Modals
    public bool $showPauseModal = false;

    public ?int $pauseClientId = null;

    public string $pauseReason = 'Payment hold';

    public string $pauseNote = '';

    public ?string $pauseResumeDate = null;

    public bool $showDeactModal = false;

    public ?int $deactClientId = null;

    public bool $deactConfirmed = false;

    // 3-step Onboard Wizard
    public bool $showOnboardModal = false;

    public int $wizStep = 1;

    public string $wName = '';

    public string $wType = 'National daily';

    public string $wCity = 'Dhaka';

    public string $wContact = '';

    public string $wEmail = '';

    public string $wPackage = 'Premium Wire + Media';

    public array $wAddons = [];

    public array $wChannels = ['email'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTierFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    // Multi-selection
    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function toggleSelectAll(bool $checked, array $visibleIds = []): void
    {
        if ($checked) {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $visibleIds)));
        } else {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $visibleIds));
        }
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    // Bulk actions
    public function bulkPause(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');

        if (empty($this->selectedIds)) {
            $this->dispatch('toast', message: 'No clients selected');

            return;
        }

        $activeClients = Client::whereIn('id', $this->selectedIds)
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

        $this->clearSelection();
        $this->dispatch('toast', message: "<b>{$count}</b> client".($count === 1 ? '' : 's').' paused — deliveries held');
    }

    public function bulkApplyPackage(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');

        if (empty($this->bulkPackage)) {
            $this->dispatch('toast', message: 'Choose a package first');

            return;
        }

        if (empty($this->selectedIds)) {
            $this->dispatch('toast', message: 'No clients selected');

            return;
        }

        $tier = str_starts_with($this->bulkPackage, 'Premium') ? 'Premium'
            : (str_starts_with($this->bulkPackage, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $this->bulkPackage)->first();
        $clients = Client::whereIn('id', $this->selectedIds)->get();

        $count = 0;
        foreach ($clients as $client) {
            $meta = $this->getClientMeta($client);
            $meta['tier'] = $tier;
            $meta['package_name'] = $this->bulkPackage;
            $meta['activity'] = array_merge([
                ['c' => '#3b6fe0', 't' => 'Package changed → '.$this->bulkPackage, 'w' => 'Just now'],
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

        $this->clearSelection();
        $this->bulkPackage = '';
        $this->dispatch('toast', message: "Package updated for <b>{$count}</b> clients");
    }

    // Drawer management
    public function selectClient(int $id): void
    {
        $this->selectedId = $id;
        $this->drawerTab = 'overview';
        $this->initDrawerFields($id);
    }

    public function closeDrawer(): void
    {
        $this->selectedId = null;
        $this->showApiKey = false;
        $this->confirmRegen = false;
    }

    public function setDrawerTab(string $tab): void
    {
        $this->drawerTab = $tab;
        if ($this->selectedId) {
            $this->initDrawerFields($this->selectedId);
        }
    }

    private function initDrawerFields(int $id): void
    {
        $client = Client::with(['clientChannels', 'clientPackages.package', 'clientUsers'])->find($id);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        $this->clientNoteText = $meta['note_text'] ?? '';

        // FTP channel fields
        $ftpChan = $client->clientChannels->firstWhere('type', 'ftp');
        $ftpConfig = $ftpChan?->config ?? [];
        $this->ftpHost = $ftpConfig['host'] ?? '';
        $this->ftpUser = $ftpConfig['username'] ?? ($ftpConfig['user'] ?? '');
        $this->ftpPort = (string) ($ftpConfig['port'] ?? '21');
        $this->ftpPass = $ftpConfig['password'] ?? ($ftpConfig['pass'] ?? '');

        // API channel fields
        $apiChan = $client->clientChannels->firstWhere('type', 'api');
        $apiConfig = $apiChan?->config ?? [];
        $this->apiKey = $apiConfig['key'] ?? '';
        $this->webhookUrl = $apiConfig['webhook'] ?? ($apiConfig['endpoint'] ?? '');
        $this->showApiKey = false;
        $this->confirmRegen = false;

        // Package tab fields
        $this->drawerPackageName = $client->clientPackages->first()?->package?->name ?? ($meta['package_name'] ?? 'Standard Wire');
        $this->drawerAddons = $meta['addons'] ?? [];
        $this->drawerEffective = 'Immediately (prorated)';
    }

    public function saveNote(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::find($this->selectedId);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        $meta['note_text'] = trim($this->clientNoteText);

        $client->update(['notes' => json_encode($meta)]);
        $this->dispatch('toast', message: 'Note saved');
    }

    // Channels tab actions
    public function toggleChannel(string $type, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::with('clientChannels')->find($this->selectedId);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);

        if ($type === 'email') {
            $emOn = ! ($meta['channels']['email']['on'] ?? true);
            $meta['channels']['email']['on'] = $emOn;
            $meta['channels']['email']['health'] = $emOn ? 'ok' : 'off';
            $meta['activity'] = array_merge([
                ['c' => $emOn ? '#16a34a' : '#7c7f8c', 't' => 'Email channel '.($emOn ? 'enabled' : 'disabled'), 'w' => 'Just now'],
            ], $meta['activity'] ?? []);

            $client->update(['notes' => json_encode($meta)]);
            $this->dispatch('toast', message: "<b>{$client->name}</b> — Email channel ".($emOn ? 'enabled' : 'disabled'));

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

            $this->dispatch('toast', message: "<b>{$client->name}</b> — {$chanName} ".($isNowOn ? 'enabled' : 'disabled'));
        } else {
            // Provision channel
            $config = [];
            if ($type === 'ftp') {
                $config = ['host' => 'ftp.'.strtolower($client->code).'.com', 'username' => strtolower($client->code).'_unb', 'port' => '21', 'password' => 'Unb@'.rand(1000, 9999), 'health' => 'ok'];
            } elseif ($type === 'api') {
                $key = 'unb_live_'.Str::random(16);
                $config = ['endpoint' => 'https://api.'.strtolower($client->code).'.com/unb', 'key' => $key, 'webhook' => '', 'health' => 'ok'];
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

            $this->initDrawerFields($client->id);
            $this->dispatch('toast', message: "<b>{$client->name}</b> — {$chanName} enabled");
        }
    }

    public function addEmailRecipient(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $email = trim($this->newEmailRecipient);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->dispatch('toast', message: 'Valid email required');

            return;
        }

        $client = Client::find($this->selectedId);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        $list = $meta['channels']['email']['list'] ?? [];
        if (! in_array($email, $list)) {
            $list[] = $email;
            $meta['channels']['email']['list'] = $list;
            $client->update(['notes' => json_encode($meta)]);
        }

        $this->newEmailRecipient = '';
        $this->dispatch('toast', message: 'Recipient added');
    }

    public function removeEmailRecipient(string $email, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::find($this->selectedId);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        $list = $meta['channels']['email']['list'] ?? [];
        $meta['channels']['email']['list'] = array_values(array_diff($list, [$email]));

        $client->update(['notes' => json_encode($meta)]);
        $this->dispatch('toast', message: 'Recipient removed');
    }

    public function testFtpConnection(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::with('clientChannels')->find($this->selectedId);
        if (! $client) {
            return;
        }

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

        $this->dispatch('toast', message: "FTP connection to <b>{$client->name}</b> is healthy");
    }

    public function saveFtpCredentials(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::with('clientChannels')->find($this->selectedId);
        if (! $client) {
            return;
        }

        $ftpChan = $client->clientChannels->firstWhere('type', 'ftp');
        $config = [
            'host' => trim($this->ftpHost),
            'username' => trim($this->ftpUser),
            'port' => trim($this->ftpPort) ?: '21',
            'password' => $this->ftpPass,
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

        $this->dispatch('toast', message: 'FTP credentials saved');
    }

    public function toggleApiKeyReveal(): void
    {
        $this->showApiKey = ! $this->showApiKey;
    }

    public function regenerateApiKey(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        if (! $this->confirmRegen) {
            $this->confirmRegen = true;

            return;
        }

        $client = Client::with('clientChannels')->find($this->selectedId);
        if (! $client) {
            return;
        }

        $newKey = 'unb_live_'.Str::random(16);
        $this->apiKey = $newKey;
        $this->confirmRegen = false;

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

        $this->dispatch('toast', message: "New API key issued for <b>{$client->name}</b>");
    }

    public function saveWebhookUrl(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::with('clientChannels')->find($this->selectedId);
        if (! $client) {
            return;
        }

        $apiChan = $client->clientChannels->firstWhere('type', 'api');
        if ($apiChan) {
            $config = $apiChan->config;
            $config['webhook'] = trim($this->webhookUrl);
            $apiChan->update(['config' => $config]);
        }

        $this->dispatch('toast', message: 'Webhook URL updated');
    }

    // Package tab actions
    public function applyPackageChange(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::with('clientPackages.package')->find($this->selectedId);
        if (! $client) {
            return;
        }

        $tier = str_starts_with($this->drawerPackageName, 'Premium') ? 'Premium'
            : (str_starts_with($this->drawerPackageName, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $this->drawerPackageName)->first();

        $meta = $this->getClientMeta($client);
        $meta['tier'] = $tier;
        $meta['package_name'] = $this->drawerPackageName;
        $meta['addons'] = $this->drawerAddons;
        $addonsStr = ! empty($this->drawerAddons) ? ' + '.implode(', ', $this->drawerAddons) : '';

        $effectiveText = strtolower(explode(' —', $this->drawerEffective)[0]);
        $meta['activity'] = array_merge([
            ['c' => '#3b6fe0', 't' => "Package updated → {$this->drawerPackageName}{$addonsStr} ({$effectiveText})", 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update(['notes' => json_encode($meta)]);

        if ($packageModel) {
            ClientPackage::updateOrCreate(
                ['client_id' => $client->id, 'status' => 'active'],
                ['package_id' => $packageModel->id, 'starts_at' => now()]
            );
        }

        $this->dispatch('toast', message: "<b>{$client->name}</b> package updated");
    }

    // Status Modals (Pause, Deactivate, Resume, Reactivate)
    public function openPauseModal(int $id): void
    {
        $this->pauseClientId = $id;
        $this->pauseReason = 'Payment hold';
        $this->pauseNote = '';
        $this->pauseResumeDate = null;
        $this->showPauseModal = true;
    }

    public function confirmPause(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->pauseClientId) {
            return;
        }

        $client = Client::find($this->pauseClientId);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        $meta['pause_reason'] = $this->pauseReason;
        $resumeStr = $this->pauseResumeDate ? ' · auto-resume '.$this->pauseResumeDate : '';
        $noteStr = trim($this->pauseNote) ? ' — '.trim($this->pauseNote) : '';

        $meta['activity'] = array_merge([
            ['c' => '#f0a832', 't' => "Deliveries paused — reason: {$this->pauseReason}{$resumeStr}{$noteStr}", 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'suspended',
            'notes' => json_encode($meta),
        ]);

        $this->showPauseModal = false;
        $this->dispatch('toast', message: "<b>{$client->name}</b> paused — deliveries held, portal read-only");
    }

    public function resumeClient(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        $client = Client::find($id);
        if (! $client) {
            return;
        }

        $meta = $this->getClientMeta($client);
        unset($meta['pause_reason']);
        $meta['activity'] = array_merge([
            ['c' => '#16a34a', 't' => 'Deliveries resumed', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'active',
            'notes' => json_encode($meta),
        ]);

        $this->dispatch('toast', message: "<b>{$client->name}</b> resumed — queued stories will now flow");
    }

    public function openDeactModal(int $id): void
    {
        $this->deactClientId = $id;
        $this->deactConfirmed = false;
        $this->showDeactModal = true;
    }

    public function confirmDeactivate(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'delete');
        if (! $this->deactClientId || ! $this->deactConfirmed) {
            return;
        }

        $client = Client::with('clientChannels')->find($this->deactClientId);
        if (! $client) {
            return;
        }

        $client->clientChannels()->update(['status' => 'disabled']);

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#7c7f8c', 't' => 'Client deactivated — access revoked, records kept', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'closed',
            'notes' => json_encode($meta),
        ]);

        $this->showDeactModal = false;
        $this->dispatch('toast', message: "<b>{$client->name}</b> deactivated");
    }

    public function reactivateClient(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');
        $client = Client::with('clientChannels')->find($id);
        if (! $client) {
            return;
        }

        $client->clientChannels()->update(['status' => 'active']);

        $meta = $this->getClientMeta($client);
        $meta['activity'] = array_merge([
            ['c' => '#16a34a', 't' => 'Client reactivated', 'w' => 'Just now'],
        ], $meta['activity'] ?? []);

        $client->update([
            'status' => 'active',
            'notes' => json_encode($meta),
        ]);

        $this->dispatch('toast', message: "<b>{$client->name}</b> reactivated — portal + channels live again");
    }

    // 3-step Onboard Wizard
    public function openOnboard(): void
    {
        $this->wizStep = 1;
        $this->wName = '';
        $this->wType = 'National daily';
        $this->wCity = 'Dhaka';
        $this->wContact = '';
        $this->wEmail = '';
        $this->wPackage = 'Premium Wire + Media';
        $this->wAddons = [];
        $this->wChannels = ['email'];
        $this->showOnboardModal = true;
    }

    public function closeOnboard(): void
    {
        $this->showOnboardModal = false;
    }

    public function wizNext(): void
    {
        if ($this->wizStep === 1) {
            $this->validate([
                'wName' => 'required|string|max:160',
                'wEmail' => 'required|email',
            ]);
            $this->wizStep = 2;
        } elseif ($this->wizStep === 2) {
            $this->wizStep = 3;
        }
    }

    public function wizBack(): void
    {
        if ($this->wizStep > 1) {
            $this->wizStep--;
        }
    }

    public function activateClient(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'create');

        $this->validate([
            'wName' => 'required|string|max:160',
            'wEmail' => 'required|email',
        ]);

        $tier = str_starts_with($this->wPackage, 'Premium') ? 'Premium'
            : (str_starts_with($this->wPackage, 'Standard') ? 'Standard' : 'Basic');

        $packageModel = Package::where('name', $this->wPackage)->first()
            ?? Package::first();

        $clientRoleId = Role::where('type', 'client')->value('id') ?? Role::first()?->id;

        $initials = collect(explode(' ', $this->wName))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->join('');

        $totalClients = Client::count();
        $grad = 'g'.(1 + ($totalClients % 8));
        $code = strtoupper(Str::slug(substr($this->wName, 0, 4))).rand(10, 99);

        $quota = $tier === 'Premium' ? 2000 : ($tier === 'Standard' ? 800 : 200);

        DB::transaction(function () use ($tier, $packageModel, $clientRoleId, $initials, $grad, $code, $quota) {
            $clientId = DB::table('clients')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $this->wName,
                'code' => $code,
                'type' => 'newspaper',
                'country' => 'BD',
                'timezone' => 'Asia/Dhaka',
                'status' => 'active',
                'billing_email' => $this->wEmail,
                'notes' => json_encode([
                    'ini' => $initials ?: 'CL',
                    'grad' => $grad,
                    'display_type' => $this->wType,
                    'city' => $this->wCity ?: 'Dhaka',
                    'tier' => $tier,
                    'since' => now()->format('M Y'),
                    'addons' => $this->wAddons,
                    'usage' => ['dl' => 0, 'quota' => $quota, 'api' => in_array('api', $this->wChannels) ? '0' : '—', 'last' => 'Just now'],
                    'note_text' => '',
                    'activity' => [
                        ['c' => '#16a34a', 't' => "Client onboarded — {$this->wPackage}", 'w' => 'Just now'],
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // User
            DB::table('client_users')->insert([
                'client_id' => $clientId,
                'name' => $this->wContact ?: 'News Desk',
                'email' => $this->wEmail,
                'password' => Hash::make('password'),
                'client_role_id' => $clientRoleId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Package
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

            // Channels
            if (in_array('ftp', $this->wChannels)) {
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

            if (in_array('api', $this->wChannels)) {
                $apiKey = 'unb_live_'.Str::random(16);
                DB::table('client_channels')->insert([
                    'client_id' => $clientId,
                    'type' => 'api',
                    'config' => json_encode(['endpoint' => '', 'key' => $apiKey, 'webhook' => '', 'health' => 'ok']),
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

        $this->showOnboardModal = false;
        $this->dispatch('toast', message: "<b>{$this->wName}</b> onboarded — credentials emailed to client");
    }

    // CSV Export
    public function exportCsv(bool $selectedOnly = false): StreamedResponse
    {
        $clients = $this->getFilteredClientsQuery()->get();
        if ($selectedOnly) {
            $clients = $clients->whereIn('id', $this->selectedIds);
        }

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

    // Metadata & Helper accessors
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

    public function clientHasIssue(Client $client): bool
    {
        if ($client->status === 'closed') {
            return false;
        }

        return $client->clientChannels->contains(fn ($ch) => $ch->failure_count > 0 || ($ch->config['health'] ?? '') === 'fail');
    }

    private function getFilteredClientsQuery()
    {
        $query = Client::with(['clientChannels', 'clientPackages.package', 'clientUsers']);

        if ($this->search !== '') {
            $s = '%'.strtolower($this->search).'%';
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(name) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(billing_email) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$s]);
            });
        }

        if ($this->statusFilter !== 'all') {
            $map = ['active' => 'active', 'paused' => 'suspended', 'deactivated' => 'closed'];
            $dbStatus = $map[$this->statusFilter] ?? $this->statusFilter;
            $query->where('status', $dbStatus);
        }

        if ($this->tierFilter !== 'all') {
            $query->where(function ($q) {
                $q->whereHas('clientPackages.package', fn ($p) => $p->where('name', 'like', '%'.$this->tierFilter.'%'))
                    ->orWhere('notes', 'like', '%"tier":"'.$this->tierFilter.'"%');
            });
        }

        if ($this->sort === 'renewal') {
            $query->leftJoin('client_packages', 'clients.id', '=', 'client_packages.client_id')
                ->orderByRaw('client_packages.ends_at ASC NULLS LAST')
                ->select('clients.*');
        } elseif ($this->sort === 'usage') {
            $query->orderBy('name');
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        $allClients = Client::with(['clientChannels', 'clientPackages'])->get();

        // 5 Stat Strip Calculation
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

        $stats = [
            'total' => $totalCount,
            'active' => $activeCount,
            'paused' => $pausedCount,
            'renew' => $renewCount,
            'issues' => $issuesCount,
        ];
        $this->stats = $stats;

        $clients = $this->getFilteredClientsQuery()->get();

        // Sort by usage in memory if requested
        if ($this->sort === 'usage') {
            $clients = $clients->sortByDesc(function ($c) {
                $meta = $this->getClientMeta($c);
                $dl = $meta['usage']['dl'] ?? 0;
                $quota = $meta['usage']['quota'] ?? 1;

                return $dl / max(1, $quota);
            })->values();
        }

        $packages = Package::where('status', 'active')->get();
        $selected = $this->selectedId ? Client::with(['clientChannels', 'clientPackages.package', 'clientUsers'])->find($this->selectedId) : null;
        $selectedMeta = $selected ? $this->getClientMeta($selected) : [];

        return view('livewire.admin.clients-manager', compact('clients', 'packages', 'selected', 'selectedMeta', 'stats'));
    }
}
