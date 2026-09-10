<div id="addNewsApp">
  @include('livewire.admin.partials.add-news-header')
  @include('livewire.admin.partials.add-news-stepper')
  @include('livewire.admin.partials.add-news-step1-write')
  @include('livewire.admin.partials.add-news-step2-media')
  @include('livewire.admin.partials.add-news-step3-organize')
  @include('livewire.admin.partials.add-news-step4-review')
  @include('livewire.admin.partials.add-news-preview-panel')
  @include('livewire.admin.partials.add-news-modals')
  @include('livewire.admin.partials.add-news-ai-drawer')
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
