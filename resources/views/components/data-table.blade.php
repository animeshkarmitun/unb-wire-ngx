@props(['headers' => []])
<div {{ $attributes->merge(['class' => 'bg-panel border border-border rounded-xl overflow-hidden']) }}>
  @if(count($headers))
  <div class="grid px-4 py-2.5 border-b border-border bg-paper text-xs font-semibold tracking-widest uppercase text-muted-2" style="grid-template-columns: repeat({{ count($headers) }}, 1fr)">
    @foreach($headers as $h)<span>{{ $h }}</span>@endforeach
  </div>
  @endif
  {{ $slot }}
</div>
