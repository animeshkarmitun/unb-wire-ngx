<aside class="relative sticky top-1 max-h-[calc(100vh-4px)] overflow-y-auto self-start flex flex-col gap-7 bg-gradient-to-b from-navy-800 to-navy-900 px-4 pt-6 pb-7">
    {{-- Collapse toggle (desktop only) --}}
    <button type="button" @click="toggleNav()" aria-label="Toggle sidebar" :title="navCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" class="side-toggle absolute right-2 top-2 z-10 hidden lg:inline-flex items-center justify-center w-7 h-7 rounded-[8px] text-navy-text hover:bg-white/[0.09] hover:text-white transition-colors">
        <x-dynamic-component component="lucide-chevrons-left" class="w-4 h-4 side-toggle-icon" stroke-width="2" />
    </button>

    {{-- Brand --}}
    <div class="flex items-center gap-[11px] px-2.5 pt-0.5 pb-1.5">
        <div class="side-brand-icon w-9 h-9 rounded-[10px] bg-crimson text-white font-serif font-bold text-sm flex items-center justify-center tracking-[0.02em] shadow-[0_3px_10px_rgba(229,72,77,0.4)] shrink-0">U</div>
        <div class="side-brand-text">
            <div class="text-white font-serif text-[16.5px] font-semibold leading-[1.2]">UNB Wire</div>
            <div class="text-navy-label text-[11px] tracking-[0.08em] uppercase">Newsroom</div>
        </div>
    </div>

    {{-- Overview --}}
    <nav>
        <div class="side-section-title text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Overview</div>
        <x-nav-item-admin href="{{ route('dashboard') }}" icon="layout-dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.news', ['language' => 'en', 'status' => 'in_review']) }}" icon="pen-line" :badge="\App\Models\Story::where('status', 'in_review')->count() ?: 4" badge-variant="red">Content pipeline</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.clients') }}" icon="users" :active="request()->routeIs('admin.clients')">Clients</x-nav-item-admin>
    </nav>

    {{-- Newsroom --}}
    <nav>
        <div class="side-section-title text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Newsroom</div>
        <x-nav-item-admin href="{{ route('admin.news', 'en') }}" icon="newspaper" :active="request()->routeIs('admin.news') && request()->route('language')==='en'">English News</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.news', 'bn') }}" icon="book-open" :active="request()->routeIs('admin.news') && request()->route('language')==='bn'">Bangla News</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.photos') }}" icon="camera" :active="request()->routeIs('admin.photos')">UNB Photos</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.ap-photos') }}" icon="image" :active="request()->routeIs('admin.ap-photos')">AP Photo Manager</x-nav-item-admin>
    </nav>

    {{-- Field apps (separate surfaces — open in new tabs) --}}
    <nav>
        <div class="side-section-title text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Field apps</div>
        <x-nav-item-admin href="#" icon="smartphone" :external="true">MoJo desk</x-nav-item-admin>
        <x-nav-item-admin href="#" icon="camera" :external="true">Photo desk</x-nav-item-admin>
    </nav>

    {{-- Distribution --}}
    <nav>
        <div class="side-section-title text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Distribution</div>
        <x-nav-item-admin href="{{ route('admin.distribution') }}" icon="activity" :active="request()->routeIs('admin.distribution')">Distribution log</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.clients') }}" icon="monitor" badge="3" badge-variant="green">Client FTP</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.packages') }}" icon="tag" :active="request()->routeIs('admin.packages')">Packages</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.service','en') }}" icon="send" :active="request()->routeIs('admin.service') && request()->route('service')==='en'">English Service</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.service','bn') }}" icon="rss" :active="request()->routeIs('admin.service') && request()->route('service')==='bn'">Bangla Service</x-nav-item-admin>
    </nav>

    {{-- Settings --}}
    <nav>
        <div class="side-section-title text-[10.5px] tracking-[0.14em] uppercase text-navy-label font-semibold mb-2.5 pl-2.5">Settings</div>
        <x-nav-item-admin href="{{ route('admin.roles') }}" icon="shield-check" :active="request()->routeIs('admin.roles')">Roles &amp; access</x-nav-item-admin>
        <x-nav-item-admin href="{{ route('admin.ai-settings') }}" icon="sparkles" :active="request()->routeIs('admin.ai-settings')">AI settings</x-nav-item-admin>
        @if(auth()->user() && app(App\Services\RbacService::class)->can(auth()->user(), 'audit', 'view'))
            <x-nav-item-admin href="{{ route('admin.audit') }}" icon="scroll-text" :active="request()->routeIs('admin.audit')">Audit log</x-nav-item-admin>
        @endif
        <x-nav-item-admin href="{{ route('admin.preferences') }}" icon="settings" :active="request()->routeIs('admin.preferences')">Preferences</x-nav-item-admin>
    </nav>
</aside>
