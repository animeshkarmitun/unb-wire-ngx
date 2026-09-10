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
