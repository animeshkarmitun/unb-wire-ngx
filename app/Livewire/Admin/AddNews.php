<?php

namespace App\Livewire\Admin;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Models\StoryEvent;
use App\Models\StoryNote;
use App\Models\Tag;
use App\Services\AiService;
use App\Services\HtmlSanitizer;
use App\Services\NotificationService;
use App\Services\RbacService;
use App\Services\StoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class AddNews extends Component
{
    // Stepper state (1: Write, 2: Media, 3: Organize & access, 4: Review & publish)
    public int $step = 1;

    public ?int $storyId = null;

    public string $status = 'draft';

    public string $language = 'en';

    // Step 1: Write fields
    public string $headline = '';

    public string $subHead = '';

    public string $datelineCity = 'Dhaka';

    public string $datelineAt = '';

    public string $brief = '';

    public string $bodyHtml = '';

    public string $priority = 'routine';

    public bool $isBreaking = false;

    public string $embargoUntil = '';

    public string $author = 'UNB Desk';

    public string $aiRawText = '';

    // Step 2: Media fields
    public ?int $featuredMediaId = null;

    public string $featuredCaption = '';

    public array $attachedMedia = []; // Array of ['id' => int, 'cap' => string, 'role' => string, 'rights' => string, 'type' => string, 'grad' => string, 'src' => ?string]

    public array $selectedMediaIds = [];

    // Step 3: Organize & Access fields
    public string $categoryId = '';

    public string $subCategoryId = '';

    public array $tags = []; // Array of tag names e.g. ['bangladesh', 'dhaka']

    public array $newsTypes = []; // e.g. ['has_video', 'top_news', 'trending', 'editors_pick', 'slider', 'special']

    public string $access = 'standard'; // 'standard' | 'exclusive'

    public array $exclusiveTiers = ['Premium', 'Standard'];

    public array $exclusiveClients = [];

    // Step 4 & Workflow fields
    public array $aiTouched = [];

    public ?array $aiPack = null;

    public bool $aiLoading = false;

    public string $noteBody = '';

    public ?string $successState = null; // 'published' | 'sent' | null

    public ?int $ownerId = null;

    public string $ownerName = '';

    public string $ownerRole = '';

    public function mount(?int $id = null): void
    {
        $user = auth()->user();
        $this->ownerId = $user?->id;
        $this->ownerName = $user?->name ?? 'Desk User';
        $this->ownerRole = $user?->role?->name ?? 'Staff';
        $this->datelineAt = now()->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i');

        if ($id) {
            $s = Story::with(['tags', 'category', 'subCategory', 'owner.role', 'notes.user'])->findOrFail($id);
            $this->storyId = $s->id;
            $this->status = $s->status;
            $this->language = $s->language;
            $this->headline = $s->headline;
            $this->subHead = $s->sub_head ?? '';
            $this->datelineCity = $s->dateline_city ?? 'Dhaka';
            $this->datelineAt = $s->dateline_at ? $s->dateline_at->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i') : now()->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i');
            $this->brief = $s->brief;
            $this->bodyHtml = $s->body_html;
            $this->priority = $s->priority;
            $this->isBreaking = $s->is_breaking;
            $this->embargoUntil = $s->embargo_until ? $s->embargo_until->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i') : '';
            $this->categoryId = (string) $s->category_id;
            $this->subCategoryId = (string) ($s->sub_category_id ?? '');
            $this->aiTouched = $s->ai_touched ?? [];
            $this->ownerId = $s->owner_id;
            $this->ownerName = $s->owner?->name ?? $this->ownerName;
            $this->ownerRole = $s->owner?->role?->name ?? $this->ownerRole;

            // Load tags
            $this->tags = $s->tags->pluck('name')->toArray();

            // Load media
            $mediaRows = DB::table('story_media')
                ->join('media_assets', 'media_assets.id', '=', 'story_media.asset_id')
                ->where('story_media.story_id', $s->id)
                ->orderBy('story_media.sort_order')
                ->select('media_assets.id', 'media_assets.title', 'media_assets.caption', 'media_assets.kind', 'story_media.role', 'story_media.caption_override')
                ->get();

            foreach ($mediaRows as $m) {
                $item = [
                    'id' => (int) $m->id,
                    'cap' => $m->caption_override ?: ($m->caption ?: $m->title),
                    'role' => $m->role,
                    'rights' => 'story',
                    'type' => $m->kind === 'video' ? 'video' : 'photo',
                    'grad' => 'g'.(($m->id % 8) + 1),
                    'src' => null,
                ];
                if ($m->role === 'featured') {
                    $this->featuredMediaId = (int) $m->id;
                    $this->featuredCaption = $item['cap'];
                }
                $this->attachedMedia[] = $item;
                $this->selectedMediaIds[] = (int) $m->id;
            }
        }
    }

    public function next(): void
    {
        $this->validateStep();
        if ($this->step < 4) {
            $this->step++;
        }
        $this->autosave();
    }

    public function prev(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function go(int $stepNumber): void
    {
        if ($stepNumber >= 1 && $stepNumber <= 4) {
            if ($stepNumber > $this->step) {
                $this->validateStep();
            }
            $this->step = $stepNumber;
            $this->autosave();
        }
    }

    private function validateStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'headline' => 'required|max:300',
                'brief' => 'required|max:280',
            ], [
                'headline.required' => 'Headline is required to proceed.',
                'brief.required' => 'Brief / Intro is required.',
            ]);
        }

        if ($this->step === 3) {
            if (empty($this->categoryId)) {
                $firstCat = Category::whereNull('parent_id')->orderBy('sort_order')->first();
                if ($firstCat) {
                    $this->categoryId = (string) $firstCat->id;
                }
            }
        }
    }

    public function setLanguage(string $lang): void
    {
        if (in_array($lang, ['en', 'bn'], true)) {
            $this->language = $lang;
            $this->dispatch('language-changed', language: $lang);
            $this->autosave();
        }
    }

    public function updatedHeadline(): void
    {
        if (isset($this->aiTouched['headline'])) {
            unset($this->aiTouched['headline']);
        }
        $this->dispatch('story-updated');
    }

    public function updatedBrief(): void
    {
        if (isset($this->aiTouched['brief'])) {
            unset($this->aiTouched['brief']);
        }
        $this->dispatch('story-updated');
    }

    public function updatedBodyHtml(): void
    {
        if (isset($this->aiTouched['body'])) {
            unset($this->aiTouched['body']);
        }
        $this->dispatch('story-updated');
    }

    public function updatedCategoryId(): void
    {
        $this->subCategoryId = '';
        if (isset($this->aiTouched['category'])) {
            unset($this->aiTouched['category']);
        }
        $this->dispatch('story-updated');
    }

    public function syncBody(string $html): void
    {
        $this->bodyHtml = HtmlSanitizer::clean($html);
        if (isset($this->aiTouched['body'])) {
            unset($this->aiTouched['body']);
        }
        $this->dispatch('story-updated');
    }

    public function syncFromDoc(string $headline, string $body, ?string $brief = null): void
    {
        $this->headline = $headline;
        $cleanBody = HtmlSanitizer::clean($body);
        $this->bodyHtml = $cleanBody;
        if (! empty($brief)) {
            $this->brief = Str::limit($brief, 280, '');
        } elseif (empty($this->brief)) {
            $this->brief = Str::limit(HtmlSanitizer::text($cleanBody), 280, '');
        }
        $this->dispatch('toast', message: 'Document imported into story');
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function addTag(string $tagName): void
    {
        $clean = strtolower(trim(ltrim($tagName, '#')));
        if ($clean && ! in_array($clean, $this->tags, true)) {
            $this->tags[] = $clean;
            $this->dispatch('story-updated');
            $this->autosave();
        }
    }

    public function removeTag(string $tagName): void
    {
        $this->tags = array_values(array_diff($this->tags, [$tagName]));
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function setFeatured(int $assetId, string $caption = ''): void
    {
        $this->featuredMediaId = $assetId;
        $this->featuredCaption = $caption;
        if (! in_array($assetId, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds[] = $assetId;
        }
        $this->dispatch('toast', message: 'Featured image set');
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function toggleMedia(int $id, string $caption = '', string $kind = 'photo'): void
    {
        if (in_array($id, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds = array_values(array_diff($this->selectedMediaIds, [$id]));
            $this->attachedMedia = array_values(array_filter($this->attachedMedia, fn ($m) => $m['id'] !== $id));
            if ($this->featuredMediaId === $id) {
                $this->featuredMediaId = null;
                $this->featuredCaption = '';
            }
        } else {
            $this->selectedMediaIds[] = $id;
            $this->attachedMedia[] = [
                'id' => $id,
                'cap' => $caption ?: 'Photo #'.$id,
                'role' => 'inline',
                'rights' => 'story',
                'type' => $kind === 'video' ? 'video' : 'photo',
                'grad' => 'g'.(($id % 8) + 1),
                'src' => null,
            ];
        }
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function clearMedia(): void
    {
        $this->selectedMediaIds = [];
        $this->attachedMedia = [];
        $this->featuredMediaId = null;
        $this->featuredCaption = '';
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function callAi(string $kind, ?AiService $svc = null): void
    {
        $svc = $svc ?? app(AiService::class);
        $this->aiLoading = true;
        $payload = [
            'headline' => $this->headline,
            'brief' => $this->brief,
            'text' => $this->aiRawText ?: (HtmlSanitizer::text($this->bodyHtml) ?: $this->brief),
            'category' => $this->categoryId,
        ];
        $pack = $svc->call($kind, $payload, auth()->id(), $this->storyId);
        $this->aiLoading = false;

        if (isset($pack['error'])) {
            $this->dispatch('toast', message: $pack['error']);

            return;
        }

        $this->aiPack = $pack;
        $this->dispatch('ai-pack-ready', pack: $pack, kind: $kind);
        $this->dispatch('toast', message: 'AI suggestion ready');
    }

    public function applyAi(string $field): void
    {
        if (! $this->aiPack) {
            return;
        }

        if ($field === 'headline' && isset($this->aiPack['headline'])) {
            $this->headline = $this->aiPack['headline'];
            $this->aiTouched['headline'] = true;
        }
        if ($field === 'brief' && isset($this->aiPack['brief'])) {
            $this->brief = $this->aiPack['brief'];
            $this->aiTouched['brief'] = true;
        }
        if ($field === 'body' && isset($this->aiPack['body'])) {
            $this->bodyHtml = HtmlSanitizer::clean($this->aiPack['body']);
            $this->aiTouched['body'] = true;
            $this->dispatch('quill-set-content', html: $this->bodyHtml);
        }
        if ($field === 'category' && isset($this->aiPack['category']['name'])) {
            $cat = Category::where('name_en', 'like', '%'.$this->aiPack['category']['name'].'%')->first();
            if ($cat) {
                $this->categoryId = (string) $cat->id;
                $this->aiTouched['category'] = true;
            }
        }
        if ($field === 'tags' && ! empty($this->aiPack['tags'])) {
            foreach ($this->aiPack['tags'] as $t) {
                $this->addTag($t);
            }
            $this->aiTouched['tags'] = true;
        }

        $this->dispatch('toast', message: 'Applied AI '.$field);
        $this->dispatch('story-updated');
        $this->autosave();
    }

    public function autosave(?RbacService $rbac = null, ?StoryService $stories = null): void
    {
        $rbac = $rbac ?? app(RbacService::class);
        $stories = $stories ?? app(StoryService::class);

        $rbac->assertCan(auth()->user(), 'stories', 'edit');

        $cleanHtml = HtmlSanitizer::clean($this->bodyHtml ?: '<p></p>');
        $this->bodyHtml = $cleanHtml;

        $embargo = $this->embargoUntil ? $this->parseEmbargo($this->embargoUntil) : null;
        $dateline = $this->datelineAt ? $this->parseEmbargo($this->datelineAt) : null;

        $catId = $this->categoryId ? (int) $this->categoryId : Category::first()?->id;
        $subCatId = $this->subCategoryId ? (int) $this->subCategoryId : null;

        $data = [
            'language' => $this->language,
            'headline' => $this->headline ?: 'Untitled Draft',
            'sub_head' => $this->subHead ?: null,
            'brief' => $this->brief ?: '—',
            'body_html' => $cleanHtml,
            'body_text' => HtmlSanitizer::text($cleanHtml),
            'category_id' => $catId,
            'sub_category_id' => $subCatId,
            'dateline_city' => $this->datelineCity ?: null,
            'dateline_at' => $dateline,
            'is_breaking' => $this->isBreaking,
            'priority' => $this->priority,
            'embargo_until' => $embargo,
            'ai_touched' => $this->aiTouched ?: null,
            'word_count' => str_word_count(strip_tags($cleanHtml)),
        ];

        if ($this->storyId) {
            $s = Story::findOrFail($this->storyId);
            $stories->updateDraft($s, $data, $s->version, auth()->user());
        } else {
            $s = $stories->createDraft($data, auth()->user());
            $this->storyId = $s->id;
            $this->status = $s->status;
        }

        // Sync story_media
        DB::table('story_media')->where('story_id', $this->storyId)->delete();
        if ($this->featuredMediaId) {
            DB::table('story_media')->insert([
                'story_id' => $this->storyId,
                'asset_id' => $this->featuredMediaId,
                'role' => 'featured',
                'sort_order' => 0,
                'caption_override' => $this->featuredCaption ?: null,
            ]);
        }
        foreach ($this->attachedMedia as $idx => $att) {
            if ($att['id'] === $this->featuredMediaId) {
                continue;
            }
            DB::table('story_media')->insert([
                'story_id' => $this->storyId,
                'asset_id' => $att['id'],
                'role' => 'inline',
                'sort_order' => $idx + 1,
                'caption_override' => $att['cap'] ?: null,
            ]);
        }

        // Sync tags
        if (! empty($this->tags)) {
            $tagIds = [];
            foreach ($this->tags as $tName) {
                $slug = Str::slug($tName);
                $tagObj = Tag::firstOrCreate(['name' => $tName], ['slug' => $slug ?: Str::random(8)]);
                $tagIds[] = $tagObj->id;
            }
            $s->tags()->sync($tagIds);
        }

        $this->dispatch('draft-autosaved', [
            'id' => $this->storyId,
            'time' => now()->format('g:i A'),
        ]);
    }

    private function parseEmbargo(string $input): ?string
    {
        try {
            return Carbon::parse($input, 'Asia/Dhaka')->utc()->toDateTimeString();
        } catch (\Throwable $e) {
            return $input;
        }
    }

    public function takeOver(?StoryService $stories = null): void
    {
        $stories = $stories ?? app(StoryService::class);
        if ($this->storyId) {
            $s = Story::findOrFail($this->storyId);
            $stories->takeOver($s, auth()->user());
            $this->ownerId = auth()->id();
            $this->ownerName = auth()->user()->name;
            $this->ownerRole = auth()->user()->role?->name ?? 'Staff';
            $this->dispatch('toast', message: 'You have taken ownership of this story');
        }
    }

    public function sendToReview(?NotificationService $notifs = null, ?RbacService $rbac = null, ?StoryService $svc = null): void
    {
        $notifs = $notifs ?? app(NotificationService::class);
        $rbac = $rbac ?? app(RbacService::class);
        $svc = $svc ?? app(StoryService::class);

        $rbac->assertCan(auth()->user(), 'stories', 'edit');

        $this->validate([
            'headline' => 'required',
            'brief' => 'required',
            'categoryId' => 'required',
        ]);

        $this->autosave($rbac, $svc);
        $s = Story::findOrFail($this->storyId);
        $svc->transition($s, 'in_review', auth()->user());
        $this->status = 'in_review';

        $notifs->notifyReviewRequested($s->id, $s->headline, auth()->id());
        $this->successState = 'sent';
        $this->dispatch('toast', message: 'Sent for review to the desk editor');
    }

    public function publish(?NotificationService $notifs = null, ?RbacService $rbac = null, ?StoryService $svc = null): void
    {
        $notifs = $notifs ?? app(NotificationService::class);
        $rbac = $rbac ?? app(RbacService::class);
        $svc = $svc ?? app(StoryService::class);

        $rbac->assertCan(auth()->user(), 'stories', 'publish');

        $this->validate([
            'headline' => 'required',
            'brief' => 'required',
            'categoryId' => 'required',
        ]);

        $this->autosave($rbac, $svc);
        $s = Story::findOrFail($this->storyId);

        // Wizard publish may be invoked from draft by an editor/admin with publish permission.
        // Walk through the workflow so every transition is audited.
        if ($s->status === 'draft') {
            $svc->transition($s, 'in_review', auth()->user());
            $s->refresh();
        }
        if ($s->status === 'in_review') {
            $svc->transition($s, 'approved', auth()->user());
            $s->refresh();
        }
        if ($s->status === 'approved') {
            $svc->transition($s, 'published', auth()->user());
        }

        $s->refresh();
        $this->status = $s->status;

        if ($s->status === 'published') {
            dispatch(new FanoutStory($s->id));
            dispatch(new ProcessIndexOutbox);
            $notifs->notifyStatusChange($s->id, 'published', auth()->id());
            $this->successState = 'published';
            $this->dispatch('toast', message: 'Story successfully published to wire feed');
        }
    }

    public function quickPublish(?NotificationService $notifs = null, ?RbacService $rbac = null, ?StoryService $svc = null): void
    {
        $this->publish($notifs, $rbac, $svc);
    }

    public function addNote(): void
    {
        $this->validate(['noteBody' => 'required|min:2|max:2000']);
        if (! $this->storyId) {
            $this->autosave();
        }

        StoryNote::create([
            'story_id' => $this->storyId,
            'user_id' => auth()->id(),
            'body' => $this->noteBody,
            'is_internal' => true,
        ]);

        StoryEvent::create([
            'story_id' => $this->storyId,
            'actor_id' => auth()->id(),
            'action' => 'note_added',
            'from_status' => $this->status,
            'to_status' => $this->status,
        ]);

        $this->noteBody = '';
        $this->dispatch('toast', message: 'Note added to newsroom thread');
    }

    public function render()
    {
        $cats = Category::whereNull('parent_id')->orderBy('sort_order')->get();
        $subs = $this->categoryId ? Category::where('parent_id', $this->categoryId)->orderBy('sort_order')->get() : collect();
        $media = MediaAsset::where('status', 'library')->orderByDesc('created_at')->limit(24)->get();
        $notes = $this->storyId ? StoryNote::with('user.role')->where('story_id', $this->storyId)->where('is_internal', true)->orderBy('created_at')->get() : collect();

        return view('livewire.admin.add-news', compact('cats', 'subs', 'media', 'notes'));
    }
}
