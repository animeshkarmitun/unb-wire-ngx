<?php

namespace App\Livewire\Admin;

use App\Models\Story;
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

    public function mount(string $publicId): void
    {
        $this->story = Story::with([
            'category',
            'subCategory',
            'owner',
            'assignedEditor',
            'creator',
            'tags',
            'media',
            'versions.creator',
            'notes.user',
            'events.actor',
        ])->where('public_id', $publicId)->firstOrFail();
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

        $this->story->refresh()->load([
            'category',
            'subCategory',
            'owner',
            'assignedEditor',
            'creator',
            'tags',
            'media',
            'versions.creator',
            'notes.user',
            'events.actor',
        ]);

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
        $related = Story::with(['category', 'media'])
            ->where('id', '!=', $this->story->id)
            ->where('status', 'published')
            ->when($this->story->category_id, fn ($q) => $q->where('category_id', $this->story->category_id))
            ->latest('published_at')
            ->limit(3)
            ->get();

        if ($related->count() < 3) {
            $fallback = Story::with(['category', 'media'])
                ->where('id', '!=', $this->story->id)
                ->where('status', 'published')
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('published_at')
                ->limit(3 - $related->count())
                ->get();

            $related = $related->concat($fallback);
        }

        return $related;
    }

    public function getLatestRailProperty(): Collection
    {
        return Story::with(['category', 'media'])
            ->where('id', '!=', $this->story->id)
            ->where('status', 'published')
            ->where('language', $this->story->language)
            ->latest('published_at')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.story-view');
    }
}
