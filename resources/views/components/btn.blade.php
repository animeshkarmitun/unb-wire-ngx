@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button'])
@php
$base = 'inline-flex items-center justify-center gap-1.5 font-medium font-sans rounded-[10px] transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
$sizes = ['sm' => 'px-3 py-1.5 text-[12.5px] rounded-lg', 'md' => 'px-5 py-[10px] text-[13.5px]', 'lg' => 'px-6 py-3 text-sm'][ $size ] ?? 'px-5 py-[10px] text-[13.5px]';
$variants = [
  'primary' => 'bg-crimson text-white shadow-[0_2px_8px_rgba(229,72,77,0.3)] hover:bg-crimson-dark border border-transparent',
  'outline' => 'bg-panel border border-[#e3e1da] text-ink hover:border-navy-800 hover:text-navy-800',
  'navy' => 'bg-navy-800 text-white hover:bg-navy-900 border border-transparent',
  'ghost' => 'bg-transparent text-muted hover:bg-paper border border-transparent',
][$variant] ?? 'bg-crimson text-white';
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => "$base $sizes $variants"]) }}>
{{ $slot }}
</button>
