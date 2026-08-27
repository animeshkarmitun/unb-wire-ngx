@props(['title' => null])
<div {{ $attributes->merge(['class' => 'fixed inset-0 z-[70] flex justify-end']) }} x-cloak>
<div class="absolute inset-0 bg-navy-900/50 backdrop-blur-sm" wire:click="$dispatch('close-drawer')"></div>
<div class="relative w-[min(620px,100%)] bg-paper h-full shadow-2xl flex flex-col overflow-hidden">
{{ $slot }}
</div>
</div>
