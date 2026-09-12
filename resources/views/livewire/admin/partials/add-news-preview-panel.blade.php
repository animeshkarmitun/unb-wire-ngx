    <!-- ===== LIVE PREVIEW COLUMN ===== -->
    <aside class="pv-col">
      <button type="button" class="pv-strip" id="pvStrip" title="Show live preview">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <span>Live preview</span>
      </button>

      <div class="pv-panel">
        <div class="pv-head">
          <span class="pv-dot"></span> Live preview
          <span class="pv-head-sub">client wire view</span>
          <button type="button" class="pv-icon-btn" id="pvExpand" title="Fullscreen preview — with device widths">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
          </button>
        </div>
        <div class="pv-body" id="pvBody" wire:ignore>
          @php
            $pvCat = $categoryId ? (optional(\App\Models\Category::find($categoryId))->name_en ?: 'Category') : 'Category';
            $pvWords = str_word_count(strip_tags($bodyHtml));
            $pvRead = max(1, round($pvWords / 200));
            $pvParas = array_values(array_filter(array_map('trim', explode("\n", strip_tags($bodyHtml))), fn($p)=>$p!==''));
          @endphp
          <div class="pv-card">
            <div class="pv-meta">
              <span class="pv-cat {{ $categoryId ? '' : 'empty' }}">{{ $pvCat }}</span>
              <span class="pv-time">Just now</span>
              @if($access === 'exclusive') <span class="pv-badge exc">★ Exclusive</span> @endif
              @if($isBreaking) <span class="pv-badge" style="background:#e5484d;color:#fff">FLASH</span> @endif
            </div>
            <div class="pv-headline {{ $headline ? '' : 'ph' }}">{{ $headline ?: 'Your headline will appear here as you type…' }}</div>
            @if($subHead) <div class="pv-subhead">{{ $subHead }}</div> @endif
            <div class="pv-brief {{ $brief ? '' : 'ph' }}">{{ $brief ?: 'Intro / brief shown on the wire feed…' }}</div>
            @if(count($tags) > 0)
              <div class="pv-tags">
                @foreach($tags as $t) <span class="pv-tag">#{{ $t }}</span> @endforeach
              </div>
            @endif
            @if(count($attachedMedia) > 0)
              <div class="pv-medianote">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                {{ count($attachedMedia) }} media attached
              </div>
            @endif
            <div class="pv-actions">
              <span class="pv-btn primary">Wire Feed View</span>
              <span class="pv-btn">Copy text</span>
            </div>
          </div>

          <div class="pv-divider"><span>Article reader view</span></div>

          <div class="pv-article">
            <div class="pv-art-head {{ $headline ? '' : 'ph' }}">{{ $headline ?: 'Article Headline…' }}</div>
            @if($subHead)<div class="pv-art-sub">{{ $subHead }}</div>@endif
            <div class="pv-art-byline">{{ $author ?: 'UNB Desk' }} · {{ $pvCat }} · Just now</div>
            <div class="pv-art-body">
              @if(count($pvParas) > 0)
                @foreach(array_slice($pvParas, 0, 3) as $para)
                  <p>{{ $para }}</p>
                @endforeach
              @else
                <p class="ph">Story body paragraphs will flow in here from the editor…</p>
              @endif
            </div>
            <div class="pv-stats"><span>{{ $pvWords }} words</span><span>~{{ $pvRead }} min read</span></div>
          </div>
        </div>
      </div>
      <div class="pv-note">This is exactly what subscribers see on their UNB Wire feed — it updates as you type, attach media, or change access. Nothing is published from here.</div>
    </aside>
  </div><!-- /add-grid -->

  <!-- ============ PREVIEW FOCUS OVERLAY ============ -->
  <div class="pv-overlay" id="pvOverlay" wire:ignore>
    <div class="pv-focus dev-desktop" id="pvFocus">
      <div class="pv-focus-bar">
        <span class="pv-focus-title"><span class="pv-dot"></span> Live preview</span>
        <div class="pv-dev" id="pvDevSeg">
          <button type="button" data-dev="desktop" class="active">Desktop</button>
          <button type="button" data-dev="tablet">Tablet</button>
          <button type="button" data-dev="mobile">Mobile</button>
        </div>
        <button type="button" class="pv-focus-close" id="pvFocusClose" title="Close (Esc)">✕</button>
      </div>
      <div class="pv-body pv-focus-body" id="pvFocusBody"></div>
    </div>
  </div>
