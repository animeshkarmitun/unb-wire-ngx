      <!-- ================================================================= -->
      <!-- ===== STEP 1: WRITE ===== -->
      <!-- ================================================================= -->
      <section class="step-panel {{ $step === 1 ? 'active' : '' }}" data-step="1">
        <div class="card">
          <div class="card-title card-title-flex">
            <span>Story content</span>
            <button type="button" class="btn btn-outline btn-sm" id="openImport">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
              Import from doc
            </button>
          </div>

          <div class="draft-banner" id="draftBanner" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="draftBannerText"></span>
            <button type="button" class="db-btn primary" id="draftRestore">Restore draft</button>
            <button type="button" class="db-btn" id="draftDiscard">Discard</button>
          </div>

          <!-- Start with AI -->
          <div class="ai-start" id="aiStart" x-data="{ open: true }">
            <div class="ai-start-head">
              <span class="ai-label">✦ Start with AI</span>
              <span class="ai-note">Paste raw notes, a press release or field copy — AI drafts the headline, brief, body, category &amp; tags. <strong>Nothing is applied without your click.</strong></span>
              <button type="button" class="ai-start-toggle" id="aiStartToggle" @click="open = !open" x-text="open ? 'Hide' : 'Show'">Hide</button>
            </div>
            <div class="ai-start-body" id="aiStartBody" x-show="open" :hidden="!open">
              <textarea class="ai-raw" id="aiRaw" wire:model.live.debounce.300ms="aiRawText" placeholder="Paste notes, press release, bullet points, rough quotes, or field copy here…"></textarea>
              <div class="ai-start-foot">
                <span class="ai-note">AI drafts headline, brief, polished wire body, category and tags for your review</span>
                <button type="button" class="ai-gen-btn" id="aiGenerateBtn" wire:click="callAi('generate')">
                  <span wire:loading.remove wire:target="callAi('generate')">✦ Generate story draft</span>
                  <span wire:loading wire:target="callAi('generate')">Thinking…</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Language toggle -->
          <div class="field">
            <label class="field-label">Language &amp; Script</label>
            <div class="flex gap-2">
              <button type="button" wire:click="setLanguage('en')" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors {{ $language === 'en' ? 'bg-navy-800 text-white border-navy-800' : 'bg-white text-ink border-[#e3e1da] hover:border-navy-800' }}">English (en)</button>
              <button type="button" wire:click="setLanguage('bn')" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors {{ $language === 'bn' ? 'bg-navy-800 text-white border-navy-800' : 'bg-white text-ink border-[#e3e1da] hover:border-navy-800' }}">বাংলা (bn)</button>
            </div>
          </div>

          <!-- Headline -->
          <div class="field {{ !empty($aiTouched['headline']) ? 'ai-touched' : '' }}">
            <label class="field-label" for="headlineInput">Headline <span class="req">*</span></label>
            <input class="text-input headline-input @error('headline') input-error @enderror" type="text" id="headlineInput" wire:model.live.debounce.300ms="headline" placeholder="{{ $language === 'bn' ? 'সংবাদের শিরোনাম লিখুন…' : 'Lead headline — concise, active voice…' }}" autocomplete="off">
            <div class="error-text @error('headline') show @enderror" id="headlineError">@error('headline') {{ $message }} @else Please enter a headline to continue. @enderror</div>
            <div class="field-hint">Recommended: 60–90 characters · Active voice, present tense for breaking events</div>
          </div>

          <!-- Subhead -->
          <div class="field">
            <label class="field-label" for="subheadInput">Sub head</label>
            <input class="text-input" type="text" id="subheadInput" wire:model.live.debounce.300ms="subHead" placeholder="Optional secondary line or deck for client wire feeds">
          </div>

          <!-- Dateline city & date -->
          <div class="two-col">
            <div class="field">
              <label class="field-label" for="datelineCity">Dateline city</label>
              <input class="text-input" type="text" id="datelineCity" wire:model.live="datelineCity" placeholder="DHAKA">
            </div>
            <div class="field">
              <label class="field-label" for="datelineAt">Dateline date/time</label>
              <input class="text-input" type="datetime-local" id="datelineAt" wire:model.live="datelineAt">
            </div>
          </div>

          <!-- Brief / Intro -->
          <div class="field {{ !empty($aiTouched['brief']) ? 'ai-touched' : '' }}">
            <label class="field-label" for="briefInput">Brief / Intro <span class="req">*</span></label>
            <textarea class="text-area @error('brief') input-error @enderror" id="briefInput" wire:model.live.debounce.300ms="brief" maxlength="280" placeholder="One or two sentences summarizing the story for tickers, SMS alerts and client wire feeds…"></textarea>
            <div class="error-text @error('brief') show @enderror">@error('brief') {{ $message }} @enderror</div>
            <div class="field-hint" style="display:flex; justify-content:space-between">
              <span>Must stand on its own without the body text</span>
              <span id="briefCounter"><strong id="briefCount">{{ strlen($brief) }}</strong> / 280</span>
            </div>
          </div>

          <!-- Story body with Quill Wire toolbar -->
          <div class="field {{ !empty($aiTouched['body']) ? 'ai-touched' : '' }}">
            <label class="field-label">Story body <span class="req">*</span></label>

            <!-- AI Assist Bar -->
            <div class="ai-row" id="aiAssistRow">
              <span class="ai-label">✦ AI assist</span>
              <button type="button" class="ai-btn llm" id="aiPreeditBtn" wire:click="callAi('preedit')">✦ Pre-edit draft</button>
              <button type="button" class="ai-btn" id="aiSuggestBtn">Headline &amp; brief</button>
              <button type="button" class="ai-btn" id="aiNamesBtn">Name spellings</button>
              <button type="button" class="ai-btn llm" id="aiTranslateBtn" wire:click="callAi('translate')">Translate to {{ $language === 'bn' ? 'English' : 'Bangla' }}</button>
              <span class="ai-note">Suggestions open in a drawer — nothing is applied without your click</span>
            </div>

            <div class="editor-wrap" id="editorWrap" wire:ignore>
              <!-- Wire Toolbar -->
              <div id="editorToolbar">
                <span class="ql-formats">
                  <select class="ql-header">
                    <option selected></option>
                    <option value="2">Subhead (H2)</option>
                    <option value="3">Section (H3)</option>
                  </select>
                </span>
                <span class="ql-formats">
                  <button class="ql-bold"></button>
                  <button class="ql-italic"></button>
                  <button class="ql-underline"></button>
                </span>
                <span class="ql-formats">
                  <button class="ql-list" value="ordered"></button>
                  <button class="ql-list" value="bullet"></button>
                  <button class="ql-link"></button>
                  <button class="ql-image"></button>
                </span>
                <span class="ql-formats ql-wire-formats">
                  <button type="button" class="qlc ql-dateline" title="Insert dateline at cursor (DHAKA, <date> — )">Dateline</button>
                  <button type="button" class="qlc ql-pullquote" title="Insert pull-quote block at cursor">Pull quote</button>
                  <button type="button" class="qlc ql-related" title="Embed link to related UNB story">Related story</button>
                  <button type="button" class="qlc ql-table" title="Insert structured data table">Table</button>
                  <button type="button" class="qlc ql-signoff" title="Insert UNB Wire signoff at the end">Signoff</button>
                  <button type="button" class="qlc ql-cleanup" title="Fix double spaces, quote marks and stray punctuation">Cleanup</button>
                  <button type="button" class="qlc ql-findreplace" title="Find &amp; replace (Ctrl+F)">Find</button>
                  <button type="button" class="qlc ql-history" title="View past snapshots taken while writing">History</button>
                </span>
                <span class="ql-formats" style="margin-left:auto">
                  <button type="button" class="qlc ql-fullscreen" id="fsBtn" title="Fullscreen (Esc to exit)">⛶</button>
                </span>
              </div>

              <!-- Find & Replace Bar -->
              <div class="fr-bar" id="frBar" hidden>
                <input type="text" id="frFind" placeholder="Find…">
                <input type="text" id="frReplace" placeholder="Replace with…">
                <span class="fr-count" id="frCount">0 found</span>
                <button type="button" id="frPrev">▲ Prev</button>
                <button type="button" id="frNext">▼ Next</button>
                <button type="button" id="frReplaceOne">Replace</button>
                <button type="button" id="frReplaceAll">Replace all</button>
                <button type="button" class="fr-x" id="frClose" title="Close">✕</button>
              </div>

              <!-- Quill container -->
              <div id="editorBody">{!! \App\Services\HtmlSanitizer::clean($bodyHtml) !!}</div>

              <!-- Editor Footer -->
              <div class="editor-foot">
                <span class="ef-stat" id="efWords">{{ str_word_count(strip_tags($bodyHtml)) }} words</span>
                <span class="ef-sep">·</span>
                <span id="efRead">~{{ max(1, round(str_word_count(strip_tags($bodyHtml)) / 200)) }} min read</span>
                <span class="ef-sep">·</span>
                <span class="ef-saved" id="efSaved"><span class="ef-saved-dot"></span><span id="efSavedTxt">Autosave on</span></span>
                <span class="ef-right">Tips: use Dateline, Pull quote, and Table buttons above</span>
              </div>
            </div>
            @error('bodyHtml') <div class="error-text show">{{ $message }}</div> @enderror
          </div>

          <div class="publish-note" style="margin-bottom:0">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Breaking story? Just fill the headline &amp; body and hit <strong>Publish now</strong> — media, tags and packages can be added later from English News → Edit.
          </div>
        </div>
      </section>

      {{-- Server Version History (visible when story is saved) --}}
      @if($storyId)
        <div class="card" style="margin-top:8px">
          <div class="card-title" style="cursor:pointer; display:flex; align-items:center; justify-content:space-between" wire:click="$toggle('showServerHistory')">
            <span>Server version history</span>
            <span style="font-size:11px; color:var(--muted)">{{ $showServerHistory ? '▲ Collapse' : '▼ Expand' }}</span>
          </div>
          @if($showServerHistory)
            <div style="max-height:200px; overflow-y:auto; margin-top:8px">
              @php $versions = $this->listVersions(); @endphp
              @forelse($versions as $ver)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 8px; border-radius:6px; font-size:11px; {{ $ver['version'] === $version ? 'background:var(--navy-50); border:1px solid var(--navy-200)' : '' }}">
                  <div style="display:flex; align-items:center; gap:8px">
                    <span style="font-family:monospace; font-weight:bold">v{{ $ver['version'] }}</span>
                    <span style="color:var(--muted)">{{ $ver['creator'] }}</span>
                  </div>
                  <div style="display:flex; align-items:center; gap:8px">
                    <span style="color:var(--muted); font-size:10px">{{ $ver['created_at'] }}</span>
                    @if($ver['version'] !== $version)
                      <button type="button" wire:click="restoreVersion({{ $ver['version'] }})" style="font-size:9px; padding:2px 6px; border-radius:4px; background:var(--amber-100); color:var(--amber-700); font-weight:bold; text-transform:uppercase">Restore</button>
                    @endif
                  </div>
                </div>
              @empty
                <div style="font-size:11px; color:var(--muted); font-style:italic; padding:8px 0; text-align:center">No server versions yet.</div>
              @endforelse
            </div>
          @endif
        </div>
      @endif
