<?php

namespace App\Livewire\Admin;

use App\Models\Story;
use App\Repositories\StoryRepository;
use App\Services\NoteService;
use App\Services\RbacService;
use App\Services\RevisionService;
use App\Services\StoryService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StoryView extends Component
{
    public Story $story;

    public string $newNote = '';

    public ?int $diffA = null;

    public ?int $diffB = null;

    public ?array $diffResult = null;

    public ?int $restoreTarget = null;

    public bool $showRestoreConfirm = false;

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

    public function getCanEditStoryProperty(): bool
    {
        return app(RbacService::class)->can(auth()->user(), 'stories', 'edit');
    }

    public function compareVersions(): void
    {
        if (! $this->diffA || ! $this->diffB || $this->diffA === $this->diffB) {
            $this->dispatch('toast', message: 'Select two different versions to compare');

            return;
        }
        $a = $this->story->versions()->where('version', $this->diffA)->first();
        $b = $this->story->versions()->where('version', $this->diffB)->first();
        if (! $a || ! $b) {
            $this->dispatch('toast', message: 'Version not found');

            return;
        }
        $this->diffResult = app(RevisionService::class)->diff($a, $b);
    }

    public function clearDiff(): void
    {
        $this->diffA = null;
        $this->diffB = null;
        $this->diffResult = null;
    }

    public function requestRestore(int $version): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'stories', 'edit');
        $this->restoreTarget = $version;
        $this->showRestoreConfirm = true;
    }

    public function confirmRestore(): void
    {
        if (! $this->restoreTarget) {
            return;
        }
        try {
            app(RevisionService::class)->restore(
                $this->story,
                $this->restoreTarget,
                auth()->user(),
                $this->story->version,
            );
            $this->story = app(StoryRepository::class)->findWithAllRelations($this->story->id);
            $this->showRestoreConfirm = false;
            $this->restoreTarget = null;
            $this->diffResult = null;
            $this->diffA = null;
            $this->diffB = null;
            $this->dispatch('toast', message: 'Story restored to selected version');
        } catch (ConflictHttpException $e) {
            $this->showRestoreConfirm = false;
            $this->dispatch('toast', message: 'Version conflict — story was modified since you loaded it. Refresh and try again.');
        } catch (\Throwable $e) {
            $this->showRestoreConfirm = false;
            $this->dispatch('toast', message: $e->getMessage());
        }
    }

    public function cancelRestore(): void
    {
        $this->showRestoreConfirm = false;
        $this->restoreTarget = null;
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
        $this->story->loadMissing('versions.creator', 'events.actor', 'notes.user', 'tags', 'media', 'category', 'owner');

        return view('livewire.admin.story-view');
    }
}
