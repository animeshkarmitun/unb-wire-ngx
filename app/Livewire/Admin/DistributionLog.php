<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Repositories\DeliveryRepository;
use App\Services\RbacService;
use Livewire\Component;
use Livewire\WithPagination;

class DistributionLog extends Component
{
    use WithPagination;

    public string $status = 'all';

    public string $search = '';

    public string $clientId = '';

    public string $channelType = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'distribution', 'view');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClientId(): void
    {
        $this->resetPage();
    }

    public function updatedChannelType(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'distribution', 'edit');

        app(DeliveryRepository::class)->markQueued($id);
        $this->dispatch('toast', message: 'Queued for retry');
    }

    public function render()
    {
        $repo = app(DeliveryRepository::class);
        $deliveries = $repo->paginateWithFilters(
            $this->status,
            $this->search,
            $this->clientId !== '' ? (int) $this->clientId : null,
            $this->channelType,
            $this->dateFrom !== '' ? $this->dateFrom : null,
            $this->dateTo !== '' ? $this->dateTo.' 23:59:59' : null,
        );
        $stats = $repo->getDistributionCounts();
        $clients = Client::orderBy('name')->get(['id', 'name']);

        return view('livewire.admin.distribution-log', compact('deliveries', 'stats', 'clients'));
    }
}
