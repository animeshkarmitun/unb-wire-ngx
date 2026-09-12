<div class="max-w-[560px]">
<h1 class="font-serif text-2xl font-bold mb-4">Preferences</h1>
<div class="bg-panel border rounded-xl p-5 space-y-3">
<div><label class="text-xs font-semibold">Desk</label><select wire:model="desk" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>English desk</option><option>Bangla desk</option><option>Business</option><option>Photo desk</option></select></div>
<div><label class="text-xs font-semibold">Timezone</label><select wire:model="timezone" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>Asia/Dhaka</option><option>UTC</option></select></div>
<div><label class="text-xs font-semibold">Date Format</label><select wire:model="dateFormat" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="dmy">Day Month, Time (13 Sep, 2:30 PM)</option><option value="mdy">Month Day, Time (Sep 13, 2:30 PM)</option><option value="iso">ISO (2026-09-13 14:30)</option></select></div>
<div><label class="text-xs font-semibold">Density</label><select wire:model="density" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></div>
@error('dateFormat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
@error('density') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
<x-btn variant="primary" wire:click="save">Save</x-btn>
</div>
<x-toast />
</div>
