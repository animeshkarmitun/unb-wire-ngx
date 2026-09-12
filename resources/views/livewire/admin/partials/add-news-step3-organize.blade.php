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
