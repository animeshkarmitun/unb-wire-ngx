<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use Livewire\Component;
use Livewire\WithPagination;

class ApPhotoManager extends Component
{
    use WithPagination;
    public string $search=''; public string $status='all'; public ?int $selectedId=null;
    public function updatedSearch():void{ $this->resetPage(); }
    public function select(int $id): void { $this->selectedId=$this->selectedId===$id?null:$id; }
    public function import(int $id): void {
        MediaAsset::where('id',$id)->update(['status'=>'library','source'=>'ap']);
        $this->dispatch('toast', message:'AP photo imported to library');
    }
    public function render(){
        $q=MediaAsset::where('source','ap');
        if($this->status!=='all') $q->where('status',$this->status);
        if($this->search!=='') $q->where('title','like','%'.$this->search.'%');
        $assets=$q->orderByDesc('created_at')->paginate(24);
        $selected=$this->selectedId?MediaAsset::find($this->selectedId):null;
        return view('livewire.admin.ap-photo-manager', compact('assets','selected'));
    }
}
