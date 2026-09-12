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
