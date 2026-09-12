<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Validate;
use Livewire\Component;

class Preferences extends Component
{
    public string $timezone = 'Asia/Dhaka';

    public string $desk = 'English desk';

    #[Validate('required|in:dmy,mdy,iso')]
    public string $dateFormat = 'dmy';

    #[Validate('required|in:comfortable,compact')]
    public string $density = 'comfortable';

    public function save(): void
    {
        $this->validate();

        auth()->user()->update([
            'timezone' => $this->timezone,
            'desk' => $this->desk,
            'date_format' => $this->dateFormat,
            'density' => $this->density,
        ]);

        $this->dispatch('toast', message: 'Preferences saved');
    }

    public function mount(): void
    {
        $this->timezone = auth()->user()->timezone ?? 'Asia/Dhaka';
        $this->desk = auth()->user()->desk ?? 'English desk';
        $this->dateFormat = auth()->user()->date_format ?? 'dmy';
        $this->density = auth()->user()->density ?? 'comfortable';
    }

    public function render()
    {
        return view('livewire.admin.preferences');
    }
}
