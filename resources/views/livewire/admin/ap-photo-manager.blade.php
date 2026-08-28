<div>
<div class="flex items-center justify-between mb-4">
<div><div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / AP Photo Manager</div><h1 class="font-serif text-2xl font-bold">AP Photo Manager</h1></div>
<div class="flex gap-2">
<div class="flex items-center gap-2 bg-panel border rounded-lg px-3 py-1.5"><x-lucide-search class="w-4 h-4 text-muted-2"/><input wire:model.live.debounce.300ms="search" placeholder="Search AP feed…" class="outline-none text-sm bg-transparent w-44"></div>
<select wire:model.live="status" class="border rounded-lg px-3 py-1.5 text-sm bg-panel"><option value="all">All</option><option value="field">Incoming</option><option value="library">Imported</option></select>
</div>
</div>
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
@forelse($assets as $a)
<div wire:click="select({{ $a->id }})" class="group relative bg-panel border rounded-xl overflow-hidden hover:shadow-md cursor-pointer {{ $a->status==='library'?'ring-1 ring-green':'' }}">
<div class="aspect-[4/3] relative overflow-hidden bg-[#eef2ff] flex items-center justify-center">
<div class="g2 absolute inset-0 opacity-70"></div>
<span class="relative z-10 bg-white/90 text-xs px-2 py-1 rounded-full font-bold">AP · {{ $a->status }}</span>
<div class="absolute bottom-1 left-1 right-1 opacity-0 group-hover:opacity-100 transition flex gap-1">
@if($a->status!=='library')<button wire:click.stop="import({{ $a->id }})" class="flex-1 bg-blue text-white text-xs py-1 rounded font-bold">Import</button>@endif
</div>
</div>
<div class="p-2"><div class="text-xs font-semibold truncate">{{ $a->title }}</div><div class="text-[11px] text-muted truncate">{{ $a->caption ? \Illuminate\Support\Str::limit($a->caption,35):'AP feed' }}</div></div>
</div>
@empty
<div class="col-span-full p-10 text-center text-sm text-muted border-2 border-dashed rounded-xl">No AP photos. AP ingestion will populate here.</div>
@endforelse
</div>
<div class="mt-4">{{ $assets->links() }}</div>
@if($selected)
<div class="fixed inset-0 z-[70] flex justify-end"><div class="absolute inset-0 bg-navy-900/50" wire:click="$set('selectedId', null)"></div><div class="relative w-[min(420px,100%)] bg-white h-full overflow-y-auto p-5">
<div class="aspect-[4/3] bg-[#eef2ff] rounded-xl flex items-center justify-center relative overflow-hidden"><div class="g2 absolute inset-0"></div><button wire:click="$set('selectedId', null)" class="absolute top-2 right-2 w-8 h-8 bg-white rounded-lg border">✕</button><span class="relative z-10 bg-white px-3 py-1 rounded-full text-xs font-bold">AP · {{ $selected->status }}</span></div>
<h3 class="font-bold mt-3">{{ $selected->title }}</h3><p class="text-sm text-muted mt-1">{{ $selected->caption }}</p>
<div class="mt-4 flex gap-2">@if($selected->status!=='library')<x-btn variant="primary" size="sm" wire:click="import({{ $selected->id }})">Import to library</x-btn>@endif<x-btn variant="outline" size="sm" wire:click="$set('selectedId', null)">Close</x-btn></div>
</div></div>
@endif
<x-toast />
</div>
