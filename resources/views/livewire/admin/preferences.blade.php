<div class="max-w-[560px]">
<h1 class="font-serif text-2xl font-bold mb-4">Preferences</h1>
<div class="bg-panel border rounded-xl p-5 space-y-3">
<div><label class="text-xs font-semibold">Desk</label><select wire:model="desk" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>English desk</option><option>Bangla desk</option><option>Business</option><option>Photo desk</option></select></div>
<div><label class="text-xs font-semibold">Timezone</label><select wire:model="timezone" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>Asia/Dhaka</option><option>UTC</option></select></div>
<x-btn variant="primary" wire:click="save">Save</x-btn>
</div>
<x-toast />
</div>
