@props(['variant' => 'gray'])
@php
$map = [
  'blue' => 'bg-blue-bg text-blue border border-transparent',
  'green' => 'bg-green-bg text-green border border-transparent',
  'gray' => 'bg-[#f3f1ee] text-muted border border-transparent',
  'purple' => 'bg-purple-bg text-purple border border-transparent',
  'amber' => 'bg-[#fdf3e0] text-[#b7791f] border border-transparent',
  'crimson' => 'bg-crimson-soft text-crimson-dark border border-transparent',
];
$cls = $map[$variant] ?? $map['gray'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold $cls"]) }}>{{ $slot }}</span>
