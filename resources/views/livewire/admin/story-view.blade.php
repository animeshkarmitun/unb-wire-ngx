<div class="max-w-[780px]">
<a href="{{ route('admin.news','en') }}" class="text-xs text-muted hover:text-ink">← Back to news</a>
<div class="mt-3">
<div class="text-xs text-muted-2">{{ $story->category->name_en ?? '' }} · {{ $story->published_at?->format('M j, Y H:i') ?? $story->status }} @if($story->is_breaking)<span class="bg-crimson text-white px-1.5 py-0.5 rounded text-[10px] font-bold ml-1">BREAKING</span>@endif</div>
<h1 class="font-serif text-[28px] font-bold leading-tight mt-1">{{ $story->headline }}</h1>
@if($story->sub_head)<h2 class="text-[16px] text-muted mt-2">{{ $story->sub_head }}</h2>@endif
<div class="text-xs text-muted-2 mt-2">{{ $story->dateline_city }} @if($story->dateline_at)· {{ $story->dateline_at->format('M j, Y') }}@endif · {{ $story->word_count }} words</div>
</div>
@if($story->brief)<div class="mt-4 p-3 bg-paper border rounded-lg text-sm italic">{{ $story->brief }}</div>@endif
<div class="prose prose-sm max-w-none mt-4 text-[15px] leading-relaxed">{!! $story->body_html !!}</div>
<div class="mt-6 flex gap-2">
<a href="{{ route('admin.add-news') }}?id={{ $story->id }}" class="px-4 py-2 rounded-lg bg-navy-800 text-white text-sm font-bold">Edit</a>
</div>
</div>
