<?php

namespace App\Livewire\Admin;

use App\Models\Story;
use App\Models\StoryEvent;
use App\Repositories\StoryRepository;
use App\Services\NoteService;
use App\Services\RbacService;
use App\Services\StoryService;
use Illuminate\Support\Collection;
use Livewire\Component;

class StoryView extends Component
{
    public Story $story;

    public string $newNote = '';

    protected array $rules = [
        'newNote' => 'required|string|min:2|max:2000',
    ];

    public function mount(string $publicId, StoryRepository $stories): void
    {
        $this->story = $stories->findWithAllRelations(
            Story::where('public_id', $publicId)->firstOrFail()->id
        );
    }

    public function getCanViewHistoryProperty(): bool
    {
        return app(RbacService::class)->can(auth()->user(), 'history', 'view');
    }

    public function addNote(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'stories', 'edit');
        $this->validate([
            'newNote' => 'required|string|min:1|max:2000',
        ]);

        app(NoteService::class)->add($this->story, auth()->user(), $this->newNote, 'note');

        $this->newNote = '';
        $this->dispatch('toast', message: 'Internal note recorded in thread.');
    }

    public function transitionStatus(string $toStatus): void
    {
        $action = ($toStatus === 'published') ? 'publish' : 'edit';
        app(RbacService::class)->assertCan(auth()->user(), 'stories', $action);

        app(StoryService::class)->transition($this->story, $toStatus, auth()->user());

        $this->story = app(StoryRepository::class)->findWithAllRelations($this->story->id);

        $this->dispatch('toast', message: 'Status updated to '.ucfirst(str_replace('_', ' ', $toStatus)));
    }

    public function getFeaturedMediaProperty()
    {
        return $this->story->media->firstWhere('pivot.role', 'featured')
            ?? $this->story->media->firstWhere('kind', 'image')
            ?? $this->story->media->first();
    }

    public function getReadingTimeProperty(): int
    {
        $words = $this->story->word_count ?: str_word_count(strip_tags($this->story->body_html ?? ''));

        return max(1, (int) ceil($words / 200));
    }

    public function getRelatedStoriesProperty(): Collection
    {
        return app(StoryRepository::class)->relatedStories($this->story->id, $this->story->category_id, 3);
    }

    public function getLatestRailProperty(): Collection
    {
        return app(StoryRepository::class)->latestRail($this->story->language, 5, $this->story->id);
    }

    public function render()
    {
        return view('livewire.admin.story-view');
    }
}
