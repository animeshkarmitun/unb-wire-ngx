<div>
<div class="mb-4"><div class="text-xs text-muted-2 mb-1">Home / Distribution / {{ $service==='en'?'English Service':'Bangla Service' }}</div><h1 class="font-serif text-2xl font-bold">{{ $service==='en'?'English Service':'Bangla Service' }}</h1><p class="text-sm text-muted">Wire feed configuration — languages, categories, and entitlement defaults for this service.</p></div>
<div class="bg-panel border rounded-xl p-5 max-w-[640px] space-y-3">
<div><label class="text-xs font-semibold">Wire name</label><input wire:model="wireName" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" placeholder="UNB English Wire"></div>
<div><label class="text-xs font-semibold">Description</label><textarea wire:model="description" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" rows="3"></textarea></div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="enabled"> Enabled</label>
<x-btn variant="primary" wire:click="save">Save</x-btn>
</div>
<x-toast />
</div>
