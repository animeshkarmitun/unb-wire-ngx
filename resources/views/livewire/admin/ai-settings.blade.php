<div>
<div class="mb-6">
<div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / Settings / AI settings</div>
<div class="flex items-center gap-3">
<h1 class="font-serif text-[28px] font-semibold">AI settings</h1>
@if($killed)<span class="bg-crimson text-white text-xs font-bold px-3 py-1 rounded-full">KILL SWITCH ON</span>@endif
</div>
<p class="text-sm text-muted mt-1">Per-desk toggles, token budgets, auto-publish guardrails — audited. Add News reads this.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1.6fr_0.9fr] gap-4 items-start">
<div class="space-y-4">
<div class="bg-panel border border-border rounded-xl p-5">
<h3 class="text-xs font-bold uppercase tracking-wide text-muted-2 mb-3">Desks</h3>
<label class="flex items-center justify-between py-2 cursor-pointer"><span class="text-sm font-medium">English desk — Pre-edit</span><input type="checkbox" wire:model.live="preeditEn" class="w-10 h-6 rounded-full appearance-none bg-[#ddd9d0] checked:bg-green relative before:content-[''] before:absolute before:w-4 before:h-4 before:bg-white before:rounded-full before:top-1 before:left-1 checked:before:translate-x-4 transition"></label>
<label class="flex items-center justify-between py-2 cursor-pointer"><span class="text-sm font-medium">Bangla desk — Pre-edit</span><input type="checkbox" wire:model.live="preeditBn" class="w-10 h-6 rounded-full appearance-none bg-[#ddd9d0] checked:bg-green relative before:content-[''] before:absolute before:w-4 before:h-4 before:bg-white before:rounded-full before:top-1 before:left-1 checked:before:translate-x-4 transition"></label>
<label class="flex items-center justify-between py-2 cursor-pointer"><span class="text-sm font-medium">Auto-publish (routine categories only)</span><input type="checkbox" wire:model.live="autoPublish" class="w-10 h-6 rounded-full appearance-none bg-[#ddd9d0] checked:bg-amber relative before:content-[''] before:absolute before:w-4 before:h-4 before:bg-white before:rounded-full before:top-1 before:left-1 checked:before:translate-x-4 transition"></label>
@if($autoPublish)
<div class="mt-2 text-xs"><div class="font-semibold mb-1">Allowlisted categories for auto-publish</div>
@foreach(['Bangladesh','World','Sports','Business'] as $cat)
<label class="flex items-center gap-2 py-1"><input type="checkbox" value="{{ $cat }}" wire:model.live="autoCats"> <span class="text-sm">{{ $cat }}</span></label>
@endforeach
</div>
@endif
</div>

<div class="bg-panel border border-border rounded-xl p-5">
<h3 class="text-xs font-bold uppercase tracking-wide text-muted-2 mb-3">Budget & Model</h3>
<div class="grid grid-cols-2 gap-3">
<div><label class="text-xs font-semibold">Monthly token cap</label><input wire:model="monthlyCap" type="number" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Model</label><select wire:model="model" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>openai:gpt-4o</option><option>openai:gpt-4o-mini</option><option>anthropic:claude-3</option></select></div>
</div>
<div class="mt-3"><label class="text-xs font-semibold">House style prompt (versioned)</label><textarea wire:model="stylePrompt" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" placeholder="UNB house style: concise, factual…"></textarea></div>
</div>

<div class="bg-panel border border-border rounded-xl p-5">
<h3 class="text-xs font-bold uppercase tracking-wide text-muted-2 mb-2">Kill switch</h3>
<p class="text-xs text-muted mb-3">When ON, every AI call is blocked and returns immediately — audited as <code>ai.kill_switch.on</code>.</p>
<button wire:click="toggleKill" class="px-4 py-2 rounded-lg text-sm font-bold border {{ $killed?'bg-crimson text-white border-crimson':'bg-white border-[#e3e1da] hover:border-crimson' }}">{{ $killed?'Disable kill switch':'Enable kill switch' }}</button>
</div>

<div class="flex justify-end"><x-btn variant="primary" wire:click="save">Save AI settings</x-btn></div>
</div>

<div class="space-y-4">
<div class="bg-purple-bg border border-purple/20 rounded-xl p-4">
<h4 class="text-sm font-bold text-purple flex items-center gap-2"><x-lucide-sparkles class="w-4 h-4"/> How Add News uses this</h4>
<p class="text-xs text-[#4b3a6b] mt-2 leading-relaxed">The 4-step wizard checks <code class="bg-white px-1 rounded">ai.desk.killed</code> before every <code>UNBAI._call</code>. Auto-publish is off by default — only routine categories in <code>autoCats</code> are allowed when the toggle is on. Every LLM call writes to <code>ai_generations</code> + <code>ai_token_usage_daily</code> for metering.</p>
</div>
<div class="bg-panel border border-border rounded-xl p-4">
<div class="text-xs font-bold uppercase text-muted-2 mb-2">Current JSON (settings.value)</div>
<pre class="text-xs font-mono bg-paper border rounded-lg p-3 overflow-x-auto">{{ json_encode(['preeditEn'=>$preeditEn,'preeditBn'=>$preeditBn,'autoPublish'=>$autoPublish,'autoCats'=>$autoCats,'monthlyCap'=>$monthlyCap,'killed'=>$killed,'model'=>$model], JSON_PRETTY_PRINT) }}</pre>
</div>
</div>
</div>

<x-toast />
</div>
