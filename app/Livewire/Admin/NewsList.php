<?php

namespace App\Livewire\Admin;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Repositories\CategoryRepository;
use App\Repositories\StoryRepository;
use App\Services\NoteService;
use App\Services\RbacService;
use App\Services\StoryService;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class NewsList extends Component
{
    use WithPagination;

    public string $language = 'en';

    public string $status = 'all';

    public string $category = 'all';

    public string $search = '';

    public ?int $selectedId = null;

    /** @var list<string> */
    public array $selectedStories = [];

    public bool $selectAll = false;

    public string $noteText = '';

    public ?string $conflictError = null;

    public function mount(string $language = 'en'): void
    {
        $this->language = in_array($language, ['en', 'bn'], true) ? $language : 'en';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedStories = app(StoryRepository::class)
                ->filteredIds($this->language, $this->status, $this->category, $this->search);
        } else {
            $this->selectedStories = [];
        }
    }

    public function select(int $id): void
    {
        $this->openDrawer($id);
    }

    public function openDrawer(int $id): void
    {
        $this->selectedId = $id;
        $this->noteText = '';
        $this->conflictError = null;
    }

    public function closeDrawer(): void
    {
        $this->selectedId = null;
        $this->noteText = '';
        $this->conflictError = null;
    }

    public function takeOver(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $story = app(StoryRepository::class)->findOrFail($this->selectedId);
        $user = auth()->user();
        app(RbacService::class)->assertCan($user, 'stories', 'edit');

        try {
            app(StoryService::class)->takeOver($story, $user, $story->version);
            $this->conflictError = null;
            $this->dispatch('toast', message: 'Ownership taken over successfully.');
        } catch (ConflictHttpException $e) {
            $this->conflictError = 'Version conflict — this story was updated by another user. Please reload.';
        } catch (\Throwable $e) {
            $this->conflictError = $e->getMessage();
        }
    }

    public function addNote(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $this->validate([
            'noteText' => 'required|string|min:1|max:2000',
        ]);

        $story = app(StoryRepository::class)->findOrFail($this->selectedId);
        $user = auth()->user();

        app(NoteService::class)->add($story, $user, $this->noteText);
        $this->noteText = '';
        $this->dispatch('toast', message: 'Note added to newsroom thread.');
    }

    public function togglePublish(int $id): void
    {
        $story = app(StoryRepository::class)->findOrFail($id);
        $user = auth()->user();
        $svc = app(StoryService::class);

        if ($story->status === 'published') {
            app(RbacService::class)->assertCan($user, 'stories', 'edit');
            $svc->transition($story, 'archived', $user);
            $this->dispatch('toast', message: 'Story archived.');
        } else {
            app(RbacService::class)->assertCan($user, 'stories', 'publish');
            if ($story->status === 'draft') {
                $svc->transition($story, 'in_review', $user);
                $story->refresh();
            }
            if ($story->status === 'in_review') {
                $svc->transition($story, 'approved', $user);
                $story->refresh();
            }
            if ($story->status === 'approved') {
                $svc->transition($story, 'published', $user);
                dispatch(new FanoutStory($story->id));
                dispatch(new ProcessIndexOutbox);
            }
            $this->dispatch('toast', message: 'Story published to wire feed.');
        }
    }

    public function deleteStory(int $id): void
    {
        $story = app(StoryRepository::class)->findOrFail($id);
        app(RbacService::class)->assertCan(auth()->user(), 'stories', 'delete');
        $story->delete();
        $this->dispatch('toast', message: 'Story moved to trash.');
    }

    public function bulkPublish(): void
    {
        if (empty($this->selectedStories)) {
            return;
        }

        $user = auth()->user();
        app(RbacService::class)->assertCan($user, 'stories', 'publish');
        $svc = app(StoryService::class);
        $repo = app(StoryRepository::class);

        $stories = $repo->findMany($this->selectedStories);
        foreach ($stories as $story) {
            if ($story->status === 'draft') {
                $svc->transition($story, 'in_review', $user);
                $story->refresh();
            }
            if ($story->status === 'in_review') {
                $svc->transition($story, 'approved', $user);
                $story->refresh();
            }
            if ($story->status === 'approved') {
                $svc->transition($story, 'published', $user);
                dispatch(new FanoutStory($story->id));
                dispatch(new ProcessIndexOutbox);
            }
        }

        $this->selectedStories = [];
        $this->selectAll = false;
        $this->dispatch('toast', message: 'Selected stories published.');
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedStories)) {
            return;
        }

        app(RbacService::class)->assertCan(auth()->user(), 'stories', 'delete');
        app(StoryRepository::class)->deleteMany($this->selectedStories);

        $this->selectedStories = [];
        $this->selectAll = false;
        $this->dispatch('toast', message: 'Selected stories deleted.');
    }

    public function export(): StreamedResponse
    {
        $stories = app(StoryRepository::class)
            ->exportQuery($this->language, $this->status, $this->category, $this->search);
        $fileName = 'unb-stories-'.$this->language.'-'.now()->format('Ymd-His').'.csv';

        return Response::streamDownload(function () use ($stories) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Public ID', 'Headline', 'Category', 'Sub Category', 'Status', 'Views', 'Owner', 'Published At', 'Created At']);
            foreach ($stories as $s) {
                fputcsv($handle, [
                    $s->id,
                    $s->public_id,
                    $s->headline,
                    $s->category?->name_en ?? '',
                    $s->subCategory?->name_en ?? '',
                    $s->status,
                    $s->view_count ?? 0,
                    $s->owner?->name ?? '',
                    $s->published_at?->toIso8601String() ?? '',
                    $s->created_at?->toIso8601String() ?? '',
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $repo = app(StoryRepository::class);

        $stories = $repo->filteredList($this->language, $this->status, $this->category, $this->search);
        $categories = app(CategoryRepository::class)->categoriesTree();
        $selected = $this->selectedId ? $repo->findWithDetails($this->selectedId) : null;
        $counts = $repo->statusCounts($this->language);

        return view('livewire.admin.news-list', compact('stories', 'categories', 'selected', 'counts'));
    }
}
