<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\AuditQueryService;
use App\Services\RbacService;
use Livewire\Component;

class AuditLogBrowser extends Component
{
    public string $filterAction = '';

    public string $filterEntityType = '';

    public string $filterEntityId = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 25;

    public function mount(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'audit', 'view');
        $this->filterEntityType = request()->query('entity_type', '');
        $this->filterEntityId = request()->query('entity_id', '');
    }

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEntityType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEntityId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateTo(): void
    {
        $this->resetPage();
    }

    private function resetPage(): void
    {
        $this->dispatch('auditResetPage');
    }

    public function getResultsProperty()
    {
        return app(AuditQueryService::class)->search(
            actorId: null,
            action: $this->filterAction ?: null,
            entityType: $this->filterEntityType ?: null,
            entityId: $this->filterEntityId ? (int) $this->filterEntityId : null,
            dateFrom: $this->filterDateFrom ?: null,
            dateTo: $this->filterDateTo ?: null,
            perPage: $this->perPage,
        );
    }

    public function render()
    {
        // COS-67: resolve actor names for the current page (no "User #N" labels).
        $results = $this->results;
        $ids = $results->getCollection()->pluck('actor_id')->filter()->unique();
        $actorNames = $ids->isEmpty()
            ? []
            : User::whereIn('id', $ids)->pluck('name', 'id')->all();

        return view('livewire.admin.audit-log-browser', [
            'actorNames' => $actorNames,
        ])->layout('components.layouts.admin');
    }
}
