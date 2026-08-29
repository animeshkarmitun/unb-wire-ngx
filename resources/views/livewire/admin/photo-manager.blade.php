<div x-data="{ dragOver: false }" @dragover.prevent="dragOver=true" @dragleave.prevent="dragOver=false" @drop.prevent="dragOver=false">
<div x-show="dragOver" x-cloak class="fixed inset-0 z-[200] bg-navy-900/55 backdrop-blur-[3px] flex items-center justify-center">
  <div class="border-[2.5px] dashed border-white/70 rounded-[20px] px-16 py-14 text-center text-white">
    <div class="font-serif text-2xl font-semibold">Drop photos to upload</div>
    <div class="text-sm opacity-80 mt-2">JPG, PNG, WebP up to 10MB</div>
  </div>
</div>
<div class="flex items-center justify-between mb-4 flex-wrap gap-3">
<div>
<div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / UNB Photos</div>
<h1 class="font-serif text-[26px] font-semibold">UNB Photos</h1>
</div>
<div class="flex gap-2">
<div class="flex items-center gap-2 bg-panel border border-border rounded-lg px-3 py-1.5">
<x-lucide-search class="w-4 h-4 text-muted-2"/><input wire:model.live.debounce.300ms="search" placeholder="Search photos…" class="outline-none text-sm bg-transparent w-40">
</div>
<label class="inline-flex items-center justify-center px-4 py-1.5 rounded-lg bg-panel border border-[#e3e1da] text-sm font-medium cursor-pointer hover:border-navy-800">
  <input type="file" wire:model="uploads" multiple accept="image/*" class="hidden">
  <span wire:loading.remove wire:target="uploads">Upload</span>
  <span wire:loading wire:target="uploads">Uploading…</span>
</label>
</div>
</div>

<div class="flex gap-2 mb-3">
@foreach(['library'=>'Library','field'=>'Field intake','reedit'=>'Re-edit'] as $k=>$l)
<button wire:click="$set('tab','{{ $k }}')" class="px-4 py-1.5 rounded-full text-xs font-semibold border {{ $tab===$k?'bg-navy-800 text-white border-navy-800':'bg-panel border-[#e3e1da]' }}">{{ $l }} @if($k==='field' && $counts['field']>0)<span class="ml-1 bg-crimson text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ $counts['field'] }}</span>@endif</button>
@endforeach
<span class="ml-auto text-xs text-muted">{{ $counts['library'] }} in library</span>
</div>

@if(count($selectedIds)>0)
<div class="sticky top-[60px] z-20 bg-navy-800 text-white rounded-xl px-4 py-2.5 flex items-center gap-3 mb-3">
<span class="text-sm font-bold">{{ count($selectedIds) }} selected</span>
<button wire:click="bulkApprove" class="px-3 py-1 rounded-lg bg-green text-white text-xs font-bold">Approve</button>
<button wire:click="clearSelection" class="ml-auto text-xs text-navy-text hover:text-white">Clear</button>
</div>
@endif

