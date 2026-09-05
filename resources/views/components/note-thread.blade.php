@props(['notes' => []])
<div {{ $attributes->merge(['class' => 'border-t border-dashed border-border pt-4 mt-5']) }}>
  <div class="text-sm font-bold text-ink flex items-center gap-2">Internal Notes <span class="text-xs font-normal text-muted-2">— newsroom only</span></div>
  <div class="flex flex-col gap-2.5 my-3">
    @forelse($notes as $n)
      <div class="border border-border rounded-xl px-3 py-2.5 bg-[#fcfbf8] {{ ($n['role'] ?? '')==='editor' ? 'border-l-[3px] border-l-navy-800' : '' }}">
        <div class="flex items-center gap-2 text-xs text-muted mb-1">
          <b class="text-ink">{{ $n['author'] ?? 'System' }}</b>
          <span class="text-[11px]">{{ $n['time'] ?? '' }}</span>
        </div>
        <div class="text-[13px] leading-relaxed text-ink">{{ $n['body'] ?? '' }}</div>
      </div>
    @empty
      <div class="text-xs text-muted-2">No notes yet.</div>
    @endforelse
  </div>
  {{ $slot }}
</div>
