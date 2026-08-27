<div>
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
<div>
<div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / Clients</div>
<h1 class="font-serif text-[30px] font-semibold">Clients</h1>
</div>
<div class="flex gap-2">
<x-btn variant="outline">Export CSV</x-btn>
<x-btn variant="primary" wire:click="openOnboard">Onboard client</x-btn>
</div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
<x-card><div class="font-serif text-2xl font-bold">{{ $stats['total'] }}</div><div class="text-xs text-muted">Total clients</div></x-card>
<x-card><div class="font-serif text-2xl font-bold text-green">{{ $stats['active'] }}</div><div class="text-xs text-muted">Active subscriptions</div></x-card>
<x-card><div class="font-serif text-2xl font-bold text-amber">{{ $stats['paused'] }}</div><div class="text-xs text-muted">Paused</div></x-card>
<x-card><div class="font-serif text-2xl font-bold text-amber">0</div><div class="text-xs text-muted">Renewals ≤45d</div></x-card>
<x-card><div class="font-serif text-2xl font-bold text-crimson">{{ $stats['issues'] }}</div><div class="text-xs text-muted">Delivery issues</div></x-card>
</div>

<div class="flex flex-wrap gap-2 mb-4 items-center">
<div class="flex-1 min-w-[220px] max-w-[360px] flex items-center gap-2 bg-panel border border-border rounded-[10px] px-3 py-2">
<x-lucide-search class="w-4 h-4 text-muted-2"/><input wire:model.live.debounce.300ms="search" placeholder="Search client, city, contact…" class="flex-1 outline-none text-sm bg-transparent">
</div>
<div class="flex gap-1">
@foreach(['all'=>'All','active'=>'Active','paused'=>'Paused','deactivated'=>'Deactivated'] as $k=>$l)
<button wire:click="$set('statusFilter','{{ $k }}')" class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $statusFilter===$k?'bg-crimson-soft border-crimson text-crimson-dark font-bold':'bg-panel border-[#e3e1da]' }}">{{ $l }}</button>
@endforeach
</div>
<select wire:model.live="tierFilter" class="border border-[#e3e1da] rounded-lg px-3 py-1.5 text-sm bg-panel">
<option value="all">All tiers</option><option>Premium</option><option>Standard</option><option>Basic</option>
</select>
<select wire:model.live="sort" class="border border-[#e3e1da] rounded-lg px-3 py-1.5 text-sm bg-panel">
<option value="name">Sort: Name</option><option value="renewal">Sort: Renewal</option>
</select>
</div>

<div class="bg-panel border border-border rounded-xl overflow-hidden">
<div class="hidden lg:grid grid-cols-[40px_2fr_1.4fr_1.2fr_120px_100px] gap-3 px-4 py-2 bg-[#fbfaf7] text-[10px] font-bold uppercase tracking-wide text-muted-2">
<span></span><span>Client</span><span>Package</span><span>Channels</span><span>Renewal</span><span>Status</span>
</div>
@forelse($clients as $c)
@php $pkg=$c->clientPackages->first()?->package; @endphp
<div wire:click="selectClient({{ $c->id }})" class="grid grid-cols-[40px_1fr] lg:grid-cols-[40px_2fr_1.4fr_1.2fr_120px_100px] gap-3 px-4 py-3 border-t border-border hover:bg-[#fbfaf7] cursor-pointer items-center">
<div class="w-8 h-8 rounded-lg bg-navy-800 text-white text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($c->name,0,2)) }}</div>
<div class="min-w-0"><div class="text-sm font-semibold truncate">{{ $c->name }}</div><div class="text-xs text-muted-2">{{ $c->code }} · {{ $c->type }}</div></div>
<div class="hidden lg:block text-xs">@if($pkg)<span class="font-semibold">{{ $pkg->name }}</span>@else<span class="text-muted-2">No package</span>@endif</div>
<div class="hidden lg:flex gap-1">
@foreach($c->clientChannels as $ch)
<span class="w-7 h-7 rounded-lg border bg-white flex items-center justify-center {{ $ch->status==='active'?'bg-green-bg border-transparent':'' }}"><x-lucide-radio class="w-3 h-3 {{ $ch->status==='active'?'text-green':'text-muted-2' }}"/></span>
@endforeach
@if($c->clientChannels->isEmpty())<span class="text-xs text-muted-2">—</span>@endif
</div>
<div class="hidden lg:block text-xs text-muted">{{ $c->created_at->format('M j, Y') }}</div>
<div class="hidden lg:block"><span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $c->status==='active'?'bg-green-bg text-green':($c->status==='suspended'?'bg-[#fdf3e0] text-[#b7791f]':'bg-[#f3f1ee] text-muted') }}">{{ $c->status }}</span></div>
</div>
@empty
<div class="p-10 text-center text-sm text-muted-2">No clients found.</div>
@endforelse
<div class="p-3 border-t">{{ $clients->links() }}</div>
</div>

