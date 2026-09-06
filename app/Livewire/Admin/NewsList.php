<?php

namespace App\Livewire\Admin;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Category;
use App\Models\Story;
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
            $query = Story::where('language', $this->language);
            if ($this->status !== 'all') {
                $query->where('status', $this->status);
            }
            if ($this->category !== 'all') {
                $query->where('category_id', $this->category);
            }
            if ($this->search !== '') {
                $query->where(function ($q) {
                    $q->where('headline', 'like', '%'.$this->search.'%')
                        ->orWhere('brief', 'like', '%'.$this->search.'%');
                });
            }
            $pageIds = $query->orderByDesc('updated_at')->paginate(15)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
            $this->selectedStories = $pageIds;
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

        $story = Story::findOrFail($this->selectedId);
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

        $story = Story::findOrFail($this->selectedId);
        $user = auth()->user();

        app(NoteService::class)->add($story, $user, $this->noteText);
        $this->noteText = '';
        $this->dispatch('toast', message: 'Note added to newsroom thread.');
    }

    public function togglePublish(int $id): void
    {
        $story = Story::findOrFail($id);
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
        $story = Story::findOrFail($id);
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

        $stories = Story::whereIn('id', $this->selectedStories)->get();
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
        Story::whereIn('id', $this->selectedStories)->delete();

        $this->selectedStories = [];
        $this->selectAll = false;
        $this->dispatch('toast', message: 'Selected stories deleted.');
    }

    public function export(): StreamedResponse
    {
        $query = Story::with(['category', 'subCategory', 'owner'])->where('language', $this->language);
        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }
        if ($this->category !== 'all') {
            $query->where('category_id', $this->category);
        }
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('headline', 'like', '%'.$this->search.'%')
                    ->orWhere('brief', 'like', '%'.$this->search.'%');
            });
        }

        $stories = $query->orderByDesc('updated_at')->get();
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
        $query = Story::with([
            'category',
            'subCategory',
            'owner.role',
            'assignedEditor',
            'lockedBy',
            'notes.user.role',
        ])->where('language', $this->language);

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }
        if ($this->category !== 'all') {
            $query->where('category_id', $this->category);
        }
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('headline', 'like', '%'.$this->search.'%')
                    ->orWhere('brief', 'like', '%'.$this->search.'%');
            });
        }

        $stories = $query->orderByDesc('updated_at')->paginate(15);
        $categories = Category::whereNull('parent_id')->with('children')->orderBy('sort_order')->get();

        $selected = $this->selectedId
            ? Story::with([
                'category',
                'subCategory',
                'owner.role',
                'assignedEditor',
                'lockedBy',
                'notes.user.role',
                'events.actor',
            ])->find($this->selectedId)
            : null;

        $counts = [
            'all' => Story::where('language', $this->language)->count(),
            'published' => Story::where('language', $this->language)->where('status', 'published')->count(),
            'draft' => Story::where('language', $this->language)->where('status', 'draft')->count(),
            'in_review' => Story::where('language', $this->language)->where('status', 'in_review')->count(),
            'changes_requested' => Story::where('language', $this->language)->where('status', 'changes_requested')->count(),
        ];

        return view('livewire.admin.news-list', compact('stories', 'categories', 'selected', 'counts'));
    }
}
