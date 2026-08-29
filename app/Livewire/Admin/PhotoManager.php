<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\MediaBatch;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PhotoManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $tab = 'library';
    public string $search = '';
    public ?int $selectedId = null;
    public array $selectedIds = [];
    public string $fieldStatus = 'pending';
    public $uploads = [];

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedUploads(): void { $this->handleUploads(); }

    public function handleUploads(): void
    {
        $this->validate(['uploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240']);
        $count = count($this->uploads);
        foreach ($this->uploads as $file) {
            $path = $file->store('media/library', 'public');
            MediaAsset::create([
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'kind' => 'photo',
                'status' => 'library',
                'mime' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'storage_disk' => 'public',
                'original_path' => $path,
                'uploaded_by' => auth()->id(),
            ]);
        }
        $this->uploads = [];
        $this->dispatch('toast', message: 'Uploaded '.$count.' photos');
    }
    public function updatedTab(): void { $this->resetPage(); $this->selectedId=null; $this->selectedIds=[]; }

    public function select(int $id): void { $this->selectedId = $this->selectedId===$id ? null : $id; }
    public function toggleSelect(int $id): void {
        if(in_array($id,$this->selectedIds)) $this->selectedIds=array_values(array_diff($this->selectedIds,[$id]));
        else $this->selectedIds[]=$id;
    }
    public function selectAll(array $ids): void { $this->selectedIds=$ids; }
    public function clearSelection(): void { $this->selectedIds=[]; }

    public function bulkApprove(): void {
        MediaAsset::whereIn('id',$this->selectedIds)->update(['status'=>'library','approved_at'=>now(),'approved_by'=>auth()->id()]);
        $this->selectedIds=[]; $this->dispatch('toast', message:'Approved');
    }
    public function approve(int $id): void {
        MediaAsset::where('id',$id)->update(['status'=>'library','approved_at'=>now(),'approved_by'=>auth()->id()]);
        $this->dispatch('toast', message:'Photo approved');
    }

    #[On('echo:photo-desk,MediaUploaded')]
    public function onMediaUploaded(array $payload): void {
        $this->dispatch('toast', message:'New photo: '.($payload['title'] ?? 'upload'));
    }

    public function render()
    {
        $libraryQuery = MediaAsset::with(['category'])->where('status','!=','archived');
        if($this->tab==='field') $libraryQuery->whereNotNull('batch_id');
        else $libraryQuery->where('status','library');
        if($this->search!=='') $libraryQuery->where('title','like','%'.$this->search.'%');
        $assets = $libraryQuery->orderByDesc('created_at')->paginate(24);

        $fieldBatches = null;
        if($this->tab==='field'){
            $fieldBatches = MediaBatch::with(['assets'=>fn($q)=>$q->orderBy('created_at')])->orderByDesc('submitted_at')->get();
        }
        $selected = $this->selectedId ? MediaAsset::with(['category','batch'])->find($this->selectedId) : null;
        $counts = [
            'library'=>MediaAsset::where('status','library')->count(),
            'field'=>MediaAsset::where('status','field')->count(),
            'reedit'=>MediaAsset::where('status','reedit')->count(),
        ];
        return view('livewire.admin.photo-manager', compact('assets','fieldBatches','selected','counts'));
    }
}