@if($tab==='field' && $fieldBatches)
<div class="space-y-4">
@forelse($fieldBatches as $batch)
<div class="bg-panel border border-border rounded-xl overflow-hidden">
<div class="flex items-center gap-3 px-4 py-3 bg-[#fbfaf7] border-b">
<div class="w-8 h-8 rounded-lg bg-amber text-white flex items-center justify-center"><x-lucide-camera class="w-4 h-4"/></div>
<div><div class="text-sm font-bold">{{ $batch->event_label }} <span class="text-xs font-normal text-muted">— {{ $batch->urgency }}</span></div><div class="text-xs text-muted">{{ $batch->assets->count() }} photos · {{ $batch->submitted_at?->format('M j, H:i') }}</div></div>
<span class="ml-auto text-xs px-2 py-1 rounded-full {{ $batch->status==='pending'?'bg-amber/20 text-amber':'bg-green-bg text-green' }}">{{ $batch->status }}</span>
</div>
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-0">
@foreach($batch->assets as $a)
<div class="relative group border border-transparent hover:border-navy-800 cursor-pointer" wire:click="select({{ $a->id }})">
<div class="aspect-[4/3] bg-gradient-to-br from-[#e0e7ff] to-[#f0e6ff] flex items-center justify-center text-[10px] text-muted overflow-hidden">
@if($a->derivatives)<span class="truncate px-2">{{ $a->title }}</span>@else<span class="{{ 'g'.(($a->id%8)+1) }} absolute inset-0 opacity-60"></span><span class="relative z-10 bg-white/80 px-1.5 py-0.5 rounded text-xs">{{ $a->title }}</span>@endif
</div>
<div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition"></div>
<div class="absolute bottom-1 left-1 right-1 flex gap-1 opacity-0 group-hover:opacity-100 transition">
<button wire:click.stop="approve({{ $a->id }})" class="flex-1 bg-green text-white text-[11px] py-1 rounded font-bold">Approve</button>
</div>
<div class="absolute top-1 left-1"><input type="checkbox" @checked(in_array($a->id,$selectedIds)) wire:click.stop="toggleSelect({{ $a->id }})" class="w-4 h-4 rounded"></div>
</div>
@endforeach
</div>
</div>
@empty<div class="p-8 text-center text-sm text-muted border rounded-xl">No field batches.</div>@endforelse
</div>
@else
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
@forelse($assets as $a)
<div class="group relative bg-panel border border-border rounded-xl overflow-hidden hover:shadow-md transition cursor-pointer {{ in_array($a->id,$selectedIds)?'ring-2 ring-navy-800':'' }}" wire:click="select({{ $a->id }})">
<div class="aspect-[4/3] relative overflow-hidden bg-[#f3f1ee] flex items-center justify-center">
<div class="{{ 'g'.(($a->id%8)+1) }} absolute inset-0 opacity-80"></div>
<span class="relative z-10 bg-white/90 text-xs px-2 py-1 rounded-full font-medium">{{ $a->kind }}</span>
<div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition"></div>
<input type="checkbox" @checked(in_array($a->id,$selectedIds)) wire:click.stop="toggleSelect({{ $a->id }})" class="absolute top-2 left-2 w-4 h-4 rounded">
</div>
<div class="p-2">
<div class="text-xs font-semibold truncate">{{ $a->title }}</div>
<div class="text-[11px] text-muted truncate">{{ $a->caption ? \Illuminate\Support\Str::limit($a->caption,40): $a->category->name_en ?? '' }}</div>
<div class="text-[11px] text-muted-2">{{ $a->created_at->format('M j') }}</div>
</div>
</div>
@empty
<div class="col-span-full p-10 text-center text-sm text-muted-2 border-2 border-dashed rounded-xl">No photos. Upload to get started.</div>
@endforelse
</div>
<div class="mt-4">{{ $assets->links() }}</div>
@endif

@if($selected)
<div class="fixed inset-0 z-[70] flex justify-end">
<div class="absolute inset-0 bg-navy-900/50" wire:click="$set('selectedId', null)"></div>
<div class="relative w-[min(420px,100%)] bg-white h-full overflow-y-auto shadow-2xl flex flex-col">
<div class="aspect-[4/3] relative bg-[#f3f1ee] flex items-center justify-center overflow-hidden">
<div class="{{ 'g'.(($selected->id%8)+1) }} absolute inset-0"></div>
<button wire:click="$set('selectedId', null)" class="absolute top-3 right-3 w-8 h-8 bg-white rounded-lg border flex items-center justify-center">✕</button>
<span class="relative z-10 bg-white px-3 py-1.5 rounded-full text-xs font-bold">{{ $selected->status }} · {{ $selected->kind }}</span>
</div>
<div class="p-5 space-y-4 flex-1">
<h3 class="font-serif text-lg font-bold leading-tight">{{ $selected->title }}</h3>
@if($selected->caption)<p class="text-sm text-muted">{{ $selected->caption }}</p>@endif
<div class="grid grid-cols-2 gap-3 text-xs">
<div class="bg-paper border rounded-lg p-3"><div class="text-muted-2 uppercase text-[10px] font-bold">Category</div><div class="font-semibold">{{ $selected->category->name_en ?? '—' }}</div></div>
<div class="bg-paper border rounded-lg p-3"><div class="text-muted-2 uppercase text-[10px] font-bold">Size</div><div class="font-semibold">{{ $selected->size_bytes ? round($selected->size_bytes/1024).' KB' : '—' }}</div></div>
</div>
<div class="text-xs space-y-1"><div class="flex justify-between"><span class="text-muted">Credit</span><b>{{ $selected->credit_line }}</b></div><div class="flex justify-between"><span class="text-muted">Captured</span><b>{{ $selected->captured_at?->format('M j, Y H:i') ?? '—' }}</b></div><div class="flex justify-between"><span class="text-muted">Dimensions</span><b>{{ $selected->width }}×{{ $selected->height }}</b></div></div>
</div>
<div class="p-4 border-t flex gap-2">
<x-btn variant="primary" size="sm" wire:click="approve({{ $selected->id }})">Approve</x-btn>
<x-btn variant="outline" size="sm" wire:click="$set('selectedId', null)">Close</x-btn>
</div>
</div>
</div>
@endif

<x-toast />
</div>
