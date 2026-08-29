<x-admin-layout>
    <x-slot:title>Dashboard</x-slot:title>

    <div class="flex items-center justify-between mb-7">
        <h1 class="font-serif text-3xl font-semibold tracking-[-0.01em] text-ink">Dashboard</h1>
        <div class="flex gap-3">
            <button type="button" class="text-sm font-medium px-5 py-[11px] rounded-[10px] bg-panel border border-[#e3e1da] text-ink shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:border-navy-800 hover:text-navy-800">Export report</button>
            <a href="{{ route('admin.add-news') }}" class="text-sm font-medium px-5 py-[11px] rounded-[10px] bg-crimson text-white shadow-[0_2px_8px_rgba(229,72,77,0.3)] transition-colors hover:bg-crimson-dark">+ New story</a>
        </div>
    </div>

    {{-- KPI cards (static placeholder data — real metrics are a later task) --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-[18px] mb-[26px]">
        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-blue border-tint-blue-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Stories published today</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-blue">
                    <x-lucide-newspaper class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $publishedToday ?? 12 }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold text-green">
                <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                live today
            </div>
        </div>

        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-mint border-tint-mint-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Active clients</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-green">
                    <x-lucide-users class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $activeClients ?? 47 }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold text-green">
                <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                total active
            </div>
        </div>

        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-peach border-tint-peach-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Distribution success rate</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-amber">
                    <x-lucide-activity class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $successRate ?? 98.4 }}%</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold text-green">
                <x-lucide-activity class="w-[11px] h-[11px]" stroke-width="2" />
                deliveries
            </div>
        </div>

        <div class="border rounded-[14px] px-5 pt-[18px] pb-5 shadow-[0_1px_3px_rgba(28,31,46,0.04)] bg-tint-rose border-tint-rose-border">
            <div class="flex items-start justify-between gap-2.5">
                <div class="text-[13.5px] text-[#5d6172] leading-[1.45] min-h-10 font-medium">Exclusive content sent</div>
                <div class="w-9 h-9 rounded-[10px] flex items-center justify-center shrink-0 bg-pink">
                    <x-lucide-star class="w-[17px] h-[17px] text-white" stroke-width="2" />
                </div>
            </div>
            <div class="font-serif text-[34px] font-semibold tracking-[-0.01em] my-2 mb-2.5 text-ink">{{ $exclusiveToday ?? 8 }}</div>
            <div class="flex items-center gap-[5px] text-[13px] font-semibold text-green">
                <x-lucide-arrow-up class="w-[11px] h-[11px]" stroke-width="2" />
                +5 from yesterday
            </div>
        </div>
    </section>

    {{-- Lower section --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.9fr_1fr] gap-[18px] items-start">
        {{-- Recent stories --}}
        <div class="bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] overflow-hidden">
            <div class="flex items-baseline justify-between px-6 pt-5 pb-3.5">
                <span class="font-serif text-[19px] font-semibold">Recent stories</span>
                <a href="#" class="text-[13px] text-crimson font-medium hover:text-crimson-dark hover:underline">View all</a>
            </div>
            <div class="grid grid-cols-[1fr_130px_90px] px-6 py-2.5 border-y border-border text-[11.5px] tracking-[0.06em] uppercase text-muted-2 font-semibold bg-paper">
                <span>Story</span><span>Status</span><span>Distribution</span>
            </div>

            <div class="grid grid-cols-[1fr_130px_90px] items-center gap-3 px-6 py-5 border-b border-border transition-colors hover:bg-[#fcfbf8]">
                <div>
                    <div class="font-serif text-[16.5px] font-semibold leading-[1.35] tracking-[-0.005em] mb-[7px]">Nandini killing: Man sentenced to death in Lalmonirhat</div>
                    <div class="text-[12.5px] text-muted mb-2.5">English · Bangladesh · 342 words</div>
                    <div class="flex gap-2 flex-wrap"><span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] bg-blue-bg text-blue">Standard</span></div>
                </div>
                <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-blue-bg text-blue"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Distributed</span>
                <span class="w-[34px] h-1.5 rounded-full bg-gradient-to-r from-green to-[#4ade80] justify-self-start"></span>
            </div>

            <div class="grid grid-cols-[1fr_130px_90px] items-center gap-3 px-6 py-5 border-b border-border transition-colors hover:bg-[#fcfbf8]">
                <div>
                    <div class="font-serif text-[16.5px] font-semibold leading-[1.35] tracking-[-0.005em] mb-[7px]">Doctors warn of rise in seasonal flu symptoms</div>
                    <div class="text-[12.5px] text-muted mb-2.5">English · Health · 420 words</div>
                    <div class="flex gap-2 flex-wrap">
                        <span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] bg-pink-bg text-pink">Exclusive</span>
                        <span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] bg-lime-bg text-lime">Photo pack</span>
                    </div>
                </div>
                <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-blue-bg text-blue"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Distributed</span>
                <span class="w-[34px] h-1.5 rounded-full bg-gradient-to-r from-green to-[#4ade80] justify-self-start"></span>
            </div>

            <div class="grid grid-cols-[1fr_130px_90px] items-center gap-3 px-6 py-5 border-b border-border transition-colors hover:bg-[#fcfbf8]">
                <div>
                    <div class="font-serif text-[16.5px] font-semibold leading-[1.35] tracking-[-0.005em] mb-[7px]">Govt prioritises creative economy to raise GDP contribution</div>
                    <div class="text-[12.5px] text-muted mb-2.5">English · Business · 560 words</div>
                    <div class="flex gap-2 flex-wrap"><span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] bg-blue-bg text-blue">Standard</span></div>
                </div>
                <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-blue-bg text-blue"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Distributed</span>
                <span class="w-[34px] h-1.5 rounded-full bg-gradient-to-r from-green to-[#4ade80] justify-self-start"></span>
            </div>

            <div class="grid grid-cols-[1fr_130px_90px] items-center gap-3 px-6 py-5 transition-colors hover:bg-[#fcfbf8]">
                <div>
                    <div class="font-serif text-[16.5px] font-semibold leading-[1.35] tracking-[-0.005em] mb-[7px]">Rangpur family of four hospitalised after objecting to Yaba consumption</div>
                    <div class="text-[12.5px] text-muted mb-2.5">English · Crime · 310 words</div>
                    <div class="flex gap-2 flex-wrap"><span class="text-[11.5px] font-semibold px-[11px] py-1 rounded-[7px] bg-blue-bg text-blue">Standard</span></div>
                </div>
                <span class="inline-flex items-center gap-[7px] text-[12.5px] font-semibold px-3.5 py-[7px] rounded-lg justify-self-start bg-green-bg text-green"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Published</span>
                <span class="w-[34px] h-1.5 rounded-full bg-gradient-to-r from-green to-[#4ade80] justify-self-start"></span>
            </div>
        </div>

        {{-- Top clients --}}
        <div class="bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] overflow-hidden">
            <div class="flex items-baseline justify-between px-6 pt-5 pb-3.5">
                <span class="font-serif text-[19px] font-semibold">Top clients today</span>
                <a href="#" class="text-[13px] text-crimson font-medium hover:text-crimson-dark hover:underline">All clients</a>
            </div>

            <div class="flex gap-4 items-start px-6 py-5 border-b border-border transition-colors hover:bg-[#fcfbf8]">
                <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center text-sm font-bold shrink-0 mt-1 bg-crimson-soft text-crimson-dark">DS</div>
                <div>
                    <div class="font-serif text-[16.5px] font-semibold mb-[5px]">Daily Star</div>
                    <div class="text-[12.5px] text-muted leading-[1.55]"><strong class="text-[#4b4e5c] font-semibold">Premium</strong> · <strong class="text-[#4b4e5c] font-semibold">12 stories</strong> downloaded<br>All content</div>
                </div>
            </div>

            <div class="flex gap-4 items-start px-6 py-5 border-b border-border transition-colors hover:bg-[#fcfbf8]">
                <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center text-sm font-bold shrink-0 mt-1 bg-blue-bg text-blue">BD</div>
                <div>
                    <div class="font-serif text-[16.5px] font-semibold mb-[5px]">Bangladesh Today</div>
                    <div class="text-[12.5px] text-muted leading-[1.55]"><strong class="text-[#4b4e5c] font-semibold">Standard</strong> · <strong class="text-[#4b4e5c] font-semibold">6 stories</strong> downloaded<br>Text only</div>
                </div>
            </div>

            <div class="flex gap-4 items-start px-6 py-5 transition-colors hover:bg-[#fcfbf8]">
                <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center text-sm font-bold shrink-0 mt-1 bg-[#fdf3e0] text-[#b7791f]">JN</div>
                <div>
                    <div class="font-serif text-[16.5px] font-semibold mb-[5px]">Jugantor</div>
                    <div class="text-[12.5px] text-muted leading-[1.55]"><strong class="text-[#4b4e5c] font-semibold">Basic</strong> · <strong class="text-[#4b4e5c] font-semibold">3 stories</strong> downloaded<br>Headlines only</div>
                </div>
            </div>
        </div>
    </section>
</x-admin-layout>