{{-- Drawer --}}
@if($selected)
<div class="fixed inset-0 z-[70] flex justify-end">
<div class="absolute inset-0 bg-navy-900/50" wire:click="closeDrawer"></div>
<div class="relative w-[min(520px,100%)] bg-paper h-full overflow-y-auto shadow-2xl flex flex-col">
<div class="bg-panel border-b p-5">
<div class="flex gap-3">
<div class="w-10 h-10 rounded-xl bg-navy-800 text-white flex items-center justify-center font-bold">{{ strtoupper(substr($selected->name,0,2)) }}</div>
<div><div class="font-serif text-lg font-bold">{{ $selected->name }}</div><div class="text-xs text-muted">{{ $selected->code }} · {{ $selected->type }} · {{ $selected->billing_email }}</div></div>
<button wire:click="closeDrawer" class="ml-auto w-8 h-8 border rounded-lg">✕</button>
</div>
<div class="flex gap-2 mt-3">
<x-btn variant="outline" size="sm" wire:click="$set('showPause', true)">Pause</x-btn>
<x-btn variant="outline" size="sm" wire:click="$set('showDeact', true)">Deactivate</x-btn>
</div>
<div class="flex gap-1 mt-4 border-b -mb-px">
@foreach(['overview'=>'Overview','channels'=>'Channels','package'=>'Package','activity'=>'Activity'] as $k=>$l)
<button wire:click="$set('drawerTab','{{ $k }}')" class="px-3 py-2 text-xs font-semibold border-b-2 {{ $drawerTab===$k?'border-crimson text-crimson-dark':'border-transparent text-muted' }}">{{ $l }}</button>
@endforeach
</div>
</div>
<div class="p-5 flex-1">
@if($drawerTab==='overview')
<div class="space-y-3">
<div class="bg-panel border rounded-xl p-4">
<div class="text-[10px] uppercase font-bold text-muted-2 mb-2">Details</div>
<div class="text-sm space-y-1"><div class="flex justify-between"><span class="text-muted">Status</span><b>{{ $selected->status }}</b></div><div class="flex justify-between"><span class="text-muted">Type</span><b>{{ $selected->type }}</b></div><div class="flex justify-between"><span class="text-muted">Billing</span><b>{{ $selected->billing_email }}</b></div></div>
</div>
<div class="bg-panel border rounded-xl p-4"><div class="text-xs font-bold mb-2">Users ({{ $selected->clientUsers->count() }})</div>@foreach($selected->clientUsers as $u)<div class="text-sm py-1 border-t first:border-0">{{ $u->name }} — {{ $u->email }} <span class="text-xs text-muted">({{ $u->status }})</span></div>@endforeach</div>
</div>
@elseif($drawerTab==='channels')
<div class="space-y-2">
@forelse($selected->clientChannels as $ch)
<div class="bg-panel border rounded-xl p-3 flex items-center gap-3">
<span class="w-8 h-8 rounded-lg bg-paper border flex items-center justify-center"><x-lucide-webhook class="w-4 h-4"/></span>
<div><div class="text-sm font-bold capitalize">{{ $ch->type }}</div><div class="text-xs text-muted font-mono truncate max-w-[260px]">{{ json_encode($ch->config) }}</div></div>
<span class="ml-auto text-[10px] font-bold px-2 py-1 rounded-full {{ $ch->status==='active'?'bg-green-bg text-green':'bg-[#fdf3e0] text-amber' }}">{{ $ch->status }}</span>
</div>
@empty<div class="text-sm text-muted">No channels.</div>@endforelse
</div>
@elseif($drawerTab==='package')
<div class="bg-panel border rounded-xl p-4">
@php $cp=$selected->clientPackages->first(); @endphp
@if($cp)<div class="font-bold">{{ $cp->package->name }}</div><div class="text-xs text-muted">Since {{ $cp->starts_at }}</div><div class="text-xs mt-2">Status: {{ $cp->status }}</div>@else<div class="text-sm text-muted">No package assigned.</div>@endif
</div>
@else
<div class="text-sm text-muted">Activity timeline — coming soon.</div>
@endif
</div>
</div>
</div>
@endif

