<div>
<div class="flex items-center justify-between mb-4">
<div><div class="text-xs text-muted-2 mb-1">Home / Distribution</div><h1 class="font-serif text-2xl font-bold">Distribution log</h1></div>
<div class="flex gap-2"><div class="flex items-center gap-2 bg-panel border rounded-lg px-3 py-1.5"><x-lucide-search class="w-4 h-4 text-muted-2"/><input wire:model.live.debounce.300ms="search" placeholder="Search hash…" class="outline-none text-sm bg-transparent w-36"></div><select wire:model.live="status" class="border rounded-lg px-3 py-1.5 text-sm bg-panel"><option value="all">All</option><option value="queued">queued</option><option value="sent">sent</option><option value="delivered">delivered</option><option value="failed">failed</option><option value="skipped_entitlement">skipped</option></select></div>
</div>
<div class="grid grid-cols-3 gap-3 mb-4">
<x-card><div class="font-serif text-xl font-bold">{{ $stats['total'] }}</div><div class="text-xs text-muted">Total deliveries</div></x-card>
<x-card><div class="font-serif text-xl font-bold text-green">{{ $stats['delivered'] }}</div><div class="text-xs text-muted">Delivered</div></x-card>
<x-card><div class="font-serif text-xl font-bold text-crimson">{{ $stats['failed'] }}</div><div class="text-xs text-muted">Failed</div></x-card>
</div>
<div class="bg-panel border rounded-xl overflow-hidden">
<div class="hidden lg:grid grid-cols-[1fr_120px_120px_100px_110px_90px] gap-3 px-4 py-2 bg-[#fbfaf7] text-[10px] font-bold uppercase text-muted-2">
<span>Deliverable</span><span>Client</span><span>Channel</span><span>Status</span><span>Attempts</span><span>Action</span>
</div>
@forelse($deliveries as $d)
<div class="grid grid-cols-1 lg:grid-cols-[1fr_120px_120px_100px_110px_90px] gap-2 px-4 py-3 border-t text-sm items-center hover:bg-[#fcfbf8]">
<div class="font-mono text-xs truncate">{{ $d->deliverable_type }} #{{ $d->deliverable_id }} <span class="text-muted-2">{{ \Illuminate\Support\Str::limit($d->idempotency_key,12) }}</span></div>
<div class="text-sm">{{ $d->client->name ?? '—' }}</div>
<div class="text-xs">{{ $d->channel->type ?? '—' }}</div>
<div><span class="text-xs font-bold px-2 py-1 rounded-full {{ $d->status==='delivered'?'bg-green-bg text-green':($d->status==='failed'?'bg-crimson-soft text-crimson':'bg-[#f3f1ee] text-muted') }}">{{ $d->status }}</span></div>
<div class="text-xs">{{ $d->attempt_count }} {{ $d->response_code ? '· '.$d->response_code:'' }}</div>
<div>@if($d->status==='failed')<button wire:click="retry({{ $d->id }})" class="text-xs px-2 py-1 rounded bg-amber text-white font-bold">Retry</button>@else<span class="text-xs text-muted-2">{{ $d->created_at->format('M j H:i') }}</span>@endif</div>
</div>
@empty
<div class="p-10 text-center text-sm text-muted">No deliveries yet. Publishing a story will fan out here.</div>
@endforelse
<div class="p-3 border-t">{{ $deliveries->links() }}</div>
</div>
<x-toast />
</div>
