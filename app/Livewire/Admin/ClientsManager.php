<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Role;
use App\Services\ClientService;
use App\Services\PortalAccountService;
use App\Services\RbacService;
use Illuminate\Contracts\View\View;
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

    // Portal user invite modal
    public bool $showPortalInviteModal = false;

    public string $portalInviteName = '';

    public string $portalInviteEmail = '';

    public string $portalInviteRoleId = '';

    // ─── Pagination Reset Hooks ─────────────────────────────────────

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

    // ─── Multi-selection ────────────────────────────────────────────

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

    // ─── Bulk Actions ───────────────────────────────────────────────

    public function bulkPause(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'edit');

        if (empty($this->selectedIds)) {
            $this->dispatch('toast', message: 'No clients selected');

            return;
        }

        $count = app(ClientService::class)->bulkPause($this->selectedIds);

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

        $count = app(ClientService::class)->bulkApplyPackage($this->selectedIds, $this->bulkPackage);

        $this->clearSelection();
        $this->bulkPackage = '';
        $this->dispatch('toast', message: "Package updated for <b>{$count}</b> clients");
    }

    // ─── Drawer Management ──────────────────────────────────────────

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
        $client = Client::with(['clientChannels', 'clientPackages.package', 'clientUsers.clientRole'])->find($id);
        if (! $client) {
            return;
        }

        $service = app(ClientService::class);
        $meta = $service->getClientMeta($client);
        $this->clientNoteText = $meta['note_text'] ?? '';

        // FTP channel fields
        $ftpChan = $client->clientChannels->firstWhere('type', 'ftp');
        $ftpConfig = $ftpChan?->config ?? [];
        $this->ftpHost = $ftpConfig['host'] ?? '';
        $this->ftpUser = $ftpConfig['username'] ?? ($ftpConfig['user'] ?? '');
        $this->ftpPort = (string) ($ftpConfig['port'] ?? '21');
        $this->ftpPass = $ftpConfig['password'] ?? ($ftpConfig['pass'] ?? '');

        // API channel fields
        $apiChan = $client->clientChannels->firstWhere('type', 'api') ?? $client->clientChannels->firstWhere('type', 'webhook');
        $apiConfig = $apiChan?->config ?? [];
        $this->apiKey = $apiConfig['key'] ?? '';
        $this->webhookUrl = $apiConfig['url'] ?? ($apiConfig['webhook'] ?? ($apiConfig['endpoint'] ?? ''));
        $this->showApiKey = false;
        $this->confirmRegen = false;

        // Package tab fields
        $this->drawerPackageName = $client->clientPackages->first()?->package?->name ?? ($meta['package_name'] ?? 'Standard Wire');
        $this->drawerAddons = $meta['addons'] ?? [];
        $this->drawerEffective = 'Immediately (prorated)';
    }

    // ─── Note & Channel Operations ──────────────────────────────────

    public function saveNote(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');
        if (! $this->selectedId) {
            return;
        }

        $client = Client::find($this->selectedId);
        if (! $client) {
            return;
        }

        app(ClientService::class)->saveNote($client, $this->clientNoteText);
        $this->dispatch('toast', message: 'Note saved');
    }

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

        $service = app(ClientService::class);
        $meta = $service->getClientMeta($client);
        $emOnBefore = $meta['channels']['email']['on'] ?? true;
        $hadFtp = $client->clientChannels->firstWhere('type', 'ftp') !== null;
        $hadApi = $client->clientChannels->firstWhere('type', 'api') !== null;

        $service->toggleChannel($client, $type);

        // Re-fetch for toast message
        $client->refresh();
        if ($type === 'email') {
            $newMeta = $service->getClientMeta($client);
            $emOn = $newMeta['channels']['email']['on'] ?? true;
            $this->dispatch('toast', message: "<b>{$client->name}</b> — Email channel ".($emOn ? 'enabled' : 'disabled'));
        } else {
            $chanName = strtoupper($type);
            $channel = $client->clientChannels->firstWhere('type', $type);
            if ($channel) {
                $isNowOn = $channel->status === 'active';
                $this->dispatch('toast', message: "<b>{$client->name}</b> — {$chanName} ".($isNowOn ? 'enabled' : 'disabled'));
            } else {
                $this->dispatch('toast', message: "<b>{$client->name}</b> — {$chanName} enabled");
            }
        }

        $this->initDrawerFields($client->id);
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

        app(ClientService::class)->addEmailRecipient($client, $email);

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

        app(ClientService::class)->removeEmailRecipient($client, $email);
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

        app(ClientService::class)->testFtpConnection($client);
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

        app(ClientService::class)->saveFtpCredentials(
            $client, $this->ftpHost, $this->ftpUser, $this->ftpPort, $this->ftpPass
        );

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

        $newKey = app(ClientService::class)->regenerateApiKey($client);

        $this->apiKey = $newKey;
        $this->confirmRegen = false;

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

        app(ClientService::class)->saveWebhookUrl($client, $this->webhookUrl);
        $this->dispatch('toast', message: 'Webhook URL updated');
    }

    // ─── Package Tab ────────────────────────────────────────────────

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

        app(ClientService::class)->updatePackage(
            $client, $this->drawerPackageName, $this->drawerAddons, $this->drawerEffective
        );

        $this->dispatch('toast', message: "<b>{$client->name}</b> package updated");
    }

    // ─── Status Modals ──────────────────────────────────────────────

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

        app(ClientService::class)->pauseClient($client, $this->pauseReason, $this->pauseResumeDate, $this->pauseNote);

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

        app(ClientService::class)->resumeClient($client);
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

        app(ClientService::class)->deactivateClient($client);

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

        app(ClientService::class)->reactivateClient($client);
        $this->dispatch('toast', message: "<b>{$client->name}</b> reactivated — portal + channels live again");
    }

    // ─── 3-step Onboard Wizard ──────────────────────────────────────

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

        app(ClientService::class)->onboardClient(
            $this->wName, $this->wEmail, $this->wType, $this->wCity,
            $this->wContact, $this->wPackage, $this->wAddons, $this->wChannels
        );

        $this->showOnboardModal = false;
        $this->dispatch('toast', message: "<b>{$this->wName}</b> onboarded — credentials emailed to client");
    }

    // ─── CSV Export ─────────────────────────────────────────────────

    public function exportCsv(bool $selectedOnly = false): StreamedResponse
    {
        $service = app(ClientService::class);
        $clients = $service->getFilteredClientsQuery(
            $this->search, $this->statusFilter, $this->tierFilter, $this->sort
        )->get();

        if ($selectedOnly) {
            $clients = $clients->whereIn('id', $this->selectedIds);
        }

        return $service->exportCsv($clients, $selectedOnly);
    }

    // ─── View Proxy Methods ──────────────────────────────────────────

    public function getClientMeta(Client $client): array
    {
        return app(ClientService::class)->getClientMeta($client);
    }

    public function clientHasIssue(Client $client): bool
    {
        return app(ClientService::class)->clientHasIssue($client);
    }

    // ─── Portal User Management ─────────────────────────────────────

    public function getClientRolesProperty(): array
    {
        return Role::where('type', 'client')->pluck('name', 'id')->toArray();
    }

    public function openPortalInviteModal(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');
        $this->portalInviteName = '';
        $this->portalInviteEmail = '';
        $this->portalInviteRoleId = '';
        $this->showPortalInviteModal = true;
    }

    public function closePortalInviteModal(): void
    {
        $this->showPortalInviteModal = false;
        $this->portalInviteName = '';
        $this->portalInviteEmail = '';
        $this->portalInviteRoleId = '';
    }

    public function invitePortalUser(PortalAccountService $svc): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');

        if (! $this->selectedId) {
            return;
        }

        $this->validate([
            'portalInviteName' => 'required|string|max:160',
            'portalInviteEmail' => 'required|email|unique:client_users,email',
        ]);

        $client = Client::find($this->selectedId);
        if (! $client) {
            return;
        }

        $svc->invite($client, [
            'name' => $this->portalInviteName,
            'email' => $this->portalInviteEmail,
            'client_role_id' => $this->portalInviteRoleId ?: null,
        ]);

        $this->closePortalInviteModal();
        $this->initDrawerFields($this->selectedId);
        $this->dispatch('toast', message: 'Portal invite sent to <b>'.e($this->portalInviteEmail).'</b>');
    }

    public function deactivatePortalUser(int $userId): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');

        $user = ClientUser::findOrFail($userId);
        app(PortalAccountService::class)->deactivate($user);

        if ($this->selectedId) {
            $this->initDrawerFields($this->selectedId);
        }
        $this->dispatch('toast', message: '<b>'.e($user->name).'</b> deactivated');
    }

    public function reactivatePortalUser(int $userId): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');

        $user = ClientUser::findOrFail($userId);
        app(PortalAccountService::class)->reactivate($user);

        if ($this->selectedId) {
            $this->initDrawerFields($this->selectedId);
        }
        $this->dispatch('toast', message: '<b>'.e($user->name).'</b> reactivated');
    }

    public function resendPortalInvite(int $userId): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');

        $user = ClientUser::findOrFail($userId);
        app(PortalAccountService::class)->resendInvite($user);

        $this->dispatch('toast', message: 'Invite resent to <b>'.e($user->name).'</b>');
    }

    public function updatePortalUserRole(int $userId, string $roleId): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'clients', 'edit');

        $user = ClientUser::findOrFail($userId);
        app(PortalAccountService::class)->updateRole($user, (int) $roleId);

        if ($this->selectedId) {
            $this->initDrawerFields($this->selectedId);
        }
        $this->dispatch('toast', message: '<b>'.e($user->name).'</b> role updated');
    }

    // ─── Render ─────────────────────────────────────────────────────

    public function render(): View
    {
        $service = app(ClientService::class);

        $this->stats = $service->computeStats();

        $clients = $service->getFilteredClientsQuery(
            $this->search, $this->statusFilter, $this->tierFilter, $this->sort
        )->get();

        // Sort by usage in memory if requested
        if ($this->sort === 'usage') {
            $clients = $clients->sortByDesc(function ($c) use ($service) {
                $meta = $service->getClientMeta($c);
                $dl = $meta['usage']['dl'] ?? 0;
                $quota = $meta['usage']['quota'] ?? 1;

                return $dl / max(1, $quota);
            })->values();
        }

        $packages = Package::where('status', 'active')->get();
        $selected = $this->selectedId ? Client::with(['clientChannels', 'clientPackages.package', 'clientUsers.clientRole'])->find($this->selectedId) : null;
        $selectedMeta = $selected ? $service->getClientMeta($selected) : [];

        return view('livewire.admin.clients-manager', compact('clients', 'packages', 'selected', 'selectedMeta'));
    }
}
