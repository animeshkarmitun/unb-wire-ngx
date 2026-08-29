@props(['tabs' => []])
<div {{ $attributes->merge(['class' => 'flex gap-2']) }}>
@foreach($tabs as $tab)
  <button type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-[10px] text-sm font-medium border transition-colors {{ ($tab['active'] ?? false) ? 'bg-navy-800 text-white border-navy-800' : 'bg-panel border-border text-muted hover:border-navy-800' }}">
    {{ $tab['label'] }}
    @if(isset($tab['count']))<span class="min-w-[19px] h-[19px] px-1.5 rounded-full flex items-center justify-center text-xs font-bold {{ ($tab['active'] ?? false) ? 'bg-white text-navy-800' : 'bg-crimson text-white' }}">{{ $tab['count'] }}</span>@endif
  </button>
@endforeach
{{ $slot }}
</div>
