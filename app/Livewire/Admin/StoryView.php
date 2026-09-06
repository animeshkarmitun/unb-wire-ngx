<?php

namespace App\Livewire\Admin;

use App\Models\Story;
use Livewire\Component;

class StoryView extends Component
{
    public Story $story;

    public function mount(string $publicId): void
    {
        $this->story = Story::with(['category', 'owner'])->where('public_id', $publicId)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.admin.story-view');
    }
}
