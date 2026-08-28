<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class NewsList extends Component
{
    use WithPagination;

    public string $language = 'en';
    public string $status = 'all';
    public string $category = 'all';
    public string $search = '';
    public ?int $selectedId = null;

    public function mount(string $language = 'en'): void
    {
        $this->language = $language;
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedCategory(): void { $this->resetPage(); }

    public function select(int $id): void { $this->selectedId = $id; }
    public function closeDrawer(): void { $this->selectedId = null; }

    public function render()
    {
        $query = Story::with(['category','owner','notes'])->where('language', $this->language);
        if ($this->status !== 'all') $query->where('status', $this->status);
        if ($this->category !== 'all') $query->where('category_id', $this->category);
        if ($this->search !== '') $query->where('headline', 'like', '%'.$this->search.'%');
        $stories = $query->orderByDesc('updated_at')->paginate(15);
        $categories = Category::whereNull('parent_id')->orderBy('sort_order')->get();
        $selected = $this->selectedId ? Story::with(['category','owner','assignedEditor','lockedBy','notes.user','events'])->find($this->selectedId) : null;
        $counts = [
            'all' => Story::where('language',$this->language)->count(),
            'draft' => Story::where('language',$this->language)->where('status','draft')->count(),
            'in_review' => Story::where('language',$this->language)->where('status','in_review')->count(),
            'published' => Story::where('language',$this->language)->where('status','published')->count(),
        ];
        return view('livewire.admin.news-list', compact('stories','categories','selected','counts'));
    }
}
