<div class="wire-service-wrap {{ $service === 'bn' ? 'font-bengali' : '' }}"
     x-data="{
        tickerItems: {{ json_encode($tickerStories->values()->all()) }},
        tickerIdx: 0,
        tickerText: '{{ addslashes($tickerStories->first() ?? 'UNB Wire News Service') }}',
        init() {
            if (this.tickerItems.length > 1) {
                setInterval(() => {
                    this.tickerIdx = (this.tickerIdx + 1) % this.tickerItems.length;
                    this.tickerText = this.tickerItems[this.tickerIdx];
                }, 5000);
            }
            this.updateClock();
            setInterval(() => this.updateClock(), 15000);
        },
        updateClock() {
            const el = document.getElementById('wsClockTime');
            if (!el) return;
            const now = new Date();
            const d = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).format(now);
            const t = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Dhaka', hour: 'numeric', minute: '2-digit', hour12: true }).format(now);
            el.textContent = d + ' · ' + t + ' (Dhaka)';
        }
     }">

    <!-- Top Brand Gradient Strip -->
    <div class="brand-strip"></div>

    <!-- Wire Masthead -->
    <header class="masthead">
        <div class="mast-inner">
            <a class="mast-brand" href="{{ route('admin.service', $service) }}">
                <div class="brand-mark">U</div>
                <div>
                    <div class="mast-name">UNB</div>
                    <div class="mast-tag">
                        United News of Bangladesh &middot; {{ $service === 'bn' ? 'বাংলা সার্ভিস' : 'English Service' }}
                    </div>
                </div>
            </a>

            <div class="mast-right">
                <div class="mast-clock">
                    <span class="live-dot"></span>
                    <span id="wsClockTime">Dhaka</span>
                </div>

                <div class="mast-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="{{ $service === 'bn' ? 'সংবাদ খুঁজুন…' : 'Search news…' }}"
                           class="mast-search-input">
                </div>

                @if($service === 'en')
                    <a class="admin-chip" href="{{ route('admin.service', 'bn') }}" title="Switch to Bangla Wire Service">
                        <span>বাং</span> বাংলা সার্ভিস
                    </a>
                @else
                    <a class="admin-chip" href="{{ route('admin.service', 'en') }}" title="Switch to English Wire Service">
                        <span>EN</span> English Service
                    </a>
                @endif

                <button type="button" class="admin-chip" wire:click="$toggle('showConfigModal')" title="Wire Service Settings">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Settings
                </button>

                <a class="admin-chip" href="{{ route('admin.add-news') }}" style="background:var(--crimson);color:#fff;border-color:var(--crimson)">
                    + Add News
                </a>
            </div>
        </div>
    </header>

    <!-- Sticky Category Navigation Bar -->
    <nav class="catnav" x-data="{ activeCat: @entangle('activeCategory') }">
        <div class="catnav-inner" id="wsCatNav">
            <button type="button"
                    wire:key="cat-all"
                    @click="activeCat = 'all'"
                    wire:click="setCategory('all')"
                    :class="{ 'active': activeCat === 'all' }"
                    class="{{ $activeCategory === 'all' ? 'active' : '' }}">
                {{ $service === 'bn' ? 'সকল বিভাগ' : 'All Wire' }}
            </button>

            @foreach($categories as $cat)
                @php $catKey = $cat->slug ?: (string)$cat->id; @endphp
                <button type="button"
                        wire:key="cat-{{ $cat->id }}"
                        @click="activeCat = '{{ $catKey }}'"
                        wire:click="setCategory('{{ $catKey }}')"
                        :class="{ 'active': activeCat === '{{ $catKey }}' }"
                        class="{{ $activeCategory == $catKey ? 'active' : '' }}">
                    {{ $service === 'bn' ? ($cat->name_bn ?: $cat->name_en) : $cat->name_en }}
                    @if($cat->stories_count > 0)
                        <span class="text-[10px] opacity-75 ml-0.5">({{ $cat->stories_count }})</span>
                    @endif
                </button>
            @endforeach
        </div>
    </nav>

    <!-- Breaking / Wire Ticker -->
    <div class="ticker-wrap">
        <div class="ticker">
            <span class="ticker-label">
                <span class="pulse"></span>
                {{ $service === 'bn' ? 'সংবাদ আপডেট' : 'News Updates' }}
            </span>
            <span class="ticker-text" x-text="tickerText" id="wsTickerText">
                {{ $tickerStories->first() ?? 'UNB Wire News Service' }}
            </span>
        </div>
    </div>

    <!-- Main Page Content -->
    <div class="page">
        <div class="home-grid">

            <!-- Left Main Column: Hero + Category Sections -->
            <div>
                @if($heroStory)
                    @php
                        $heroMedia = $heroStory->media->firstWhere('pivot.role', 'featured')
                            ?? $heroStory->media->firstWhere('kind', 'image')
                            ?? $heroStory->media->first();
                        $heroGradient = 'g' . (($heroStory->id % 8) + 1);
                    @endphp

                    <a class="hero" href="{{ route('admin.story', $heroStory->public_id) }}" title="Read dispatch">
                        <div class="hero-img {{ $heroMedia ? '' : $heroGradient }}">
                            @if($heroMedia && !empty($heroMedia->file_path))
                                <img src="{{ asset('storage/' . $heroMedia->file_path) }}" alt="{{ $heroStory->headline }}">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="m21 15-5-5L5 21"/>
                                </svg>
                            @endif
                        </div>

                        <div class="hero-overlay">
                            <span class="hero-cat">
                                @if($heroStory->is_breaking)
                                    {{ $service === 'bn' ? 'জরুরি সংবাদ' : 'BREAKING' }}
                                @else
                                    {{ $service === 'bn' ? (optional($heroStory->category)->name_bn ?: optional($heroStory->category)->name_en ?? 'সাধারণ') : (optional($heroStory->category)->name_en ?? 'Wire') }}
                                @endif
                            </span>
                            <div class="hero-head">{{ $heroStory->headline }}</div>
                            <div class="hero-meta">
                                <span>{{ $heroStory->published_at ? \App\Support\DisplayPrefs::format($heroStory->published_at) : 'Just now' }}</span>
                                <span>&middot;</span>
                                <span>UNB News</span>
                                @if($heroStory->word_count)
                                    <span>&middot;</span>
                                    <span>{{ $heroStory->word_count }} {{ $service === 'bn' ? 'শব্দ' : 'words' }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @else
                    <div class="bg-panel border border-border rounded-2xl p-12 text-center text-muted mb-8">
                        <div class="font-serif text-xl font-semibold mb-2">No wire dispatches match the current filter</div>
                        <p class="text-sm">Try choosing another category or clearing your search term.</p>
                        <button type="button" wire:click="setCategory('all'); $set('search', '')" class="admin-chip mt-4">
                            View All Dispatches
                        </button>
                    </div>
                @endif

                <!-- Dynamic Category Sections -->
                @foreach($sections as $sec)
                    <div class="sec">
                        <div class="sec-head">
                            <div class="sec-title">{{ $sec['title'] }}</div>
                            <a class="sec-more" href="{{ route('admin.news', ['language' => $service, 'category' => optional($sec['category'])->id]) }}">
                                {{ $service === 'bn' ? 'সকল দেখুন →' : 'View all →' }}
                            </a>
                        </div>

                        <div class="card-row">
                            @foreach($sec['stories'] as $story)
                                @php
                                    $cardMedia = $story->media->firstWhere('pivot.role', 'featured')
                                        ?? $story->media->firstWhere('kind', 'image')
                                        ?? $story->media->first();
                                    $cardGrad = 'g' . (($story->id % 8) + 1);
                                @endphp

                                <a class="story-card" href="{{ route('admin.story', $story->public_id) }}" title="{{ $story->headline }}">
                                    <div class="sc-img {{ $cardMedia ? '' : $cardGrad }}">
                                        @if($cardMedia && !empty($cardMedia->file_path))
                                            <img src="{{ asset('storage/' . $cardMedia->file_path) }}" alt="{{ $story->headline }}">
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <path d="m21 15-5-5L5 21"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="sc-body">
                                        <div class="sc-cat">
                                            {{ $service === 'bn' ? (optional($story->category)->name_bn ?: optional($story->category)->name_en ?? 'সাধারণ') : (optional($story->category)->name_en ?? 'News') }}
                                        </div>
                                        <div class="sc-head">{{ $story->headline }}</div>
                                        <div class="sc-time">
                                            {{ $story->published_at ? \App\Support\DisplayPrefs::format($story->published_at) : '' }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Right Aside Rail: Latest & Popular Dispatches -->
            <aside>
                <div class="rail">
                    <div class="rail-tabs">
                        <button type="button"
                                class="rail-tab {{ $activeRailTab === 'latest' ? 'active' : '' }}"
                                wire:click="setRailTab('latest')">
                            {{ $service === 'bn' ? 'সাম্প্রতিক' : 'Latest' }}
                        </button>
                        <button type="button"
                                class="rail-tab {{ $activeRailTab === 'popular' ? 'active' : '' }}"
                                wire:click="setRailTab('popular')">
                            {{ $service === 'bn' ? 'জনপ্রিয়' : 'Popular' }}
                        </button>
                    </div>

                    <div class="rail-list" id="railItemsList">
                        @php
                            $railStories = ($activeRailTab === 'latest') ? $latestRail : $popularRail;
                        @endphp

                        @forelse($railStories as $rStory)
                            @php
                                $rMedia = $rStory->media->firstWhere('pivot.role', 'featured')
                                    ?? $rStory->media->firstWhere('kind', 'image')
                                    ?? $rStory->media->first();
                                $rGrad = 'g' . (($rStory->id % 8) + 1);
                            @endphp

                            <a class="rail-item" href="{{ route('admin.story', $rStory->public_id) }}">
                                <div class="rail-thumb {{ $rMedia ? '' : $rGrad }}">
                                    @if($rMedia && !empty($rMedia->file_path))
                                        <img src="{{ asset('storage/' . $rMedia->file_path) }}" alt="{{ $rStory->headline }}">
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="rail-head">{{ $rStory->headline }}</div>
                                    <div class="rail-time">
                                        @if($activeRailTab === 'popular' && $rStory->word_count)
                                            {{ $rStory->word_count }} {{ $service === 'bn' ? 'শব্দ' : 'words' }}
                                        @else
                                            {{ $rStory->published_at ? \App\Support\DisplayPrefs::format($rStory->published_at) : 'Recent' }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-6 text-center text-xs text-muted">
                                No dispatches currently available.
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>

        </div>
    </div>

    <!-- Wire Service Footer -->
    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="brand-mark">U</div>
                <div>
                    <div class="footer-name">UNB &mdash; United News of Bangladesh</div>
                    <div class="footer-tag">{{ $service === 'bn' ? 'বাংলা সার্ভিস' : 'English Service' }}</div>
                </div>
            </div>
            <div class="footer-note">Subscriber feed &middot; Updated continuously &middot; &copy; {{ date('Y') }} UNB</div>
        </div>
    </footer>

    <!-- Wire Service Settings Modal -->
    @if($showConfigModal)
        <div class="fixed inset-0 bg-navy-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             @keydown.escape.window="$set('showConfigModal', false)">
            <div class="bg-panel border border-border rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-border flex items-center justify-between bg-paper">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-navy-800 text-white flex items-center justify-center font-bold text-sm">
                            ⚙
                        </div>
                        <div>
                            <h3 class="font-serif text-lg font-bold">
                                {{ $service === 'bn' ? 'বাংলা সার্ভিস কনফিগারেশন' : 'English Wire Configuration' }}
                            </h3>
                            <p class="text-xs text-muted">Manage distribution metadata and service defaults</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showConfigModal', false)" class="text-muted hover:text-ink text-xl font-semibold">
                        &times;
                    </button>
                </div>

                <form wire:submit.prevent="saveConfig" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Wire Service Name</label>
                        <input type="text"
                               wire:model="wireName"
                               class="w-full border border-border rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-navy-800 bg-white">
                        @error('wireName') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Description / Tagline</label>
                        <textarea wire:model="description"
                                  rows="3"
                                  class="w-full border border-border rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-navy-800 bg-white"></textarea>
                        @error('description') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-1">
                        <label class="flex items-center gap-2.5 text-sm cursor-pointer select-none">
                            <input type="checkbox" wire:model="enabled" class="rounded border-border text-navy-800 focus:ring-0 w-4 h-4">
                            <span class="font-medium">Enable real-time wire distribution for this service</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" wire:click="$set('showConfigModal', false)" class="admin-chip">
                            Cancel
                        </button>
                        <button type="submit" class="admin-chip" style="background:var(--crimson);color:#fff;border-color:var(--crimson)">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
