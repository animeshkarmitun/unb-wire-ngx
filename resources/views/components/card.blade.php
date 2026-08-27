@props(['padding' => 'p-5'])
<div {{ $attributes->merge(['class' => "bg-panel border border-border rounded-[13px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] $padding"]) }}>
{{ $slot }}
</div>
