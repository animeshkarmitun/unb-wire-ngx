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
