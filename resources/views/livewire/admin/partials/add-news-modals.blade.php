  <!-- ============ PHOTO ARCHIVE MODAL ============ -->
  <div class="media-overlay" id="mediaOverlay" wire:ignore>
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
  <div class="media-overlay" id="docOverlay" wire:ignore>
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
  <div class="media-overlay" id="editOverlay" wire:ignore>
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
  <div class="media-overlay" id="relOverlay" wire:ignore>
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
  <div class="media-overlay" id="tblOverlay" wire:ignore>
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
  <div class="media-overlay" id="hisOverlay" wire:ignore>
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
  <div class="media-overlay" id="aiOverlay" wire:ignore>
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

  <!-- ============ RAW VS AI COMPARE MODAL ============ -->
  <div class="media-overlay" id="cmpOverlay" wire:ignore>
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
  <div class="media-overlay" id="aiGateOverlay" wire:ignore>
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
  <div class="drop-overlay" id="dropOverlay" wire:ignore>
    <div class="drop-overlay-inner">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <div class="do-title">Drop photos or videos to attach</div>
      <div class="do-sub">Files will be added directly to this story's media attachments</div>
    </div>
  </div>

  <div class="up-toasts" id="upToasts"></div>
