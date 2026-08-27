<div>
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
<div>
<div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / {{ $language==='bn'?'Bangla News':'English News' }}</div>
<h1 class="font-serif text-[28px] font-semibold">{{ $language==='bn'?'Bangla News':'English News' }}</h1>
</div>
<a href="#" class="px-5 py-2.5 rounded-[10px] bg-crimson text-white text-sm font-medium hover:bg-crimson-dark">+ New story</a>
</div>

<div class="flex flex-wrap gap-2 mb-4 items-center">
@foreach(['all'=>'All','draft'=>'Draft','in_review'=>'In review','published'=>'Published'] as $k=>$l)
<button wire:click="$set('status','{{ $k }}')" class="px-3.5 py-1.5 rounded-full text-xs font-medium border {{ $status===$k?'bg-navy-800 text-white border-navy-800':'bg-panel border-[#e3e1da] hover:border-navy-800' }}">{{ $l }} <span class="ml-1 text-[10px] {{ $status===$k?'bg-white/20':'bg-[#f3f1ee]' }} px-1.5 rounded-full">{{ $counts[$k]??0 }}</span></button>
@endforeach
<div class="ml-auto flex gap-2">
<div class="flex items-center gap-2 bg-panel border border-border rounded-lg px-3 py-1.5">
<x-lucide-search class="w-4 h-4 text-muted-2"/><input wire:model.live.debounce.300ms="search" placeholder="Search headline…" class="outline-none text-sm bg-transparent">
</div>
<select wire:model.live="category" class="border border-[#e3e1da] rounded-lg px-3 py-1.5 text-sm bg-panel">
<option value="all">All categories</option>@foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name_en }}</option>@endforeach
</select>
</div>
</div>

<div class="bg-panel border border-border rounded-xl overflow-hidden">
<div class="hidden lg:grid grid-cols-[1fr_140px_150px_90px] gap-3 px-5 py-2.5 bg-[#fbfaf7] text-[11px] font-bold uppercase tracking-wide text-muted-2">
<span>Story</span><span>Category</span><span>Status</span><span>Owner</span>
</div>
@forelse($stories as $s)
@php
$statusMap=['draft'=>'bg-[#f3f1ee] text-muted','in_review'=>'bg-amber/20 text-amber','changes_requested'=>'bg-crimson-soft text-crimson-dark','approved'=>'bg-blue-bg text-blue','published'=>'bg-green-bg text-green','killed'=>'bg-crimson-soft text-crimson','archived'=>'bg-[#f3f1ee] text-muted'];
$cls=$statusMap[$s->status]??'bg-[#f3f1ee] text-muted';
@endphp
<div wire:click="select({{ $s->id }})" class="grid grid-cols-1 lg:grid-cols-[1fr_140px_150px_90px] gap-2 lg:gap-3 px-5 py-4 border-t border-border hover:bg-[#fcfbf8] cursor-pointer items-center">
<div class="min-w-0">
<div class="font-serif text-[15px] font-semibold leading-tight truncate">{{ $s->headline }}</div>
<div class="text-xs text-muted mt-1 flex items-center gap-2">{{ $s->brief ? \Illuminate\Support\Str::limit($s->brief,80):'' }} @if($s->is_breaking)<span class="bg-crimson text-white text-[10px] px-1.5 py-0.5 rounded font-bold">BREAKING</span>@endif @if($s->notes->count()>0)<span class="bg-blue-bg text-blue text-[10px] px-1.5 py-0.5 rounded-full">{{ $s->notes->count() }} notes</span>@endif</div>
</div>
<div class="text-xs"><span class="px-2 py-1 rounded-full bg-paper border text-muted">{{ $s->category->name_en ?? '—' }}</span></div>
<div><span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full {{ $cls }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></div>
<div class="text-xs text-muted">{{ $s->owner->name ?? '—' }}</div>
</div>
@empty
<div class="p-10 text-center text-sm text-muted-2">No stories found. Create your first story.</div>
@endforelse
<div class="p-3 border-t">{{ $stories->links() }}</div>
</div>

@if($selected)
<div class="fixed inset-0 z-[70] flex justify-end">
<div class="absolute inset-0 bg-navy-900/50" wire:click="closeDrawer"></div>
<div class="relative w-[min(560px,100%)] bg-paper h-full overflow-y-auto shadow-2xl flex flex-col">
<div class="bg-panel border-b p-5">
<div class="flex gap-3">
<div class="flex-1 min-w-0">
<div class="font-serif text-lg font-bold leading-tight">{{ $selected->headline }}</div>
<div class="text-xs text-muted mt-1">{{ $selected->category->name_en ?? '' }} · {{ $selected->language==='bn'?'Bangla':'English' }} · {{ $selected->word_count }} words</div>
<div class="mt-2 flex gap-2 flex-wrap">
<span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $statusMap[$selected->status]??'' }}">{{ $selected->status }}</span>
@if($selected->is_breaking)<span class="bg-crimson text-white text-xs px-2 py-1 rounded-full font-bold">Breaking</span>@endif
<span class="text-xs text-muted">v{{ $selected->version }}</span>
</div>
</div>
<button wire:click="closeDrawer" class="w-8 h-8 border rounded-lg shrink-0">✕</button>
</div>
<div class="flex gap-2 mt-4">
<div class="text-xs"><span class="text-muted">Owner:</span> <b>{{ $selected->owner->name ?? '—' }}</b> @if($selected->lockedBy)<span class="text-amber">· {{ $selected->lockedBy->name }} editing</span>@endif</div>
</div>
</div>
<div class="p-5 space-y-4 flex-1">
<div class="bg-panel border rounded-xl p-4">
<div class="text-xs font-bold uppercase tracking-wide text-muted-2 mb-2">Workflow</div>
@forelse($selected->events->sortByDesc('created_at')->take(6) as $ev)
<div class="flex gap-3 py-1.5 text-sm"><span class="w-2 h-2 rounded-full bg-blue mt-1.5 shrink-0"></span><div><b>{{ $ev->action }}</b> <span class="text-muted">{{ $ev->from_status }} → {{ $ev->to_status }}</span><div class="text-xs text-muted-2">{{ $ev->created_at->diffForHumans() }}</div></div></div>
@empty<div class="text-xs text-muted">No events yet.</div>@endforelse
</div>
<div class="bg-panel border rounded-xl p-4">
<div class="flex items-center justify-between mb-2"><span class="text-xs font-bold uppercase text-muted-2">Internal notes</span><span class="text-xs bg-blue-bg text-blue px-2 py-0.5 rounded-full">{{ $selected->notes->count() }}</span></div>
@forelse($selected->notes as $n)
<div class="py-2 border-t first:border-0"><div class="text-sm">{{ $n->body }}</div><div class="text-xs text-muted-2 mt-1">{{ $n->user->name ?? 'System' }} · {{ $n->created_at->diffForHumans() }}</div></div>
@empty<div class="text-xs text-muted">No notes.</div>@endforelse
</div>
<div class="bg-panel border rounded-xl p-4">
<div class="text-xs font-bold uppercase text-muted-2 mb-2">Story</div>
<div class="text-sm leading-relaxed prose max-w-none">{!! $selected->body_html !!}</div>
@if($selected->brief)<div class="text-xs text-muted mt-2 p-2 bg-paper rounded border">{{ $selected->brief }}</div>@endif
</div>
</div>
</div>
</div>
@endif

<x-toast />
</div>
