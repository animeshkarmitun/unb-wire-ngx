<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class Preferences extends Component
{
    public string $timezone = 'Asia/Dhaka';

    public string $desk = 'English desk';

    public function save(): void
    {
        auth()->user()->update(['timezone' => $this->timezone, 'desk' => $this->desk]);
        $this->dispatch('toast', message: 'Preferences saved');
    }

    public function mount(): void
    {
        $this->timezone = auth()->user()->timezone ?? 'Asia/Dhaka';
        $this->desk = auth()->user()->desk ?? 'English desk';
    }

    public function render()
    {
        return view('livewire.admin.preferences');
    }
}
