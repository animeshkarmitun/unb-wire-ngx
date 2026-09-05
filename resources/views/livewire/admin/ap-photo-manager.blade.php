<div>
  {{-- Breadcrumb --}}
  <div class="breadcrumb mb-2 text-xs text-muted">
    <a href="{{ route('dashboard') }}" class="text-crimson hover:underline">Home</a> &nbsp;/&nbsp; AP Photo Manager
  </div>

  {{-- Topbar --}}
  <div class="topbar flex items-center justify-between mb-1.5">
    <h1 class="font-serif text-[30px] font-semibold tracking-[-0.01em] text-ink">AP Photo Manager</h1>
    <div class="topbar-actions flex gap-3">
      <button type="button" wire:click="toggleSyncLog" class="btn btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        Sync log
      </button>
      <button type="button" wire:click="syncNow" class="btn btn-primary" id="syncBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="23 4 23 10 17 10"/>
          <polyline points="1 20 1 14 7 14"/>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
        </svg>
        Sync now
      </button>
    </div>
  </div>

  {{-- Sync Note --}}
  <div class="sync-note">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
    AP wire auto-syncs every 15 minutes · Last synced {{ $lastSyncTime }} · {{ $todaySyncCount }} new photos today
  </div>

  {{-- Filter Card --}}
  <div class="card bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] mb-3">
    <div class="filters flex gap-2.5 flex-wrap items-center p-4">
      <input
        type="text"
        wire:model.live.debounce.300ms="search"
        id="apSearch"
        placeholder="Search captions, keywords…"
        class="search-input"
      />

      <select wire:model.live="category" id="apCategory" class="filter-select">
        @foreach($categories as $catKey => $catLabel)
          <option value="{{ $catKey }}">{{ $catLabel }}</option>
        @endforeach
      </select>

      <select wire:model.live="subCategory" class="filter-select">
        @foreach($subCategories as $subKey => $subLabel)
          <option value="{{ $subKey }}">{{ $subLabel }}</option>
        @endforeach
      </select>

      <input
        type="text"
        wire:model.live.debounce.300ms="tag"
        placeholder="Tag"
        class="search-input tag-filter"
      />

      <button type="button" class="btn btn-navy">Search</button>
      <button type="button" wire:click="resetFilters" id="resetFilters" class="btn btn-ghost">Reset</button>
    </div>

    {{-- Category Chips --}}
    <div class="cat-chips" id="catChips">
      @foreach($categories as $catKey => $catLabel)
        <button
          type="button"
          wire:click="setCategory('{{ $catKey }}')"
          class="cat-chip {{ $category === $catKey ? 'active' : '' }}"
          data-cat="{{ $catKey }}"
        >
          {{ $catKey === 'all' ? 'All' : $catLabel }}
        </button>
      @endforeach
    </div>
  </div>

  {{-- Result Count --}}
  <div class="result-count" id="resultCount">
    Showing <strong>{{ $visibleCount }}</strong> of <strong>{{ number_format($totalApCount) }}</strong> AP photos
  </div>

  {{-- Photo Grid --}}
  <div class="photo-grid" id="apGrid">
    @forelse($assets as $a)
      @php
        $grad = $a->derivatives['grad'] ?? ('g' . (($a->id % 8) + 1));
        $cat = $a->event_label ?: ($a->derivatives['ap_category'] ?? 'world');
        $date = $a->captured_at ? $a->captured_at->format('M j, Y') : $a->created_at->format('M j, Y');
        $catClass = in_array(strtolower($cat), ['world', 'sports']) ? strtolower($cat) : '';
      @endphp
      <div class="ap-card" wire:click="openLightbox({{ $a->id }})" data-id="{{ $a->id }}">
        <div class="ap-thumb {{ $grad }}">
          <span class="ap-badge">AP</span>
          <button type="button" class="ap-expand" wire:click.stop="openLightbox({{ $a->id }})" title="Expand">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/>
            </svg>
          </button>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
          </svg>
        </div>
        <div class="ap-body">
          <div class="ap-cap" title="{{ $a->caption ?: $a->title }}">{{ $a->caption ?: $a->title }}</div>
          <div class="ap-meta">
            <span class="ap-cat {{ $catClass }}">{{ ucfirst($cat) }}</span>
            <span>{{ $date }}</span>
          </div>
        </div>
        <div class="ap-foot">
          <button
            type="button"
            wire:click.stop="attachToStory({{ $a->id }})"
            class="attach-btn {{ !empty($attachedIds[$a->id]) ? 'done' : '' }}"
          >
            @if(!empty($attachedIds[$a->id]))
              ✓ Attached
            @else
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
              </svg>
              Attach to story
            @endif
          </button>
          <button type="button" wire:click.stop="downloadOriginal({{ $a->id }})" class="dl-btn" title="Download">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 3v13"/><path d="M6 11l6 6 6-6"/><path d="M5 21h14"/>
            </svg>
          </button>
        </div>
      </div>
    @empty
      <div class="col-span-full p-12 text-center text-sm text-muted border-2 border-dashed border-border rounded-xl">
        No AP wire photos match the current filters. Try adjusting your search query or category.
      </div>
    @endforelse
  </div>

  {{-- Load More Wrap --}}
  @if($remainingCount > 0)
    <div class="load-more-wrap">
      <button type="button" wire:click="loadMore" class="btn btn-outline" id="loadMore">
        Load more photos ({{ $remainingCount }})
      </button>
    </div>
  @endif

  {{-- Lightbox Modal --}}
  @if($selected)
    @php
      $selGrad = $selected->derivatives['grad'] ?? ('g' . (($selected->id % 8) + 1));
      $selCat = $selected->event_label ?: ($selected->derivatives['ap_category'] ?? 'world');
      $selDate = $selected->captured_at ? $selected->captured_at->format('M j, Y') : $selected->created_at->format('M j, Y');
      $selDim = ($selected->width && $selected->height) ? ($selected->width . ' × ' . $selected->height) : ($selected->derivatives['dim'] ?? '3000 × 2000');
    @endphp
    <div class="lb-overlay open" id="lbOverlay" wire:click.self="closeLightbox">
      <div class="lb-modal">
        <div class="lb-img {{ $selGrad }}" id="lbImg">
          <button type="button" class="lb-close" id="lbClose" wire:click="closeLightbox" title="Close">✕</button>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
          </svg>
        </div>
        <div class="lb-info">
          <div class="lb-cap" id="lbCap">{{ $selected->caption ?: $selected->title }}</div>
          <div class="lb-row"><span class="k">Credit</span><span class="v">{{ $selected->credit_line ?: 'AP Photo' }}</span></div>
          <div class="lb-row"><span class="k">Category</span><span class="v" id="lbCat">{{ ucfirst($selCat) }}</span></div>
          <div class="lb-row"><span class="k">Wire date</span><span class="v" id="lbDate">{{ $selDate }}</span></div>
          <div class="lb-row"><span class="k">Dimensions</span><span class="v" id="lbDim">{{ $selDim }} px</span></div>
          <div class="lb-row"><span class="k">Downloads</span><span class="v" id="lbDl">{{ $selected->download_count ?? 0 }} by clients</span></div>
          <div class="lb-actions">
            <button
              type="button"
              wire:click="attachToStory({{ $selected->id }})"
              class="btn btn-primary"
              id="lbAttach"
              style="justify-content:center"
            >
              {{ !empty($attachedIds[$selected->id]) ? '✓ Attached to draft' : 'Attach to story' }}
            </button>
            <button
              type="button"
              wire:click="downloadOriginal({{ $selected->id }})"
              class="btn btn-outline"
              style="justify-content:center"
            >
              Download original
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Sync Log Modal --}}
  @if($showSyncLog)
    <div class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-navy-900/60 backdrop-blur-sm" wire:click.self="toggleSyncLog">
      <div class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl border border-border animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-paper">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-crimson" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            <h3 class="font-serif text-lg font-bold text-ink">AP Wire Sync Log</h3>
          </div>
          <button type="button" wire:click="toggleSyncLog" class="text-muted-2 hover:text-ink text-sm font-semibold p-1">✕</button>
        </div>
        <div class="p-6">
          <div class="flex items-center justify-between mb-4 text-xs text-muted">
            <span>Automatic wire poll every 15 minutes</span>
            <span class="flex items-center gap-1.5 text-green font-semibold">
              <span class="w-2 h-2 rounded-full bg-green"></span> Wire feed healthy
            </span>
          </div>
          <div class="border border-border rounded-xl overflow-hidden">
            <table class="w-full text-xs text-left">
              <thead class="bg-paper text-muted border-b border-border">
                <tr>
                  <th class="px-4 py-2.5 font-semibold">Sync Time</th>
                  <th class="px-4 py-2.5 font-semibold">Status</th>
                  <th class="px-4 py-2.5 font-semibold">Ingested</th>
                  <th class="px-4 py-2.5 font-semibold">Channel</th>
                  <th class="px-4 py-2.5 font-semibold">Latency</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                @foreach($syncLogs as $log)
                  <tr class="hover:bg-paper/50">
                    <td class="px-4 py-3 font-semibold text-ink">{{ $log['time'] }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-bg text-green">
                        {{ $log['status'] }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-ink font-medium">{{ $log['photos'] }}</td>
                    <td class="px-4 py-3 text-muted">{{ $log['channel'] }}</td>
                    <td class="px-4 py-3 text-muted font-mono">{{ $log['duration'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 bg-paper border-t border-border">
          <button type="button" wire:click="toggleSyncLog" class="btn btn-outline text-xs py-2 px-4">Close</button>
        </div>
      </div>
    </div>
  @endif

  <x-toast />
</div>

