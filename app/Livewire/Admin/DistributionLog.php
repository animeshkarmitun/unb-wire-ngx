<?php

namespace App\Livewire\Admin;

use App\Repositories\DeliveryRepository;
use Livewire\Component;
use Livewire\WithPagination;

class DistributionLog extends Component
{
    use WithPagination;

    public string $status = 'all';

    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        app(DeliveryRepository::class)->markQueued($id);
        $this->dispatch('toast', message: 'Queued for retry');
    }

    public function render()
    {
        $repo = app(DeliveryRepository::class);
        $deliveries = $repo->paginateWithFilters($this->status, $this->search);
        $stats = $repo->getDistributionCounts();

        return view('livewire.admin.distribution-log', compact('deliveries', 'stats'));
    }
}
