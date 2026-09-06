<?php

namespace App\Livewire\Admin;

use App\Models\Delivery;
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
        Delivery::where('id', $id)->update(['status' => 'queued', 'attempt_count' => 0]);
        $this->dispatch('toast', message: 'Queued for retry');
    }

    public function render()
    {
        $q = Delivery::with(['client', 'channel'])->orderByDesc('created_at');
        if ($this->status !== 'all') {
            $q->where('status', $this->status);
        }
        if ($this->search !== '') {
            $q->where('payload_hash', 'like', '%'.$this->search.'%');
        }
        $deliveries = $q->paginate(20);
        $stats = ['total' => Delivery::count(), 'failed' => Delivery::where('status', 'failed')->count(), 'delivered' => Delivery::where('status', 'delivered')->count()];

        return view('livewire.admin.distribution-log', compact('deliveries', 'stats'));
    }
}
