<div>
<div class="mb-4"><div class="text-xs text-muted-2 mb-1">Home / Distribution / Delivery settings</div><h1 class="font-serif text-2xl font-bold">Delivery settings</h1><p class="text-sm text-muted">At-least-once fan-out, backoff, auto-pause — NFR §6.</p></div>
<div class="bg-panel border rounded-xl p-5 max-w-[560px] space-y-3">
<div class="grid grid-cols-3 gap-3">
<div><label class="text-xs font-semibold">Retry attempts</label><input wire:model="retryAttempts" type="number" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Backoff (s)</label><input wire:model="backoffSeconds" type="number" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Auto-pause after</label><input wire:model="autoPauseAfter" type="number" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
</div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="atLeastOnce"> At-least-once (idempotency key)</label>
<x-btn variant="primary" wire:click="save">Save</x-btn>
</div>
<x-toast />
</div>