{{-- Onboard modal --}}
@if($showOnboard)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50" wire:click="$set('showOnboard', false)"></div>
<div class="relative bg-white rounded-2xl w-[min(640px,100%)] shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
<div class="px-5 py-4 border-b flex items-center gap-2"><span class="font-serif font-bold text-lg">Onboard new client</span><button wire:click="$set('showOnboard', false)" class="ml-auto w-7 h-7 border rounded-lg">✕</button></div>
<div class="p-5 space-y-3">
<div><label class="text-xs font-semibold">Organisation name *</label><input wire:model="wName" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div class="grid grid-cols-2 gap-3"><div><label class="text-xs font-semibold">Type</label><select wire:model="wType" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="newspaper">newspaper</option><option value="tv">tv</option><option value="online">online</option><option value="radio">radio</option><option value="govt">govt</option><option value="agency">agency</option></select></div><div><label class="text-xs font-semibold">Contact email *</label><input wire:model="wEmail" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div></div>
<div><label class="text-xs font-semibold">Package *</label><select wire:model="wPackage" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="">Select package</option>@foreach($packages as $p)<option value="{{ $p->id }}">{{ $p->name }} — ৳{{ number_format($p->price_monthly) }}</option>@endforeach</select></div>
</div>
<div class="flex justify-end gap-2 p-4 border-t"><x-btn variant="outline" wire:click="$set('showOnboard', false)">Cancel</x-btn><x-btn variant="primary" wire:click="onboard">Create client</x-btn></div>
</div>
</div>
@endif

@if($showPause)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4"><div class="absolute inset-0 bg-navy-900/50" wire:click="$set('showPause', false)"></div><div class="relative bg-white rounded-2xl p-5 w-[min(420px,100%)]"><h3 class="font-bold">Pause client?</h3><p class="text-sm text-muted mt-1">Deliveries will be held until resumed.</p><div class="flex justify-end gap-2 mt-4"><x-btn variant="outline" wire:click="$set('showPause', false)">Cancel</x-btn><x-btn variant="primary" wire:click="pause">Pause</x-btn></div></div></div>
@endif
@if($showDeact)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4"><div class="absolute inset-0 bg-navy-900/50" wire:click="$set('showDeact', false)"></div><div class="relative bg-white rounded-2xl p-5 w-[min(420px,100%)]"><h3 class="font-bold">Deactivate client?</h3><p class="text-xs text-muted mt-1">Type DEACTIVATE to confirm. Portal + channels stopped.</p><input wire:model="deactConfirm" placeholder="DEACTIVATE" class="w-full border rounded-lg px-3 py-2 text-sm mt-2"><div class="flex justify-end gap-2 mt-4"><x-btn variant="outline" wire:click="$set('showDeact', false)">Cancel</x-btn><x-btn variant="primary" wire:click="deactivate">Deactivate</x-btn></div></div></div>
@endif

<x-toast />
</div>
