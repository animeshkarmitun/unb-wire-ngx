<x-admin-layout>
    <x-slot:title>Dashboard</x-slot:title>

    <div class="flex items-center justify-between mb-7">
        <h1 class="font-serif text-3xl font-semibold tracking-[-0.01em] text-ink">Dashboard</h1>
        <div class="flex gap-3">
            <button type="button" @click="$dispatch('toast', { message: 'Export report initiated' })" class="text-sm font-medium px-5 py-[11px] rounded-[10px] bg-panel border border-[#e3e1da] text-ink shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:border-navy-800 hover:text-navy-800">Export report</button>
            <a href="{{ route('admin.add-news') }}" class="text-sm font-medium px-5 py-[11px] rounded-[10px] bg-crimson text-white shadow-[0_2px_8px_rgba(229,72,77,0.3)] transition-colors hover:bg-crimson-dark">+ New story</a>
        </div>
    </div>

    {{-- KPI cards --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-[18px] mb-[26px]">
        {{-- Card 1: Stories published today --}}
        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-blue border-tint-blue-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Stories published today</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-blue">
                    <x-lucide-newspaper class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $publishedToday }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold {{ $deltaPublishedDirection === 'up' ? 'text-green' : 'text-crimson' }}">
                @if ($deltaPublishedDirection === 'up')
                    <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                @else
                    <x-lucide-arrow-down class="w-[11px] h-[11px]" stroke-width="2" />
                @endif
                {{ $deltaPublishedText }}
            </div>
        </div>

        {{-- Card 2: Active clients --}}
        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-mint border-tint-mint-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Active clients</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-green">
                    <x-lucide-users class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $activeClients }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold {{ $deltaClientsDirection === 'up' ? 'text-green' : 'text-crimson' }}">
                <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                {{ $deltaClientsText }}
            </div>
        </div>

        {{-- Card 3: Distribution success rate --}}
        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-peach border-tint-peach-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Distribution success rate</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-amber">
                    <x-lucide-activity class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $successRate }}%</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold {{ $deltaRateDirection === 'up' ? 'text-green' : 'text-crimson' }}">
                @if ($deltaRateDirection === 'up')
                    <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                @else
                    <x-lucide-arrow-down class="w-[11px] h-[11px]" stroke-width="2" />
                @endif
                {{ $deltaRateText }}
            </div>
        </div>

        {{-- Card 4: Exclusive content sent --}}
        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-rose border-tint-rose-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Exclusive content sent</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-pink">
                    <x-lucide-star class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $exclusiveToday }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold {{ $deltaExclusiveDirection === 'up' ? 'text-green' : 'text-crimson' }}">
                @if ($deltaExclusiveDirection === 'up')
                    <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                @else
                    <x-lucide-arrow-down class="w-[11px] h-[11px]" stroke-width="2" />
                @endif
                {{ $deltaExclusiveText }}
            </div>
        </div>
    </section>

    {{-- Lower section --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.9fr_1fr] gap-[18px] items-start">
        {{-- Recent stories --}}
        <div class="bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] overflow-hidden">
            <div class="flex items-baseline justify-between px-6 pt-5 pb-3.5">
                <span class="font-serif text-[19px] font-semibold">Recent stories</span>
                <a href="{{ route('admin.news', 'en') }}" class="text-[13px] text-crimson font-medium hover:text-crimson-dark hover:underline">View all</a>
            </div>
            <div class="grid grid-cols-[1fr_130px_90px] px-6 py-2.5 border-y border-border text-[11.5px] tracking-[0.06em] uppercase text-muted-2 font-semibold bg-paper">
                <span>Story</span><span>Status</span><span>Distribution</span>
            </div>

            @forelse ($recentStories as $story)
                <div class="grid grid-cols-[1fr_130px_90px] items-center gap-3 px-6 py-5 border-b border-border last:border-b-0 transition-colors hover:bg-[#fcfbf8]">
                    <div>
                        <div class="font-serif text-[16.5px] font-semibold leading-[1.35] tracking-[-0.005em] mb-[7px]">
                            <a href="{{ route('admin.story', $story['public_id']) }}" class="hover:text-crimson transition-colors">
                                {{ $story['headline'] }}
                            </a>
                        </div>
                        <div class="text-[12.5px] text-muted mb-2.5">{{ $story['meta'] }}</div>
                        <div class="flex gap-2 flex-wrap">
                            @foreach ($story['tags'] as $tag)
                                <span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] {{ $tag['class'] === 'exclusive' ? 'bg-pink-bg text-pink' : ($tag['class'] === 'photo' ? 'bg-lime-bg text-lime' : 'bg-blue-bg text-blue') }}">
                                    {{ $tag['name'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @if ($story['is_distributed'])
                        <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-blue-bg text-blue">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>Distributed
                        </span>
                    @elseif ($story['status'] === 'published')
                        <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-green-bg text-green">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>Published
                        </span>
                    @else
                        <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-amber-bg text-amber">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ ucfirst(str_replace('_', ' ', $story['status'])) }}
                        </span>
                    @endif
                    <div class="w-[34px] h-1.5 rounded-full bg-border overflow-hidden justify-self-start" title="{{ $story['dist_percent'] }}% delivered">
                        <div class="h-full rounded-full bg-gradient-to-r from-green to-[#4ade80]" style="width: {{ $story['dist_percent'] }}%"></div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-muted text-sm">
                    No recent stories available.
                </div>
            @endforelse
        </div>

        {{-- Top clients --}}
        <div class="bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] overflow-hidden">
            <div class="flex items-baseline justify-between px-6 pt-5 pb-3.5">
                <span class="font-serif text-[19px] font-semibold">Top clients today</span>
                <a href="{{ route('admin.clients') }}" class="text-[13px] text-crimson font-medium hover:text-crimson-dark hover:underline">All clients</a>
            </div>

            @forelse ($topClients as $client)
                <div class="flex gap-4 items-start px-6 py-5 border-b border-border last:border-b-0 transition-colors hover:bg-[#fcfbf8]">
                    <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center text-sm font-bold shrink-0 mt-1 {{ $client['tint'] === 'crimson' ? 'bg-crimson-soft text-crimson-dark' : ($client['tint'] === 'blue' ? 'bg-blue-bg text-blue' : 'bg-[#fdf3e0] text-[#b7791f]') }}">
                        {{ $client['initials'] }}
                    </div>
                    <div>
                        <div class="font-serif text-[16.5px] font-semibold mb-[5px]">{{ $client['name'] }}</div>
                        <div class="text-[12.5px] text-muted leading-[1.55]">
                            <strong class="text-[#4b4e5c] font-semibold">{{ $client['tier'] }}</strong> · <strong class="text-[#4b4e5c] font-semibold">{{ $client['downloads'] }} stories</strong> downloaded<br>{{ $client['scope'] }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-muted text-sm">
                    No client activity recorded yet.
                </div>
            @endforelse
        </div>
    </section>

    {{-- Download FAB --}}
    <button type="button" class="fixed right-[26px] bottom-[26px] w-[50px] h-[50px] rounded-full bg-navy-800 text-white shadow-[0_6px_18px_rgba(15,23,48,0.35)] flex items-center justify-center cursor-pointer transition-all hover:-translate-y-0.5 hover:bg-crimson z-40" title="Download" @click="$dispatch('toast', { message: 'Download initiated' })">
        <x-lucide-download class="w-[19px] h-[19px] text-white" stroke-width="2" />
    </button>
</x-admin-layout>

