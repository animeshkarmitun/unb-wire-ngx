<aside class="sticky top-1 max-h-[calc(100vh-4px)] overflow-y-auto self-start flex flex-col gap-7 bg-gradient-to-b from-navy-800 to-navy-900 px-4 pt-6 pb-7">
    {{-- Brand --}}
    <div class="flex items-center gap-[11px] px-2.5 pt-0.5 pb-1.5">
        <div class="w-9 h-9 rounded-[10px] bg-crimson text-white font-serif font-bold text-sm flex items-center justify-center tracking-[0.02em] shadow-[0_3px_10px_rgba(229,72,77,0.4)]">U</div>
        <div>
            <div class="text-white font-serif text-[16.5px] font-semibold leading-[1.2]">UNB Wire</div>
            <div class="text-navy-label text-[11px] tracking-[0.08em] uppercase">Newsroom</div>
        </div>
    </div>

    {{-- Overview --}}
    <nav>
        <div class="text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Overview</div>
        <x-nav-item-admin href="{{ route('dashboard') }}" icon="layout-dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="pen-line" badge="4" badge-variant="red">Content pipeline</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="users">Clients</x-nav-item-admin>
    </nav>

    {{-- Newsroom --}}
    <nav>
        <div class="text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Newsroom</div>
        <x-nav-item-admin href="#" icon="newspaper">English News</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="book-open">Bangla News</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="camera">UNB Photos</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="image">AP Photo Manager</x-nav-item-admin>
    </nav>

    {{-- Field apps (separate surfaces — open in new tabs) --}}
    <nav>
        <div class="text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Field apps</div>
        <x-nav-item-admin href="#" icon="smartphone" :external="true">MoJo desk</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="camera" :external="true">Photo desk</x-nav-item-admin>
    </nav>

    {{-- Distribution --}}
    <nav>
        <div class="text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Distribution</div>
        <x-nav-item-admin href="#" icon="activity">Distribution log</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="monitor" badge="3" badge-variant="green">Client FTP</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="tag">Packages</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="send">English Service</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="rss">Bangla Service</x-nav-item-admin>
    </nav>

    {{-- Settings --}}
    <nav>
        <div class="text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Settings</div>
        <x-nav-item-admin href="#" icon="shield-check">Roles &amp; access</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="sparkles">AI settings</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="settings">Preferences</x-nav-item-admin>
    </nav>
</aside>
