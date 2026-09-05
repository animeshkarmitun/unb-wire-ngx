<header class="sticky top-1 z-40 flex items-center gap-4 px-4 lg:px-[38px] py-[11px] bg-white/90 backdrop-blur border-b border-border">
    <button type="button" class="lg:hidden w-9 h-9 rounded-[9px] border border-border bg-white flex items-center justify-center text-ink hover:border-navy-800" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle navigation">
        <x-lucide-menu class="w-[18px] h-[18px]" stroke-width="1.8" />
    </button>
    {{-- Search ("/" focuses) --}}
    <div class="flex-1 max-w-[400px] flex items-center gap-[9px] bg-paper border border-border rounded-[10px] px-[13px] py-[9px] transition-colors focus-within:border-navy-800">
        <x-lucide-search class="w-[15px] h-[15px] shrink-0 text-muted-2" stroke-width="1.8" />
        <input id="topnavSearch" type="text" placeholder="Search stories, clients, photos…" class="border-0 outline-none bg-transparent text-[13.5px] w-full text-ink placeholder:text-muted-2 p-0 focus:ring-0">
        <kbd class="text-[11px] text-muted-2 border border-border rounded-[5px] px-[7px] py-px bg-white">/</kbd>
    </div>

    <div class="ml-auto flex items-center gap-[13px]">
        {{-- Live Dhaka clock --}}
        <div class="hidden lg:flex items-center gap-2 whitespace-nowrap text-[12.5px] text-muted font-medium">
            <span class="w-[7px] h-[7px] rounded-full bg-green shadow-[0_0_0_3px_#e5f6ec]"></span>
            <span id="clockTime">Dhaka</span>
        </div>

        {{-- Client portal (subscriber view) --}}
        <a href="{{ config('app.portal_url', 'http://localhost:3000') }}" target="_blank" rel="noopener" title="Client portal (subscriber view)" class="relative w-[38px] h-[38px] rounded-[10px] border border-border bg-white text-[#4b4e5c] flex items-center justify-center transition-colors hover:border-navy-800 hover:text-navy-800">
            <x-lucide-globe class="w-[17px] h-[17px]" stroke-width="1.8" />
        </a>

        {{-- Notifications --}}
        @php $notifs = Auth::user()->notifications()->latest()->limit(5)->get(); $unread = Auth::user()->unreadNotifications()->count(); @endphp
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open" title="Notifications" class="relative w-[38px] h-[38px] rounded-[10px] border border-border bg-white text-[#4b4e5c] flex items-center justify-center transition-colors hover:border-navy-800 hover:text-navy-800">
                <x-lucide-bell class="w-[17px] h-[17px]" stroke-width="1.8" />
                @if($unread>0)<span class="absolute -top-[5px] -right-[5px] min-w-[17px] h-[17px] px-1 rounded-full bg-crimson text-white text-[10px] font-bold flex items-center justify-center border-2 border-white">{{ $unread }}</span>@endif
            </button>
            <div x-show="open" x-transition.opacity.duration.150ms x-cloak class="absolute right-0 top-[calc(100%+8px)] w-[320px] bg-white border border-border rounded-xl shadow-[0_14px_34px_rgba(15,23,48,0.14)] p-1.5 z-[60]">
                <div class="text-[11px] uppercase tracking-[0.07em] text-muted-2 font-semibold px-[11px] pt-2 pb-[5px]">Notifications @if($unread>0)<span class="bg-crimson text-white px-1.5 rounded-full text-[10px]">{{ $unread }} new</span>@endif</div>
                @forelse($notifs as $n)
                <div class="flex gap-2.5 items-center px-[11px] py-[9px] rounded-lg text-[13px] text-ink {{ is_null($n->read_at)?'bg-paper':'' }}">
                    <span class="w-2 h-2 rounded-full mt-[5px] shrink-0 self-start {{ $n->data['event']==='review_requested'?'bg-amber':($n->data['event']==='published'?'bg-green':'bg-blue') }}"></span>
                    <div>
                        <div class="text-[12.5px] leading-[1.45]">{{ $n->data['data']['headline'] ?? $n->data['event'] }}</div>
                        <div class="text-[11px] text-muted-2 mt-0.5">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <div class="text-xs text-muted p-3 text-center">No notifications — burst-collapsed per thread per 5 min.</div>
                @endforelse
                <a href="{{ route('admin.distribution') }}" class="block text-center text-[12px] text-crimson font-semibold px-2 pt-[9px] pb-[7px] mt-1 border-t border-border hover:underline">View distribution log</a>
            </div>
        </div>

        {{-- User --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open" class="flex items-center gap-2.5 border border-border bg-white rounded-[11px] pl-1.5 pr-3 py-[5px] transition-colors hover:border-navy-800">
                <span class="w-[30px] h-[30px] rounded-lg bg-navy-800 text-white text-[11px] font-bold flex items-center justify-center">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}{{ strtoupper(substr(strrpos(Auth::user()->name, ' ') !== false ? substr(Auth::user()->name, strrpos(Auth::user()->name, ' ') + 1) : '', 0, 1)) }}</span>
                <span class="hidden lg:block text-left leading-[1.25]">
                    <span class="block text-[13px] font-semibold text-ink">{{ Auth::user()->name }}</span>
                    <span class="block text-[10.5px] text-muted-2">{{ Auth::user()->email }}</span>
                </span>
                <x-lucide-chevron-down class="w-3 h-3 text-muted-2" stroke-width="2" />
            </button>
            <div x-show="open" x-transition.opacity.duration.150ms x-cloak class="absolute right-0 top-[calc(100%+8px)] w-[230px] bg-white border border-border rounded-xl shadow-[0_14px_34px_rgba(15,23,48,0.14)] p-1.5 z-[60]">
                <a href="{{ route('profile.edit') }}" class="flex gap-2.5 items-center px-[11px] py-[9px] rounded-lg text-[13px] text-ink hover:bg-paper">
                    <x-lucide-user class="w-[15px] h-[15px] shrink-0 text-[#4b4e5c]" stroke-width="1.8" />
                    My profile
                </a>
                <a href="#" class="flex gap-2.5 items-center px-[11px] py-[9px] rounded-lg text-[13px] text-ink hover:bg-paper">
                    <x-lucide-settings class="w-[15px] h-[15px] shrink-0 text-[#4b4e5c]" stroke-width="1.8" />
                    Preferences
                </a>
                <div class="h-px bg-border my-[5px] mx-2"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex gap-2.5 items-center px-[11px] py-[9px] rounded-lg text-[13px] text-crimson-dark font-semibold hover:bg-paper">
                        <x-lucide-log-out class="w-[15px] h-[15px] shrink-0 text-crimson" stroke-width="1.8" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

@once
    <script>
        // Live Dhaka clock (business timezone Asia/Dhaka; storage is UTC)
        (function () {
            function tickClock() {
                const now = new Date();
                const d = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', weekday: 'short', day: 'numeric', month: 'short' }).format(now);
                const t = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Dhaka', hour: 'numeric', minute: '2-digit', hour12: true }).format(now);
                const el = document.getElementById('clockTime');
                if (el) el.textContent = 'Dhaka · ' + d + ' · ' + t;
            }
            tickClock();
            setInterval(tickClock, 30000);

            // "/" focuses the topnav search
            document.addEventListener('keydown', function (e) {
                if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) && !e.target.isContentEditable) {
                    e.preventDefault();
                    document.getElementById('topnavSearch')?.focus();
                }
            });
        })();
    </script>
@endonce
