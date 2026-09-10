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
          <button type="button" class="btn btn-outline pv-top-toggle" id="pvToggleBtn" title="Show / hide live preview" aria-label="Toggle live preview">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview
          </button>
          <button type="button" class="btn btn-outline" id="saveDraftTop" wire:click="autosave" aria-label="Save draft">
            <span wire:loading.remove wire:target="autosave">Save draft</span>
            <span wire:loading wire:target="autosave">Saving…</span>
          </button>
        </div>
      </div>
