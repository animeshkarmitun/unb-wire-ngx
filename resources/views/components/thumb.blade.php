@props(['gradient' => 1, 'kind' => null])
@php $g = 'g'. max(1,min(8,intval($gradient))); @endphp
<div {{ $attributes->merge(['class' => "rounded-[8px] flex items-center justify-center text-white font-bold text-xs $g"]) }} style="min-height:88px">
  {{ $kind ?? $slot }}
</div>
