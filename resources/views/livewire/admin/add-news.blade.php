<div>
<div class="flex items-center justify-between mb-4">
<div>
<div class="text-xs text-muted-2 mb-1"><a href="{{ route('dashboard') }}" class="hover:text-crimson-dark">Home</a> / Add News</div>
<h1 class="font-serif text-2xl font-bold">Add News <span class="text-sm font-normal text-muted">{{ $language==='bn'?'— Bangla':'— English' }}</span></h1>
</div>
<div class="flex items-center gap-2">
<span class="text-xs px-2.5 py-1 rounded-full bg-paper border">{{ $storyId ? 'Draft #'.$storyId : 'New draft' }}</span>
<label class="text-xs flex items-center gap-1"><input type="checkbox" wire:model.live="isBreaking"> <span class="font-bold text-crimson">Breaking</span></label>
</div>
</div>

<div class="flex gap-1 mb-4">
@foreach([1=>'Basics',2=>'Body',3=>'Media',4=>'Review'] as $i=>$label)
<button wire:click="go({{ $i }})" class="flex-1 py-2.5 rounded-lg text-sm font-semibold border flex items-center justify-center gap-2 {{ $step===$i?'bg-navy-800 text-white border-navy-800':($step>$i?'bg-green-bg text-green border-green':'bg-panel border-[#e3e1da] text-muted') }}">
<span class="w-6 h-6 rounded-full flex items-center justify-center text-xs {{ $step===$i?'bg-white text-navy-800':($step>$i?'bg-green text-white':'bg-[#f3f1ee]') }}">{{ $step>$i?'✓':$i }}</span>{{ $label }}
</button>
@endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1.8fr_0.9fr] gap-4 items-start">
<div class="bg-panel border border-border rounded-xl p-5">
@if($step===1)
<div class="space-y-3">
<div class="flex gap-2">
<button wire:click="$set('language','en')" class="px-3 py-1 rounded-full text-xs font-bold border {{ $language==='en'?'bg-navy-800 text-white':'bg-white' }}">English</button>
<button wire:click="$set('language','bn')" class="px-3 py-1 rounded-full text-xs font-bold border {{ $language==='bn'?'bg-navy-800 text-white':'bg-white' }}">Bangla</button>
</div>
<div><label class="text-xs font-semibold">Headline *</label><input wire:model="headline" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" placeholder="Headline"> @error('headline')<span class="text-xs text-crimson">{{ $message }}</span>@enderror @if(!empty($aiTouched['headline']))<span class="text-[11px] text-purple font-bold">✦ AI — unreviewed</span>@endif</div>
<div><label class="text-xs font-semibold">Sub head</label><input wire:model="subHead" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Brief * (280)</label><textarea wire:model="brief" rows="2" maxlength="280" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></textarea> @error('brief')<span class="text-xs text-crimson">{{ $message }}</span>@enderror</div>
<div class="grid grid-cols-2 gap-3">
<div><label class="text-xs font-semibold">Category *</label><select wire:model.live="categoryId" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="">Select category</option>@foreach($cats as $c)<option value="{{ $c->id }}">{{ $c->name_en }} / {{ $c->name_bn }}</option>@endforeach</select> @error('categoryId')<span class="text-xs text-crimson">{{ $message }}</span>@enderror</div>
<div><label class="text-xs font-semibold">Sub category</label><select wire:model="subCategoryId" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="">—</option>@foreach($subs as $s)<option value="{{ $s->id }}">{{ $s->name_en }}</option>@endforeach</select></div>
</div>
<div class="grid grid-cols-3 gap-3">
<div><label class="text-xs font-semibold">Dateline city</label><input wire:model="datelineCity" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" placeholder="Dhaka"></div>
<div><label class="text-xs font-semibold">Priority</label><select wire:model="priority" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="routine">routine</option><option value="urgent">urgent</option><option value="flash">flash</option></select></div>
<div><label class="text-xs font-semibold">Embargo until</label><input wire:model="embargoUntil" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
</div>
</div>
@elseif($step===2)
<div class="space-y-3">
<div class="flex gap-2 mb-1 flex-wrap">
<x-btn variant="outline" size="sm" wire:click="callAi('preedit')" wire:loading.attr="disabled">{{ $aiLoading?'Thinking…':'✦ Pre-edit draft' }}</x-btn>
<x-btn variant="outline" size="sm" wire:click="callAi('tags')">Tags & category</x-btn>
<x-btn variant="outline" size="sm" wire:click="callAi('generate')">Generate</x-btn>
</div>
@if($aiPack)
<div class="bg-purple-bg border border-purple/20 rounded-lg p-3 text-xs">
<div class="font-bold text-purple mb-1">AI suggestion</div>
@if(isset($aiPack['headline']))<div>Headline: {{ $aiPack['headline'] }} <button wire:click="applyAi('headline')" class="ml-2 px-2 py-0.5 rounded bg-purple text-white font-bold">Apply</button></div>@endif
@if(isset($aiPack['brief']))<div>Brief: {{ $aiPack['brief'] }} <button wire:click="applyAi('brief')" class="ml-1 px-2 py-0.5 rounded bg-purple text-white font-bold">Apply</button></div>@endif
@if(isset($aiPack['body']))<div>Body: {!! \Illuminate\Support\Str::limit(strip_tags($aiPack['body']),80) !!} <button wire:click="applyAi('body')" class="ml-1 px-2 py-0.5 rounded bg-purple text-white font-bold">Apply</button></div>@endif
@if(isset($aiPack['category']))<div>Category: {{ $aiPack['category']['name'] }} <button wire:click="applyAi('category')" class="ml-1 px-2 py-0.5 rounded bg-purple text-white font-bold">Apply</button></div>@endif
</div>
@endif
<label class="text-xs font-semibold">Body * (Quill)</label>
<div wire:ignore class="mt-1">
<div id="quillEditor" class="bg-white border rounded-lg min-h-[280px]"></div>
</div>
<textarea wire:model="bodyHtml" class="hidden"></textarea>
@error('bodyHtml')<span class="text-xs text-crimson">{{ $message }}</span>@enderror
@if(!empty($aiTouched['body']))<div class="text-xs text-purple font-bold">✦ AI — body unreviewed</div>@endif
<div class="text-xs text-muted">Word count: {{ str_word_count(strip_tags($bodyHtml)) }}</div>
</div>
@elseif($step===3)
<div class="space-y-3">
<div class="text-xs font-bold uppercase text-muted-2">Attach media (story_media)</div>
<div class="grid grid-cols-3 md:grid-cols-4 gap-2">
@foreach($media as $m)
<button wire:click="toggleMedia({{ $m->id }})" class="relative border rounded-lg overflow-hidden text-left {{ in_array($m->id,$selectedMediaIds)?'ring-2 ring-navy-800':'' }}">
<div class="aspect-[4/3] {{ 'g'.(($m->id%8)+1) }} flex items-center justify-center text-xs text-white font-bold">{{ $m->kind }}</div>
<div class="p-1.5"><div class="text-xs font-semibold truncate">{{ $m->title }}</div><div class="text-[11px] text-muted truncate">{{ \Illuminate\Support\Str::limit($m->caption??'',30) }}</div></div>
@if(in_array($m->id,$selectedMediaIds))<span class="absolute top-1 right-1 w-5 h-5 bg-green text-white rounded-full flex items-center justify-center text-xs">✓</span>@endif
</button>
@endforeach
</div>
@if($media->isEmpty())<div class="text-xs text-muted p-4 border-2 border-dashed rounded-lg text-center">No library photos. Upload in Photo Manager.</div>@endif
<div class="text-xs text-muted">{{ count($selectedMediaIds) }} attached</div>
</div>
@else
<div class="space-y-3">
<div class="bg-amber/10 border border-amber/30 rounded-lg p-3 text-xs">
<b>Publish checklist</b>
<label class="flex gap-2 mt-2"><input type="checkbox" class="accent-crimson"> Headline reviewed</label>
<label class="flex gap-2"><input type="checkbox" class="accent-crimson"> Body factual</label>
<label class="flex gap-2"><input type="checkbox" class="accent-crimson"> Category & tags correct</label>
@if(!empty($aiTouched))<div class="text-purple font-bold mt-1">✦ AI-touched — human edit required before publish</div>@endif
</div>
<div class="bg-paper border rounded-lg p-4">
<div class="font-serif text-lg font-bold">{{ $headline ?: 'Untitled' }}</div>
<div class="text-xs text-muted mt-1">{{ $brief }}</div>
<div class="prose prose-sm max-w-none mt-3 text-sm">{!! $bodyHtml !!}</div>
@if(count($selectedMediaIds)>0)<div class="text-xs mt-2">Attached media: {{ count($selectedMediaIds) }}</div>@endif
</div>
<div class="flex gap-2">
<x-btn variant="outline" wire:click="autosave">Save draft</x-btn>
<x-btn variant="primary" wire:click="sendToReview">Send to review</x-btn>
<x-btn variant="navy" wire:click="publish">Publish</x-btn>
</div>
</div>
@endif

