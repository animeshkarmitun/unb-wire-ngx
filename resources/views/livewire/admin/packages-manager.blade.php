<div>
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
<div>
<div class="text-[12.5px] text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / Packages & add-ons</div>
<h1 class="font-serif text-[30px] font-semibold">Packages & add-ons</h1>
</div>
<div class="flex gap-2">
<x-btn variant="outline" wire:click="openCreate">New package</x-btn>
</div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
<x-card><div class="font-serif text-[27px] font-bold">{{ $live }}</div><div class="text-xs text-muted">Live packages</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold">0</div><div class="text-xs text-muted">Add-ons live</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold text-navy-800">{{ $clientsCovered }}</div><div class="text-xs text-muted">Clients covered</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold text-green">৳{{ number_format($mrr) }}</div><div class="text-xs text-muted">Monthly recurring</div></x-card>
</div>

<div class="flex items-baseline gap-2 mb-3">
<h2 class="font-serif text-lg font-bold">Subscription packages</h2>
<span class="text-xs text-muted">What clients subscribe to — assign from the Clients page</span>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
@foreach($packages as $idx=>$p)
@php $grad='g'.(($idx%8)+1); $arch=$p->status==='archived'; @endphp
<div class="bg-panel border border-border rounded-[15px] overflow-hidden flex flex-col shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition {{ $arch?'opacity-70':'' }}">
<div class="p-4 text-white relative {{ $grad }}">
<span class="absolute top-3 right-3 text-[10px] font-bold uppercase tracking-wide bg-white/20 px-2 py-1 rounded-md">{{ $p->status }}</span>
<div class="font-serif text-lg font-bold pr-16">{{ $p->name }}</div>
<div class="mt-1"><b class="font-serif text-2xl">৳{{ number_format($p->price_monthly) }}</b> <span class="text-xs opacity-80">/ month</span></div>
@if($p->description)<div class="text-xs opacity-85 mt-1">{{ $p->description }}</div>@endif
<div class="text-[11px] mt-2 opacity-80">{{ $p->code }} · {{ $p->kind }}</div>
</div>
<div class="p-4 flex-1 flex flex-col">
<div class="text-xs text-muted font-mono bg-paper border border-border rounded-lg p-2 mb-3">{{ json_encode($p->entitlement_filter) }}</div>
<div class="flex items-center gap-2 mt-auto pt-3 border-t border-border">
<span class="text-xs text-muted"><b>{{ $p->clientPackages_count }}</b> client{{ $p->clientPackages_count!==1?'s':'' }}</span>
</div>
</div>
<div class="flex gap-2 p-3 pt-0">
<button wire:click="openEdit({{ $p->id }})" class="flex-1 py-1.5 text-xs font-semibold border rounded-lg hover:border-navy-800">Edit</button>
@if($arch)
<button wire:click="$set('archiveId', {{ $p->id }})" class="flex-1 py-1.5 text-xs font-semibold border rounded-lg">Restore</button>
<button wire:click="$set('archiveId', {{ $p->id }})" class="py-1.5 px-3 text-xs font-semibold border rounded-lg hover:border-crimson hover:text-crimson">Delete</button>
@else
<button wire:click="$set('archiveId', {{ $p->id }})" class="flex-1 py-1.5 text-xs font-semibold border rounded-lg hover:border-crimson">Archive</button>
@endif
</div>
</div>
@endforeach
<button wire:click="openCreate" class="border-2 border-dashed border-[#d8d5cd] rounded-[15px] min-h-[300px] flex flex-col items-center justify-center gap-2 text-muted hover:border-crimson hover:text-crimson-dark hover:bg-crimson-soft transition">
<x-lucide-plus class="w-6 h-6"/><span class="text-sm font-semibold">Create a new package</span>
</button>
</div>

{{-- Create modal --}}
@if($showCreate || $showEdit)
@php $isEdit=$showEdit; @endphp
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50 backdrop-blur-sm" wire:click="$set('showCreate', false); $set('showEdit', false)"></div>
<div class="relative bg-white rounded-2xl w-[min(640px,100%)] max-h-[90vh] overflow-y-auto shadow-2xl">
<div class="flex items-center gap-2 px-5 py-4 border-b sticky top-0 bg-white"><span class="font-serif text-lg font-bold">{{ $isEdit?'Edit package':'New package' }}</span><button wire:click="$set('showCreate', false); $set('showEdit', false)" class="ml-auto w-7 h-7 rounded-lg border">✕</button></div>
<div class="p-5 space-y-3">
<div class="grid grid-cols-2 gap-3">
<div><label class="text-xs font-semibold">Code *</label><input wire:model="code" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" placeholder="EN-BASIC"></div>
<div><label class="text-xs font-semibold">Kind *</label><select wire:model="kind" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"><option value="news">news</option><option value="photos">photos</option><option value="bundle">bundle</option></select></div>
</div>
<div><label class="text-xs font-semibold">Name *</label><input wire:model="name" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" placeholder="Premium Wire + Media"></div>
<div class="grid grid-cols-2 gap-3">
<div><label class="text-xs font-semibold">Price / month</label><input wire:model="price" type="number" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" placeholder="45000"></div>
<div><label class="text-xs font-semibold">Status</label><select wire:model="status" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"><option value="active">active</option><option value="archived">archived</option></select></div>
</div>
<div><label class="text-xs font-semibold">Description</label><textarea wire:model="description" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"></textarea></div>
<div><label class="text-xs font-semibold">Entitlement filter (JSON)</label><textarea wire:model="entitlement" rows="4" class="mt-1 w-full border rounded-lg px-3 py-2 text-xs font-mono bg-paper"></textarea><div class="text-[11px] text-muted-2 mt-1">Same shape drives delivery + portal + tenant token — keep languages/category_ids/media_kinds</div></div>
</div>
<div class="flex gap-2 justify-end p-4 border-t sticky bottom-0 bg-white"><x-btn variant="outline" wire:click="$set('showCreate', false); $set('showEdit', false)">Cancel</x-btn><x-btn variant="primary" wire:click="{{ $isEdit?'saveEdit':'saveCreate' }}">{{ $isEdit?'Save':'Create' }}</x-btn></div>
</div>
</div>
@endif

{{-- Archive / delete confirm --}}
@if($archiveId)
@php $pkg=$packages->firstWhere('id',$archiveId); @endphp
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50" wire:click="$set('archiveId', null)"></div>
<div class="relative bg-white rounded-2xl w-[min(420px,100%)] p-5 shadow-2xl">
<h3 class="font-serif font-bold">{{ $pkg && $pkg->status==='active' ? 'Archive' : 'Restore' }} package?</h3>
<p class="text-sm text-muted mt-2">{{ $pkg?->name }} — {{ $pkg?->status }}</p>
<div class="flex gap-2 justify-end mt-4">
<x-btn variant="outline" wire:click="$set('archiveId', null)">Cancel</x-btn>
<x-btn variant="primary" wire:click="archive">{{ $pkg && $pkg->status==='active' ? 'Archive' : 'Restore' }}</x-btn>
@if($pkg && $pkg->status==='archived')<x-btn variant="outline" wire:click="delete">Delete permanently</x-btn>@endif
</div>
</div>
</div>
@endif

<x-toast />
</div>
