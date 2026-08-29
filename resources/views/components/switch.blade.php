@props(['checked' => false, 'label' => null])
<label {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 cursor-pointer']) }}>
  <span class="relative inline-flex w-11 h-6 rounded-full bg-[#eceae5] transition-colors {{ $checked ? 'bg-navy-800' : '' }}">
    <input type="checkbox" {{ $checked ? 'checked' : '' }} class="sr-only peer">
    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
  </span>
  @if($label)<span class="text-sm font-medium text-ink">{{ $label }}</span>@endif
</label>