<div class="flex justify-between mt-5">
<x-btn variant="outline" wire:click="prev" :disabled="$step===1">← Back</x-btn>
@if($step<4)<x-btn variant="primary" wire:click="next">Continue →</x-btn>@endif
</div>
</div>

<div class="space-y-3">
<div class="bg-panel border border-border rounded-xl p-4">
<div class="text-xs font-bold uppercase text-muted-2 mb-2">Desk workflow</div>
<div class="text-sm flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber"></span> Draft @if($storyId)<span class="text-xs text-muted">#{{ $storyId }}</span>@endif</div>
<div class="text-xs text-muted mt-1">Owner: {{ auth()->user()->name }}</div>
<div class="mt-3 p-2 bg-purple-bg border border-purple/20 rounded-lg text-xs">
<b class="text-purple">AI desk</b><br>
<button wire:click="callAi('preedit')" class="mt-2 w-full py-1.5 rounded-lg bg-purple text-white text-xs font-bold">Run pre-edit</button>
<button wire:click="callAi('tags')" class="mt-1 w-full py-1 rounded-lg border text-xs font-bold">Tags & category</button>
</div>
</div>
<div class="bg-panel border border-border rounded-xl p-4">
<div class="text-xs font-bold uppercase text-muted-2 mb-2">Internal notes</div>
<div class="text-xs text-muted">Newsroom-only thread — never on client feeds.</div>
<textarea placeholder="Add a note…" class="w-full border rounded-lg px-3 py-2 text-sm mt-2"></textarea>
<button class="mt-2 w-full py-1.5 rounded-lg bg-navy-800 text-white text-xs font-bold">Add note</button>
</div>
</div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet" integrity="sha384-2ea2b8f0a1c7c9e0a0b0c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" integrity="sha384-2f1a5dff8a3d2b6c1e5f0a9d1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f" crossorigin="anonymous"></script>
<script>
document.addEventListener('livewire:init', ()=>{
  const el=document.getElementById('quillEditor');
  if(!el) return;
  const quill=new Quill(el,{theme:'snow', modules:{toolbar:[['bold','italic','underline'],[{header:[1,2,3,false]}],['blockquote','code-block'],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']]}});
  quill.root.innerHTML=@json($bodyHtml ?: '<p></p>');
  quill.on('text-change', ()=>{ const html=quill.root.innerHTML; @this.set('bodyHtml', html); });
  Livewire.on('ai-applied', (html)=>{ quill.root.innerHTML=html; });
});
</script>
@endpush

<x-toast />
</div>
