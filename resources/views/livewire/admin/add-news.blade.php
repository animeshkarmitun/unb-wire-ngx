<div id="addNewsApp">
  {{-- TOP SECTION: Breadcrumb & Title --}}
  <div class="add-grid" id="addGrid">
    <div class="add-form-col">
      <div class="breadcrumb">
        <a href="{{ route('dashboard') }}">Home</a> &nbsp;/&nbsp;
        <a href="{{ route('admin.news', ['language' => $language]) }}">{{ $language === 'bn' ? 'Bangla News' : 'English News' }}</a> &nbsp;/&nbsp;
        Add News
      </div>

      <div class="topbar">
        <h1>Add News</h1>
        <div class="topbar-actions">
          <a href="{{ route('admin.news', ['language' => $language]) }}" class="btn btn-outline">Cancel</a>
          <button type="button" class="btn btn-outline pv-top-toggle" id="pvToggleBtn" title="Show / hide live preview">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview
          </button>
          <button type="button" class="btn btn-outline" id="saveDraftTop" wire:click="autosave">
            <span wire:loading.remove wire:target="autosave">Save draft</span>
            <span wire:loading wire:target="autosave">Saving…</span>
          </button>
        </div>
      </div>

      <!-- ===== STEPPER ===== -->
      <div class="stepper" id="stepper">
        <button type="button" class="stp {{ $step === 1 ? 'active' : ($step > 1 ? 'done' : '') }}" wire:click="go(1)" data-go="1">
          <span class="stp-num"><span>1</span></span>
          <span class="stp-label">Write</span>
        </button>
        <span class="stp-line {{ $step > 1 ? 'done' : '' }}" data-line="1"></span>

        <button type="button" class="stp {{ $step === 2 ? 'active' : ($step > 2 ? 'done' : '') }}" wire:click="go(2)" data-go="2">
          <span class="stp-num"><span>2</span></span>
          <span class="stp-label">Media</span>
          @if(count($attachedMedia) > 0)
            <span class="stp-badge" id="mediaStepBadge">{{ count($attachedMedia) }}</span>
          @else
            <span class="stp-badge" id="mediaStepBadge" hidden>0</span>
          @endif
        </button>
        <span class="stp-line {{ $step > 2 ? 'done' : '' }}" data-line="2"></span>

        <button type="button" class="stp {{ $step === 3 ? 'active' : ($step > 3 ? 'done' : '') }}" wire:click="go(3)" data-go="3">
          <span class="stp-num"><span>3</span></span>
          <span class="stp-label">Organize &amp; access</span>
        </button>
        <span class="stp-line {{ $step > 3 ? 'done' : '' }}" data-line="3"></span>

        <button type="button" class="stp {{ $step === 4 ? 'active' : '' }}" wire:click="go(4)" data-go="4">
          <span class="stp-num"><span>4</span></span>
          <span class="stp-label">Review &amp; publish</span>
        </button>
      </div>

      <!-- ===== DESK WORKFLOW STRIP ===== -->
      <div class="wf-strip" id="wfStrip">
        <span class="wf-pill {{ $status === 'published' ? 'live' : ($status === 'in_review' ? 'review' : ($status === 'changes_requested' ? 'rework' : ($status === 'approved' ? 'approved' : 'draft'))) }}" id="wfPill">
          {{ $status === 'published' ? 'Published' : ($status === 'in_review' ? 'In review — with editor' : ($status === 'changes_requested' ? 'Changes requested' : ($status === 'approved' ? 'Approved by editor' : 'Draft'))) }}
        </span>
        <span class="wf-owner" id="wfOwner">
          <span class="wf-ava {{ $ownerId === auth()->id() ? 'navy' : 'green' }}">{{ strtoupper(substr($ownerName, 0, 2)) }}</span>
          <span>Owner: <b>{{ $ownerName }}</b> ({{ $ownerRole }})</span>
        </span>
        <span class="spacer"></span>
        @if($ownerId !== auth()->id() && $status !== 'published')
          <button class="btn btn-outline" id="wfTakeOverBtn" type="button" wire:click="takeOver" title="Take ownership — the current owner is notified, the handover is audit-logged">Take over this story</button>
        @endif
        @if($status !== 'published' && $status !== 'in_review')
          <button class="btn btn-navy" id="wfSendBtn" type="button" wire:click="sendToReview" title="Send to the desk editor for final check">Send to editor</button>
        @endif
      </div>

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
          <div class="ai-start" id="aiStart">
            <div class="ai-start-head">
              <span class="ai-label">✦ Start with AI</span>
              <span class="ai-note">Paste raw notes, a press release or field copy — AI drafts the headline, brief, body, category &amp; tags. <strong>Nothing is applied without your click.</strong></span>
              <button type="button" class="ai-start-toggle" id="aiStartToggle">Hide</button>
            </div>
            <div class="ai-start-body" id="aiStartBody">
              <textarea class="ai-raw" id="aiRaw" placeholder="Paste notes, press release, bullet points, rough quotes, or field copy here…"></textarea>
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
              <div id="editorBody">{!! $bodyHtml !!}</div>

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

      <!-- ================================================================= -->
      <!-- ===== STEP 2: MEDIA ===== -->
      <!-- ================================================================= -->
      <section class="step-panel {{ $step === 2 ? 'active' : '' }}" data-step="2">
        <div class="card" id="featuredCard">
          <div class="card-title">Featured image</div>

          <div class="featured-preview {{ $featuredMediaId ? 'has-photo' : '' }}" id="featuredPreview">
            @if($featuredMediaId)
              <div class="fp-photo g{{ (($featuredMediaId % 8) + 1) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
              </div>
              <div class="fp-cap">{{ $featuredCaption ?: 'Featured image #'.$featuredMediaId }}</div>
            @else
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
              <span class="fp-text">No photo selected yet</span>
            @endif
          </div>

          <div class="img-actions">
            <label class="btn btn-outline" style="cursor:pointer">
              <input type="file" accept="image/*" hidden id="fileInput">
              Upload new
            </label>
            <button type="button" class="btn btn-archive" id="openArchive">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
              Choose from photo archive
            </button>
          </div>
          <div class="field-hint warn" id="fileHint">Recommended size: 1200 × 630 px · JPG or PNG, max 5 MB</div>

          <div class="field" style="margin-top:16px">
            <label class="field-label">Image caption</label>
            <textarea class="text-area" style="min-height:70px" id="imgCaption" wire:model.live="featuredCaption" placeholder="Caption with photo credit, e.g. — Photo: UNB"></textarea>
          </div>
        </div>

        <div class="card" id="attachCard">
          <div class="card-title">Attached media</div>
          <div class="field-hint" style="margin:-8px 0 14px">
            Extra photos &amp; videos clients can download with this story — you can drag &amp; drop files anywhere on this page, they land here automatically.
          </div>

          <div class="bulk-bar" id="bulkBar" @if(empty($attachedMedia)) hidden @endif>
            <label class="bulk-all"><input type="checkbox" id="selectAllMedia"> Select all</label>
            <span class="bulk-count" id="bulkCount">None selected</span>
            <select class="select-input bulk-select" id="bulkRights">
              <option value="story">With story — all recipients (default)</option>
              <option value="pack">Media pack clients only</option>
              <option value="exclusive">Exclusive clients only</option>
            </select>
            <button type="button" class="btn btn-navy btn-sm" id="bulkApply" disabled>Apply to selected</button>
          </div>

          <div class="attach-grid" id="attachGrid">
            @foreach($attachedMedia as $i => $att)
              <div class="attach-tile">
                <input type="checkbox" class="attach-check" data-i="{{ $i }}" title="Select for bulk action">
                <div class="attach-thumb {{ $att['grad'] ?? 'g1' }}" data-i="{{ $i }}" title="Click to edit">
                  <span class="attach-type">{{ ($att['type'] ?? '') === 'video' ? '▶ Video' : 'Photo' }}</span>
                  <button type="button" class="attach-remove" data-i="{{ $i }}" title="Remove">✕</button>
                  <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                </div>
                <div class="attach-info">
                  <div class="attach-cap" title="{{ $att['cap'] }}">{{ $att['cap'] }}</div>
                  <select class="attach-rights" data-i="{{ $i }}">
                    <option value="story" {{ ($att['rights'] ?? '') === 'story' ? 'selected' : '' }}>With story — all recipients</option>
                    <option value="pack" {{ ($att['rights'] ?? '') === 'pack' ? 'selected' : '' }}>Media pack clients only</option>
                    <option value="exclusive" {{ ($att['rights'] ?? '') === 'exclusive' ? 'selected' : '' }}>Exclusive clients only</option>
                  </select>
                </div>
              </div>
            @endforeach
          </div>

          <div class="attach-empty" id="attachEmpty" @if(!empty($attachedMedia)) style="display:none" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            No media attached yet — stories with media get far more client downloads
          </div>

          <div class="img-actions">
            <label class="btn btn-outline" style="cursor:pointer">
              <input type="file" accept="image/*,video/*" multiple hidden id="mediaUploadInput">
              Upload media
            </label>
            <button type="button" class="btn btn-archive" id="openAttachArchive">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
              Add from photo archive
            </button>
          </div>
          <div class="field-hint warn">Photos: JPG/PNG up to 5 MB · Videos: MP4 up to 200 MB</div>
        </div>
      </section>

      <!-- ================================================================= -->
      <!-- ===== STEP 3: ORGANIZE & ACCESS ===== -->
      <!-- ================================================================= -->
      <section class="step-panel {{ $step === 3 ? 'active' : '' }}" data-step="3">
        <div class="two-col">
          <div>
            <div class="card">
              <div class="card-title">Organize</div>

              <div class="field {{ !empty($aiTouched['category']) ? 'ai-touched' : '' }}">
                <label class="field-label">News category <span class="req">*</span></label>
                <select class="select-input" id="catSelect" wire:model.live="categoryId">
                  <option value="">Select category</option>
                  @foreach($cats as $c)
                    <option value="{{ $c->id }}">{{ $c->name_en }} / {{ $c->name_bn }}</option>
                  @endforeach
                </select>
                @error('categoryId') <div class="error-text show">{{ $message }}</div> @enderror
              </div>

              <div class="field">
                <label class="field-label">News sub category</label>
                <select class="select-input" id="subcatSelect" wire:model.live="subCategoryId">
                  <option value="">Select sub category</option>
                  @foreach($subs as $s)
                    <option value="{{ $s->id }}">{{ $s->name_en }} / {{ $s->name_bn }}</option>
                  @endforeach
                </select>
              </div>

              <div class="field">
                <label class="field-label">Author / Desk</label>
                <input class="text-input" type="text" id="authorInput" wire:model.live.debounce.300ms="author" placeholder="Desk / reporter name">
              </div>

              <div class="field {{ !empty($aiTouched['tags']) ? 'ai-touched' : '' }}">
                <label class="field-label">Tags</label>
                <div class="tag-wrap">
                  <div class="tag-box" id="tagBox">
                    @foreach($tags as $t)
                      <span class="tag-chip" data-name="{{ $t }}">#{{ $t }} <button type="button" wire:click="removeTag('{{ $t }}')" aria-label="Remove">✕</button></span>
                    @endforeach
                    <input type="text" id="tagInput" placeholder="Type # to search saved tags" autocomplete="off">
                  </div>
                  <div class="tag-suggest" id="tagSuggest"></div>
                </div>
                <div class="field-hint">Type <strong>#</strong> for saved tags · Space or Enter adds the tag</div>
              </div>
            </div>

            <div class="card">
              <div class="card-title">News type &amp; priority</div>
              <div class="type-chips" id="newsTypeChips">
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="has_video"><span>Has video</span></label>
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="top_news"><span>Top news</span></label>
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="trending"><span>Trending</span></label>
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="editors_pick"><span>Editor's pick</span></label>
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="slider"><span>Slider</span></label>
                <label class="type-chip"><input type="checkbox" wire:model.live="newsTypes" value="special"><span>Special</span></label>
              </div>

              <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-border">
                <div>
                  <label class="field-label">Priority</label>
                  <select class="select-input" wire:model.live="priority">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="flash">Flash (Breaking)</option>
                  </select>
                </div>
                <div>
                  <label class="field-label">Embargo until (Dhaka)</label>
                  <input class="text-input" type="datetime-local" wire:model.live="embargoUntil">
                </div>
              </div>
            </div>
          </div>

          <div>
            <div class="card">
              <div class="card-title">Access &amp; packages</div>

              <div class="seg-control" id="accessSeg">
                <label class="seg-opt">
                  <input type="radio" name="access" value="standard" wire:model.live="access" {{ $access === 'standard' ? 'checked' : '' }}>
                  <span><span class="seg-dot std"></span>Standard</span>
                </label>
                <label class="seg-opt">
                  <input type="radio" name="access" value="exclusive" wire:model.live="access" {{ $access === 'exclusive' ? 'checked' : '' }}>
                  <span><span class="seg-dot exc"></span>Exclusive</span>
                </label>
              </div>

              <div class="exclusive-opts {{ $access === 'exclusive' ? 'show' : '' }}" id="exclusiveOpts">
                <div class="field">
                  <label class="field-label">Eligible subscription tiers</label>
                  <div class="type-chips" id="tierChips">
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveTiers" value="Premium" checked><span>Premium</span></label>
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveTiers" value="Standard"><span>Standard</span></label>
                  </div>
                </div>
                <div class="field">
                  <label class="field-label">Or limit to specific clients</label>
                  <div class="type-chips" id="clientChips">
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveClients" value="Daily Star"><span>Daily Star</span></label>
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveClients" value="Prothom Alo"><span>Prothom Alo</span></label>
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveClients" value="Bangladesh Today"><span>Bangladesh Today</span></label>
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveClients" value="Jugantor"><span>Jugantor</span></label>
                    <label class="type-chip"><input type="checkbox" wire:model.live="exclusiveClients" value="Ittefaq"><span>Ittefaq</span></label>
                  </div>
                  <div class="field-hint">Exclusive stories are watermarked per client and tracked in the distribution log.</div>
                </div>
              </div>

              <div class="dist-preview">
                <div class="dist-title">Who gets what</div>
                <div id="distRows">
                  <div class="dist-row">
                    <span class="dist-dot" style="background:var(--green)"></span>
                    <div><span class="dist-tier">Premium</span> · <span class="dist-gets">Full story + featured image + {{ count($attachedMedia) }} media attachments</span></div>
                  </div>
                  <div class="dist-row">
                    <span class="dist-dot" style="background:var(--blue)"></span>
                    <div><span class="dist-tier">Standard</span> · <span class="dist-gets">Full text · no media downloads</span></div>
                  </div>
                  <div class="dist-row">
                    <span class="dist-dot" style="background:var(--muted-2)"></span>
                    <div><span class="dist-tier">Basic</span> · <span class="dist-gets">Headline + brief only</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================================================================= -->
      <!-- ===== STEP 4: REVIEW & PUBLISH ===== -->
      <!-- ================================================================= -->
      <section class="step-panel {{ $step === 4 ? 'active' : '' }}" data-step="4">
        @if(!$successState)
        <div class="card" id="reviewCard">
          <div class="card-title">Review before publishing</div>
          <div id="reviewRows">
            <div class="rv-section">
              <div class="rv-group-title">Story <button type="button" class="rv-edit" wire:click="go(1)">Edit</button></div>
              <div class="rv-row"><span class="k">Headline</span><span class="v serif">{{ $headline ?: 'Missing headline' }}</span></div>
              @if($subHead)<div class="rv-row"><span class="k">Sub head</span><span class="v">{{ $subHead }}</span></div>@endif
              <div class="rv-row"><span class="k">Brief</span><span class="v">{{ strlen($brief) }} / 280 characters</span></div>
              <div class="rv-row"><span class="k">Body</span><span class="v">{{ str_word_count(strip_tags($bodyHtml)) }} words</span></div>

              <div class="rv-group-title">Media <button type="button" class="rv-edit" wire:click="go(2)">Edit</button></div>
              <div class="rv-row"><span class="k">Featured image</span><span class="v">{{ $featuredMediaId ? ($featuredCaption ?: 'Image #'.$featuredMediaId) : 'Not set' }}</span></div>
              <div class="rv-row"><span class="k">Attachments</span><span class="v">{{ count($attachedMedia) ? count($attachedMedia).' items attached' : 'None' }}</span></div>

              <div class="rv-group-title">Organize &amp; access <button type="button" class="rv-edit" wire:click="go(3)">Edit</button></div>
              <div class="rv-row"><span class="k">Category</span><span class="v">{{ $categoryId ? (optional(\App\Models\Category::find($categoryId))->name_en ?: 'Category #'.$categoryId) : 'Not selected' }}</span></div>
              <div class="rv-row"><span class="k">Tags</span><span class="v">{{ count($tags) ? implode(', #', array_map(fn($t) => '#'.$t, $tags)) : '—' }}</span></div>
              <div class="rv-row"><span class="k">Access</span><span class="v">{{ $access === 'exclusive' ? 'Exclusive distribution' : 'Standard — all subscribers' }}</span></div>
            </div>
          </div>

          <div class="publish-note" style="margin-top:18px">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Story enters the distribution queue right after publishing and is pushed to all subscribed clients.
          </div>

          <!-- Internal notes thread -->
          <div class="wf-notes">
            <div class="wf-notes-title">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Internal notes
              <span class="wf-notes-hint">newsroom only — never published, never sent to clients</span>
            </div>
            <div class="nt-list" id="ntList">
              @foreach($notes as $n)
                @php $roleName = $n->user?->role?->name ?? ''; @endphp
                <div class="nt-item {{ str_contains(strtolower($roleName), 'editor') && !str_contains(strtolower($roleName), 'sub') ? 'editor' : 'sub' }}">
                  <div class="nt-head">
                    <b>{{ $n->user?->name ?? 'Staff' }}</b>
                    <span class="nt-role {{ str_contains(strtolower($roleName), 'editor') && !str_contains(strtolower($roleName), 'sub') ? '' : 'sub' }}">{{ $roleName ?: 'Desk' }}</span>
                    <span class="nt-time">{{ $n->created_at->format('M j, g:i A') }}</span>
                  </div>
                  <div class="nt-body">{{ $n->body }}</div>
                </div>
              @endforeach
            </div>
            <div class="nt-reply">
              <textarea wire:model.live="noteBody" id="ntInput" rows="2" placeholder="Reply to the desk — leave context for the next shift…"></textarea>
              <button type="button" wire:click="addNote" class="btn btn-navy btn-sm" id="ntSend">Add note</button>
            </div>
          </div>
        </div>
        @endif

        <!-- Story published success card -->
        <div class="card success-card {{ $successState === 'published' ? 'show' : '' }}" id="successCard">
          <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div class="success-title">Story published</div>
          <div class="success-sub">Pushed to the distribution queue — subscribed clients will receive it shortly.<br>Media, tags and packages can still be changed — updates go out automatically.</div>
          <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap">
            <button type="button" class="btn btn-quick" id="addMediaAfter" wire:click="go(2)">Add media now</button>
            <a href="{{ route('admin.news', ['language' => $language]) }}" class="btn btn-navy">View in News List</a>
            <a href="{{ route('admin.add-news') }}" class="btn btn-outline">Add another story</a>
          </div>
        </div>

        <!-- Sent for review card -->
        <div class="card success-card {{ $successState === 'sent' ? 'show' : '' }}" id="sentCard">
          <div class="sent-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
          </div>
          <div class="success-title">Sent for final check</div>
          <div class="success-sub" id="sentSub">The story is now <b>In review</b>. The desk editor has been notified and will check before publication.</div>
          <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap">
            <button type="button" class="btn btn-outline" id="sentBackBtn" wire:click="go(1)">Back to editing</button>
            <a href="{{ route('admin.news', ['language' => $language]) }}" class="btn btn-navy">View in News List</a>
          </div>
        </div>
      </section>

      <!-- ===== WIZARD NAV ===== -->
      <div class="wizard-nav" id="wizardNav" @if($successState) style="display:none" @endif>
        <button type="button" class="btn btn-outline" id="backBtn" @if($step === 1) style="visibility:hidden" @endif>← Back</button>
        <span class="wizard-progress" id="progressText">Step {{ $step }} of 4 — {{ $step === 1 ? 'Write' : ($step === 2 ? 'Media' : ($step === 3 ? 'Organize & access' : 'Review & publish')) }}</span>
        <span class="spacer"></span>
        <button type="button" class="btn btn-outline" id="saveDraftBtn" wire:click="autosave">Save draft</button>
        <button type="button" class="btn btn-quick" id="quickPublishBtn" wire:click="quickPublish" style="{{ $step === 4 ? 'display:none' : '' }}">Publish now</button>
        <button type="button" class="btn btn-navy" id="nextBtn" style="{{ $step === 4 ? 'display:none' : '' }}">{{ $step === 3 ? 'Review →' : 'Continue →' }}</button>
        <button type="button" class="btn btn-primary" id="publishBtn" wire:click="publish" style="{{ $step === 4 ? '' : 'display:none' }}">Publish story</button>
      </div>
    </div><!-- /add-form-col -->

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
  <div class="pv-overlay" id="pvOverlay">
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

  <!-- ============ PHOTO ARCHIVE MODAL ============ -->
  <div class="media-overlay" id="mediaOverlay">
    <div class="media-modal">
      <div class="mm-header">
        <div>
          <div class="mm-title">Photo &amp; video archive</div>
          <div class="mm-count">{{ count($media) }} photos in archive · UNB staff &amp; AP</div>
        </div>
        <button type="button" class="mm-close" id="closeModal" title="Close">✕</button>
      </div>

      <div class="mm-filters">
        <input class="mm-search" type="text" id="mmSearch" placeholder="Search by caption, keyword, date…">
        <select class="select-input" id="mediaTypeFilter" style="width:130px; background-color:#fff">
          <option value="all">All media</option>
          <option value="photo">Photos</option>
          <option value="video">Videos</option>
        </select>
      </div>

      <div class="mm-body" id="photoGrid">
        @foreach($media as $m)
          <div class="photo-card" data-id="{{ $m->id }}" data-type="{{ $m->kind === 'video' ? 'video' : 'photo' }}" data-cap="{{ $m->caption ?: $m->title }}">
            <div class="photo-check"><svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="photo-img g{{ (($m->id % 8) + 1) }}">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            </div>
            <div class="photo-cap">{{ $m->caption ?: $m->title }}</div>
            <div class="photo-date">{{ $m->created_at->format('M d, Y') }} · UNB</div>
          </div>
        @endforeach
      </div>

      <div class="mm-footer">
        <span class="mm-selected-count" id="selectedCount">No photo selected</span>
        <div class="mm-footer-btns">
          <button type="button" class="btn btn-outline" id="cancelModal">Cancel</button>
          <button type="button" class="btn btn-primary" id="insertPhoto" disabled>Insert photo</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ IMPORT FROM DOC MODAL ============ -->
  <div class="media-overlay" id="docOverlay">
    <div class="media-modal doc-modal">
      <div class="mm-header">
        <div>
          <div class="mm-title">Import story from document</div>
          <div class="mm-count">Upload a .docx or .txt draft — headline and body will populate the wizard</div>
        </div>
        <button type="button" class="mm-close" id="closeDoc" title="Close">✕</button>
      </div>

      <div class="doc-body">
        <label class="doc-dropzone" id="docDrop">
          <input type="file" accept=".docx,.txt" id="docFile" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
          <div><strong>Drop your document here</strong> or <span class="browse">browse files</span></div>
          <div class="doc-hint">Supports Word (.docx) and plain text (.txt) up to 10 MB</div>
        </label>
        <div class="doc-error" id="docError"></div>

        <div class="doc-preview" id="docPreview" hidden>
          <div class="doc-meta" id="docMeta"></div>
          <div class="doc-field">
            <span class="doc-flabel">Extracted headline (first line)</span>
            <div class="doc-headline" id="docHeadline"></div>
          </div>
          <div class="doc-field">
            <span class="doc-flabel">Extracted body preview</span>
            <div class="doc-body-preview" id="docBody"></div>
          </div>
        </div>
      </div>

      <div class="mm-footer">
        <span class="mm-selected-count">Formatting will be preserved where supported</span>
        <div class="mm-footer-btns">
          <button type="button" class="btn btn-outline" id="cancelDoc">Cancel</button>
          <button type="button" class="btn btn-primary" id="docInsert" disabled>Use this document</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ MEDIA EDIT MODAL ============ -->
  <div class="media-overlay" id="editOverlay">
    <div class="media-modal edit-modal">
      <div class="mm-header">
        <div>
          <div class="mm-title">Edit media details</div>
          <div class="mm-count" id="editMeta">Photo</div>
        </div>
        <button type="button" class="mm-close" id="closeEdit" title="Close">✕</button>
      </div>
      <div class="edit-stage" id="editStage"></div>
      <div class="edit-fields">
        <div class="field">
          <label class="field-label">Caption override</label>
          <textarea class="text-area" id="editCaption" rows="2" placeholder="Caption with photographer credit…"></textarea>
        </div>
        <div class="field">
          <label class="field-label">Distribution rights</label>
          <select class="select-input" id="editRights">
            <option value="story">With story — all recipients</option>
            <option value="pack">Media pack clients only</option>
            <option value="exclusive">Exclusive clients only</option>
          </select>
        </div>
      </div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cancelEdit">Cancel</button>
        <button type="button" class="btn btn-primary" id="applyEdit">Save changes</button>
      </div>
    </div>
  </div>

  <!-- ============ RELATED STORY MODAL ============ -->
  <div class="media-overlay" id="relOverlay">
    <div class="media-modal" style="width:min(600px, 100%)">
      <div class="mm-header">
        <div>
          <div class="mm-title">Insert related story embed</div>
          <div class="mm-count">Search published stories to link inside the body</div>
        </div>
        <button type="button" class="mm-close" id="closeRel" title="Close">✕</button>
      </div>
      <div style="padding:16px 24px">
        <input class="rel-search" type="text" id="relSearch" placeholder="Search by headline or topic…">
        <div class="rel-list" id="relList"></div>
      </div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cancelRel">Cancel</button>
        <button type="button" class="btn btn-primary" id="relInsert" disabled>Embed link</button>
      </div>
    </div>
  </div>

  <!-- ============ TABLE MODAL ============ -->
  <div class="media-overlay" id="tblOverlay">
    <div class="media-modal" style="width:min(580px, 100%)">
      <div class="mm-header">
        <div>
          <div class="mm-title">Insert data table</div>
          <div class="mm-count">Formats a wire-friendly text data table</div>
        </div>
        <button type="button" class="mm-close" id="closeTbl" title="Close">✕</button>
      </div>
      <div class="tbl-grid">
        <div class="field">
          <label class="field-label">Rows</label>
          <input type="number" class="text-input" id="tblRows" value="3" min="1" max="10">
        </div>
        <div class="field">
          <label class="field-label">Columns</label>
          <input type="number" class="text-input" id="tblCols" value="3" min="2" max="5">
        </div>
        <div class="field" style="display:flex; align-items:center; gap:8px; margin-top:24px">
          <input type="checkbox" id="tblHeader" checked class="accent-crimson">
          <label for="tblHeader" class="text-xs font-bold">Include header row</label>
        </div>
      </div>
      <div class="tbl-prev" id="tblPrev"></div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cancelTbl">Cancel</button>
        <button type="button" class="btn btn-primary" id="tblInsert">Insert table</button>
      </div>
    </div>
  </div>

  <!-- ============ REVISION HISTORY MODAL ============ -->
  <div class="media-overlay" id="hisOverlay">
    <div class="media-modal" style="width:min(640px, 100%)">
      <div class="mm-header">
        <div>
          <div class="mm-title">Revision history</div>
          <div class="mm-count">Snapshots taken automatically while editing in this session</div>
        </div>
        <button type="button" class="mm-close" id="closeHis" title="Close">✕</button>
      </div>
      <div class="his-list" id="hisList"></div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cancelHis">Close</button>
      </div>
    </div>
  </div>

  <!-- ============ AI ASSIST MODAL ============ -->
  <div class="media-overlay" id="aiOverlay">
    <div class="media-modal ai-modal">
      <div class="mm-header">
        <div>
          <div class="mm-title" id="aiTitle">AI suggestions</div>
          <div class="mm-count" id="aiSub">Drafted from your body text</div>
        </div>
        <button type="button" class="mm-close" id="closeAi" title="Close">✕</button>
      </div>
      <div class="ai-body" id="aiBody"></div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cancelAi">Cancel</button>
        <button type="button" class="btn btn-primary" id="aiUse">Apply to story</button>
      </div>
    </div>
  </div>

  <!-- ============ AI SUGGESTION DRAWER ============ -->
  <div class="ai-drawer" id="aiDrawer">
    <div class="aid-head">
      <div>
        <div class="aid-title">✦ AI Editorial Assistant</div>
        <div class="aid-sub" id="aidSub">Suggestions only — apply per card</div>
      </div>
      <button type="button" class="aid-close" id="aidClose" title="Close">✕</button>
    </div>
    <div class="aid-body" id="aidBody"></div>
    <div class="aid-foot">
      <span>UNB Newsroom AI Guardrails Active</span>
      <span class="aid-demo" id="aidTokens">Tokens audited</span>
    </div>
  </div>

  <!-- ============ RAW VS AI COMPARE MODAL ============ -->
  <div class="media-overlay" id="cmpOverlay">
    <div class="media-modal cmp-modal">
      <div class="mm-header">
        <div>
          <div class="mm-title" id="cmpTitle">Raw copy vs AI pre-edit</div>
          <div class="mm-count" id="cmpSub">Side-by-side comparison of changes</div>
        </div>
        <button type="button" class="mm-close" id="cmpClose" title="Close">✕</button>
      </div>
      <div style="padding:16px 24px">
        <div class="cmp-cols">
          <div class="cmp-pane left">
            <div class="cmp-pane-head">Original text</div>
            <div class="cmp-text" id="cmpLeft"></div>
          </div>
          <div class="cmp-pane right">
            <div class="cmp-pane-head">✦ AI suggestion</div>
            <div class="cmp-text" id="cmpRight"></div>
          </div>
        </div>
        <div class="cmp-legend" id="cmpLegend">
          <span><span class="swatch" style="background:var(--crimson-soft);border:1px solid #f9c6c8"></span><b>removed</b></span>
          <span><span class="swatch" style="background:var(--green-bg);border:1px solid #b9e6c9"></span><b>added / rewritten</b></span>
        </div>
      </div>
      <div class="mm-footer">
        <button type="button" class="btn btn-outline" id="cmpBack">← Back</button>
        <button type="button" class="btn btn-primary" id="cmpApply">Apply AI version</button>
      </div>
    </div>
  </div>

  <!-- ============ AI PUBLISH GATE MODAL ============ -->
  <div class="media-overlay" id="aiGateOverlay">
    <div class="modal ai-gate">
      <h3>✦ AI Human Review Checklist</h3>
      <div class="g-sub">This story includes AI-generated or assisted content. Verify accuracy before wire broadcast (<span id="gateFacts">0 facts flagged</span>).</div>
      <label class="g-check"><input type="checkbox" class="gate-check"> <span><b>Headline checked:</b> Accurate, tone compliant, no hallucinated quotes.</span></label>
      <label class="g-check"><input type="checkbox" class="gate-check"> <span><b>Facts &amp; figures confirmed:</b> All numbers and names match source notes.</span></label>
      <label class="g-check"><input type="checkbox" class="gate-check"> <span><b>Category &amp; tags verified:</b> Correct taxonomy for wire routing.</span></label>
      <div class="g-foot">
        <button type="button" class="btn btn-outline" id="gateCancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="gatePublish" disabled>Confirm &amp; publish</button>
      </div>
    </div>
  </div>

  <!-- ============ DRAG & DROP OVERLAY ============ -->
  <div class="drop-overlay" id="dropOverlay">
    <div class="drop-overlay-inner">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <div class="do-title">Drop photos or videos to attach</div>
      <div class="do-sub">Files will be added directly to this story's media attachments</div>
    </div>
  </div>

  <div class="up-toasts" id="upToasts"></div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
function addNewsWizard() {
  return {
    init() {
      // Initialize client-side wizard controller
      window.initAddNewsClientController && window.initAddNewsClientController(this);
    }
  };
}
</script>
@endpush
