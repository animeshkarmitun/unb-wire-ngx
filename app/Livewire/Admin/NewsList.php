<?php

namespace App\Livewire\Admin;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Repositories\CategoryRepository;
use App\Repositories\StoryRepository;
use App\Services\NoteService;
use App\Services\RbacService;
use App\Services\StoryFilterService;
use App\Services\StoryService;
use App\Support\StoryFilter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
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

    public string $searchScope = 'all';

    public string $authorId = 'any';

    public array $tagIds = [];

    public string $dateField = 'any';

    public string $dateMode = 'any';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $hasImage = 'any';

    public bool $showFilters = false;

    public string $sortField = 'updated_at';

    public string $sortDir = 'desc';

    public ?int $selectedId = null;

    /** @var list<string> */
    public array $selectedStories = [];

    public bool $selectAll = false;

    public string $noteText = '';

    public ?string $conflictError = null;

    public ?int $selectedVersion = null;

    public $gotoPageNum = null;

    public function gotoInput(): void
    {
        $page = (int) ($this->gotoPageNum ?? 0);

        if ($page < 1) {
            return;
        }

        $total = app(StoryRepository::class)->filteredCount($this->filter());
        $lastPage = max(1, (int) ceil($total / 15));

        $this->gotoPage(min($page, $lastPage));
        $this->gotoPageNum = null;
    }

    public function mount(string $language = 'en'): void
    {
        $this->language = in_array($language, ['en', 'bn'], true) ? $language : 'en';
    }

    public function updated(string $name): void
    {
        $prop = Str::before($name, '.');

        if (in_array($prop, [
            'search', 'status', 'category', 'searchScope', 'authorId', 'tagIds',
            'dateField', 'dateMode', 'dateFrom', 'dateTo', 'hasImage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = in_array($field, ['published', 'views'], true) ? 'desc' : 'asc';
        }
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedStories = app(StoryRepository::class)->filteredIds($this->filter());
        } else {
            $this->selectedStories = [];
        }
    }

    public function toggleTag(int $tagId): void
    {
        if (in_array($tagId, $this->tagIds, true)) {
            $this->tagIds = array_values(array_diff($this->tagIds, [$tagId]));
        } else {
            $this->tagIds[] = $tagId;
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->searchScope = 'all';
        $this->authorId = 'any';
        $this->tagIds = [];
        $this->dateField = 'any';
        $this->dateMode = 'any';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->hasImage = 'any';
        $this->resetPage();
    }

    private function filter(): StoryFilter
    {
        return StoryFilter::fromArray([
            'language' => $this->language,
            'status' => $this->status,
            'category' => $this->category,
            'text' => $this->search,
            'scope' => $this->searchScope,
            'authorId' => $this->authorId,
            'tagIds' => $this->tagIds,
            'dateField' => $this->dateField,
            'dateMode' => $this->dateMode,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'hasImage' => $this->hasImage,
            'sortField' => $this->sortField,
            'sortDir' => $this->sortDir,
        ]);
    }

    public function select(int $id): void
    {
        $this->openDrawer($id);
    }

    public function openDrawer(int $id): void
    {
        $this->selectedId = $id;
        $story = app(StoryRepository::class)->findOrFail($id);
        $this->selectedVersion = (int) $story->version;
        $this->noteText = '';
        $this->conflictError = null;
    }

    public function closeDrawer(): void
    {
        $this->selectedId = null;
        $this->selectedVersion = null;
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
            app(StoryService::class)->takeOver($story, $user, $this->selectedVersion ?? $story->version);
            $this->selectedVersion = $story->fresh()->version;
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
        try {
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
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Failed to update story: '.$e->getMessage());
        }
    }

    public function deleteStory(int $id): void
    {
        try {
            $story = app(StoryRepository::class)->findOrFail($id);
            app(RbacService::class)->assertCan(auth()->user(), 'stories', 'delete');
            $story->delete();
            $this->dispatch('toast', message: 'Story moved to trash.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Failed to delete story: '.$e->getMessage());
        }
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
        $published = 0;
        $failed = 0;

        foreach ($stories as $story) {
            try {
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
                $published++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $this->selectedStories = [];
        $this->selectAll = false;
        $msg = $published.' story(s) published.';
        if ($failed > 0) {
            $msg .= ' '.$failed.' failed.';
        }
        $this->dispatch('toast', message: $msg);
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedStories)) {
            return;
        }

        try {
            app(RbacService::class)->assertCan(auth()->user(), 'stories', 'delete');
            app(StoryRepository::class)->deleteMany($this->selectedStories);

            $this->selectedStories = [];
            $this->selectAll = false;
            $this->dispatch('toast', message: 'Selected stories deleted.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Failed to delete stories: '.$e->getMessage());
        }
    }

    public function export(): StreamedResponse
    {
        $stories = app(StoryRepository::class)->exportQuery($this->filter());
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

        $stories = $repo->filteredList($this->filter(), 15);
        $categories = app(CategoryRepository::class)->categoriesTree();
        $selected = $this->selectedId ? $repo->findWithDetails($this->selectedId) : null;
        $counts = $repo->statusCounts($this->language);
        $authors = StoryFilterService::authorOptions($this->language);
        $tags = StoryFilterService::tagOptions();

        return view('livewire.admin.news-list', compact('stories', 'categories', 'selected', 'counts', 'authors', 'tags'));
    }
}
