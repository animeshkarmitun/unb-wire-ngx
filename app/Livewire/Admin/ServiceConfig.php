<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\RbacService;
use Livewire\Component;

class ServiceConfig extends Component
{
    public string $service = 'en';

    public string $wireName = '';

    public string $description = '';

    public bool $enabled = true;

    public function mount(string $service = 'en'): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'settings', 'view');

        $this->service = $service;
        $s = Setting::where('key', 'service.'.$service)->first();
        if ($s) {
            $v = $s->value;
            $this->wireName = $v['wireName'] ?? '';
            $this->description = $v['description'] ?? '';
            $this->enabled = $v['enabled'] ?? true;
        }
    }

    public function save(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'settings', 'edit');

        Setting::updateOrCreate(['key' => 'service.'.$this->service], ['value' => ['wireName' => $this->wireName, 'description' => $this->description, 'enabled' => $this->enabled], 'updated_by' => auth()->id(), 'updated_at' => now()]);
        $this->dispatch('toast', message: 'Service config saved');
    }

    public function render()
    {
        return view('livewire.admin.service-config');
    }
}
