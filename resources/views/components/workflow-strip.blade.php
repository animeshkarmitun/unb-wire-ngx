@props(['status' => 'draft', 'owner' => null, 'shift' => null, 'storyId' => null])
@php
$map = ['draft'=>'bg-[#f1efe9] text-muted-2','review'=>'bg-blue-bg text-blue','rework'=>'bg-crimson-soft text-crimson-dark','approved'=>'bg-green-bg text-green','live'=>'bg-green-bg text-green','published'=>'bg-green-bg text-green'];
$pill = $map[$status] ?? $map['draft'];
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 flex-wrap bg-panel border border-border rounded-[14px] px-4 py-2.5']) }}>
  <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $pill }}">{{ strtoupper($status) }}</span>
  @if($owner)
  <span class="flex items-center gap-2 text-xs text-muted">
    <span class="w-6 h-6 rounded-full bg-navy-800 text-white flex items-center justify-center text-[10px] font-bold">{{ strtoupper(substr($owner,0,2)) }}</span>
    <b class="text-ink">{{ $owner }}</b>
    @if($shift)<span class="text-muted-2">{{ $shift }}</span>@endif
    @if($storyId)<span class="text-muted-2">#{{ $storyId }}</span>@endif
  </span>
  @endif
  <span class="flex-1"></span>
  {{ $slot }}
</div>
