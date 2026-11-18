      <!-- ===== STEPPER ===== -->
      @php
        $stpDone = [
          1 => trim($headline) !== '',
          2 => $featuredMediaId !== null || count($attachedMedia) > 0,
          3 => $categoryId !== '',
        ];
      @endphp
      <div class="stepper" id="stepper">
        <button type="button" class="stp {{ $step === 1 ? 'active' : ($step > 1 && $stpDone[1] ? 'done' : '') }}" wire:click="go(1)" data-go="1" aria-label="Step 1: Write" @if($step === 1) aria-current="step" @endif>
          <span class="stp-num"><span>1</span></span>
          <span class="stp-label">Write</span>
        </button>
        <span class="stp-line {{ $step > 1 && $stpDone[1] ? 'done' : '' }}" data-line="1"></span>

        <button type="button" class="stp {{ $step === 2 ? 'active' : ($step > 2 && $stpDone[2] ? 'done' : '') }}" wire:click="go(2)" data-go="2" aria-label="Step 2: Media" @if($step === 2) aria-current="step" @endif>
          <span class="stp-num"><span>2</span></span>
          <span class="stp-label">Media</span>
          @if(count($attachedMedia) > 0)
            <span class="stp-badge" id="mediaStepBadge">{{ count($attachedMedia) }}</span>
          @else
            <span class="stp-badge" id="mediaStepBadge" hidden>0</span>
          @endif
        </button>
        <span class="stp-line {{ $step > 2 && $stpDone[2] ? 'done' : '' }}" data-line="2"></span>

        <button type="button" class="stp {{ $step === 3 ? 'active' : ($step > 3 && $stpDone[3] ? 'done' : '') }}" wire:click="go(3)" data-go="3" aria-label="Step 3: Organize and access" @if($step === 3) aria-current="step" @endif>
          <span class="stp-num"><span>3</span></span>
          <span class="stp-label">Organize &amp; access</span>
        </button>
        <span class="stp-line {{ $step > 3 && $stpDone[3] ? 'done' : '' }}" data-line="3"></span>

        <button type="button" class="stp {{ $step === 4 ? 'active' : '' }}" wire:click="go(4)" data-go="4" aria-label="Step 4: Review and publish" @if($step === 4) aria-current="step" @endif>
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
        @if($ownerId !== auth()->id() && $status === 'in_review')
          <button class="btn btn-outline" id="wfNeedsWorkBtn" type="button" wire:click="toggleNeedsWorkForm(true)" wire:loading.attr="disabled" title="Send back to {{ $ownerName }} with a comment" aria-label="Send story back to owner with a comment">Needs work</button>
        @endif
        @if($ownerId !== auth()->id() && $status !== 'published')
          <button class="btn btn-outline" id="wfTakeOverBtn" type="button" wire:click="takeOver" wire:confirm="Take over this story? The current owner will be notified." title="Take ownership — the current owner is notified, the handover is audit-logged" aria-label="Take over ownership of this story">Take over this story</button>
        @endif
        @if($status !== 'published' && $status !== 'in_review' && !app(\App\Services\RbacService::class)->can(auth()->user(), 'stories', 'publish'))
          <button class="btn btn-navy" id="wfSendBtn" type="button" wire:click="sendToReview" title="Send to the desk editor for final check" aria-label="Send story to editor for review">Send to editor</button>
        @endif
      </div>

      @if($showNeedsWorkForm && $ownerId !== auth()->id())
        <div class="wf-strip" id="wfNeedsWorkForm" style="margin-top: 10px; border: 1px solid #e3e1da; border-radius: 12px; padding: 12px 14px; background: #fdf6ec;">
          <label class="text-[13px] font-semibold text-[#4b4e5c] block mb-2" for="needsWorkComment">What needs fixing? (sent back to {{ $ownerName }})</label>
          <textarea wire:model="needsWorkComment" id="needsWorkComment" rows="3" class="w-full text-[14px] border border-[#e3e1da] rounded-[10px] px-3 py-2 focus:outline-none focus:border-navy-800" placeholder="Describe the changes the uploader should make…"></textarea>
          @error('needsWorkComment') <div class="text-[12.5px] text-crimson font-medium mb-2">{{ $message }}</div> @enderror
          <div class="flex gap-2 justify-end mt-2">
            <button type="button" class="btn btn-outline" wire:click="toggleNeedsWorkForm(false)">Cancel</button>
            <button type="button" class="btn btn-navy" wire:click="requestChanges" wire:loading.attr="disabled">Send back with comment</button>
          </div>
        </div>
      @endif
