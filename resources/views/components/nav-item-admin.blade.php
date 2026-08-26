@props([
    'href' => '#',
    'icon',
    'active' => false,
    'badge' => null,
    'badgeVariant' => 'red',
    'external' => false,
])

<a
    href="{{ $href }}"
    @if($external) target="_blank" rel="noopener" @endif
    {{ $attributes->class([
        'relative flex items-center gap-3 px-2.5 py-[9px] mb-[3px] rounded-[9px] text-sm transition-colors',
        'bg-white/[0.09] text-white font-semibold before:content-[""] before:absolute before:-left-4 before:top-2 before:bottom-2 before:w-[3.5px] before:rounded-r before:bg-crimson' => $active,
        'text-navy-text hover:bg-white/[0.06] hover:text-[#e4e8f4]' => ! $active,
    ]) }}
>
    <x-dynamic-component :component="'lucide-' . $icon" class="w-[19px] h-[19px] shrink-0 stroke-current" stroke-width="1.8" />
    {{ $slot }}
    @if($badge)
        <span @class([
            'ml-auto min-w-5 h-5 px-1.5 rounded-full text-[11.5px] font-semibold inline-flex items-center justify-center',
            'bg-crimson text-white' => $badgeVariant === 'red',
            'bg-[rgba(52,211,153,0.18)] text-[#5eeab0]' => $badgeVariant === 'green',
        ])>{{ $badge }}</span>
    @endif
</a>
