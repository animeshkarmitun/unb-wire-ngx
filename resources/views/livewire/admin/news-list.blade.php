<div>
    <!-- Topbar & Breadcrumb -->
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <div class="text-xs text-muted mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-navy-800">Home</a> &nbsp;/&nbsp; {{ $language === 'bn' ? 'Bangla News' : 'English News' }}
            </div>
            <h1 class="font-serif text-[28px] font-bold text-ink">{{ $language === 'bn' ? 'Bangla News' : 'English News' }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="export" class="btn btn-outline" title="Export current filtered stories to CSV">Export</button>
            <a href="{{ route('admin.add-news') }}" class="btn btn-primary" style="text-decoration:none">+ Add News</a>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="flex flex-wrap gap-2 mb-4 items-center">
        @php
            $tabDefs = [
                'all' => 'All',
                'published' => 'Live',
                'draft' => 'Draft',
                'in_review' => 'In review',
                'changes_requested' => 'Needs work',
            ];
        @endphp
        @foreach($tabDefs as $k => $label)
            <button type="button" wire:click="$set('status', '{{ $k }}')" class="px-3.5 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $status === $k ? 'bg-navy-800 text-white border-navy-800' : 'bg-panel border-[#e3e1da] text-ink hover:border-navy-800' }}">
                {{ $label }}
                <span class="ml-1 text-[10px] {{ $status === $k ? 'bg-white/20' : 'bg-[#f3f1ee]' }} px-1.5 py-0.5 rounded-full font-bold">
                    {{ $counts[$k] ?? 0 }}
                </span>
            </button>
        @endforeach
    </div>

    <!-- Card with Filters and Table -->
    <div class="bg-panel border border-border rounded-[14px] shadow-[0_1px_3px_rgba(28,31,46,0.04)] overflow-hidden">

        <!-- Filters Bar -->
        <div class="flex gap-2.5 p-[18px_22px] border-b border-border flex-wrap items-center">
            <input wire:model.live.debounce.300ms="search" class="search-input" type="text" placeholder="Search headlines, keywords…">

            <select wire:model.live="category" class="filter-select">
                <option value="all">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name_en }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" class="filter-select">
                <option value="all">All status</option>
                <option value="published">Live</option>
                <option value="draft">Draft</option>
                <option value="in_review">In review</option>
                <option value="changes_requested">Needs work</option>
            </select>

            <button type="button" wire:click="$refresh" class="btn btn-navy">Search</button>
        </div>

        <!-- Bulk Action Bar -->
        @if(count($selectedStories) > 0)
            <div class="bg-navy-800 text-white px-5 py-2.5 flex items-center justify-between text-xs transition-all">
                <span class="font-medium">{{ count($selectedStories) }} {{ count($selectedStories) === 1 ? 'story' : 'stories' }} selected</span>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="bulkPublish" class="px-3 py-1 bg-green hover:bg-green-700 text-white rounded font-medium transition-colors">Publish Selected</button>
                    <button type="button" wire:click="bulkDelete" wire:confirm="Are you sure you want to delete the selected stories?" class="px-3 py-1 bg-crimson hover:bg-crimson-dark text-white rounded font-medium transition-colors">Delete Selected</button>
                </div>
            </div>
        @endif

        <!-- 7-Column Table -->
        <div class="news-table-wrap">
            <table class="news-table">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" wire:model.live="selectAll"></th>
                        <th>Title</th>
                        <th style="width:120px">Category</th>
                        <th style="width:120px">Sub category</th>
                        <th style="width:80px">Views</th>
                        <th style="width:130px">Status</th>
                        <th style="width:110px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stories as $s)
                        @php
                            $tints = ['t-blue', 't-green', 't-purple', 't-amber'];
                            $tint = $tints[$s->id % 4];
                            $catRaw = $s->category?->name_en ?? 'general';
                            $catSlug = strtolower(preg_replace('/[^a-zA-Z]/', '', $catRaw));
                            if (!in_array($catSlug, ['bangladesh', 'sports', 'world', 'business'], true)) {
                                $catSlug = 'general';
                            }
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" wire:model.live="selectedStories" value="{{ $s->id }}">
                            </td>
                            <td>
                                <div class="title-cell">
                                    <div class="thumb {{ $tint }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="news-title hover:text-navy-800 cursor-pointer" wire:click="openDrawer({{ $s->id }})">
                                            {{ $s->headline }}
                                        </div>
                                        @if($s->owner)
                                            <div class="owner-line">
                                                <span class="wf-ava">{{ strtoupper(substr($s->owner->name, 0, 2)) }}</span>
                                                <span>{{ $s->owner->name }}</span>
                                                @if($s->status === 'in_review')
                                                    <span>· with editor {{ $s->assignedEditor?->name ?? 'on duty' }} for final check</span>
                                                @elseif($s->status === 'changes_requested')
                                                    <span>· editor sent it back {{ $s->updated_at->diffForHumans() }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="cat-tag {{ $catSlug }}">{{ $s->category?->name_en ?? 'General' }}</span>
                            </td>
                            <td class="subcat">
                                {{ $s->subCategory?->name_en ?? '—' }}
                            </td>
                            <td>
                                <span class="views">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    {{ number_format($s->view_count ?? 0) }}
                                </span>
                            </td>
                            <td>
                                @if($s->status === 'published')
                                    <label class="switch" title="Toggle Live / Archive">
                                        <input type="checkbox" checked wire:change="togglePublish({{ $s->id }})">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="status-label live">Live</span>
                                @elseif($s->status === 'in_review')
                                    <span class="wf-pill review">In review</span><br>
                                    <button type="button" class="note-badge wfd-open" wire:click="openDrawer({{ $s->id }})" title="Internal notes — newsroom only, click to open thread">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        {{ $s->notes->count() }}
                                    </button>
                                @elseif($s->status === 'changes_requested')
                                    <span class="wf-pill rework">Needs work</span><br>
                                    <button type="button" class="note-badge wfd-open" wire:click="openDrawer({{ $s->id }})" title="Internal notes — newsroom only, click to open thread">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        {{ $s->notes->count() }}
                                    </button>
                                @elseif($s->status === 'draft')
                                    <label class="switch" title="Toggle draft to publish">
                                        <input type="checkbox" wire:change="togglePublish({{ $s->id }})">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="status-label draft">Draft</span>
                                    @if($s->notes->count() > 0)
                                        <br>
                                        <button type="button" class="note-badge wfd-open" wire:click="openDrawer({{ $s->id }})" title="Internal notes">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                            {{ $s->notes->count() }}
                                        </button>
                                    @endif
                                @elseif($s->status === 'approved')
                                    <span class="wf-pill approved">Approved</span>
                                @elseif($s->status === 'killed')
                                    <span class="wf-pill killed">Killed</span>
                                @else
                                    <span class="wf-pill archived">{{ ucfirst($s->status) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('admin.add-news', ['id' => $s->id]) }}" class="icon-btn" title="Edit story">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <button type="button" wire:click="deleteStory({{ $s->id }})" wire:confirm="Are you sure you want to delete this story?" class="icon-btn danger" title="Delete story">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-sm text-muted-2">No stories found. Create your first story.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination matching prototype -->
        @if($stories->hasPages())
            <div class="news-pagination">
                <button type="button" wire:click="previousPage" @disabled($stories->onFirstPage()) class="news-page-btn" title="Previous page">‹</button>

                @php
                    $currentPage = $stories->currentPage();
                    $lastPage = $stories->lastPage();
                    $start = max(1, $currentPage - 3);
                    $end = min($lastPage, $currentPage + 3);
                @endphp

                @if($start > 1)
                    <button type="button" wire:click="gotoPage(1)" class="news-page-btn">1</button>
                    @if($start > 2)
                        <span class="news-page-btn dots">…</span>
                    @endif
                @endif

                @for($page = $start; $page <= $end; $page++)
                    <button type="button" wire:click="gotoPage({{ $page }})" class="news-page-btn {{ $currentPage === $page ? 'active' : '' }}">
                        {{ $page }}
                    </button>
                @endfor

                @if($end < $lastPage)
                    @if($end < $lastPage - 1)
                        <span class="news-page-btn dots">…</span>
                    @endif
                    <button type="button" wire:click="gotoPage({{ $lastPage }})" class="news-page-btn">{{ $lastPage }}</button>
                @endif

                <button type="button" wire:click="nextPage" @disabled(!$stories->hasMorePages()) class="news-page-btn" title="Next page">›</button>
            </div>
        @endif

        <div class="results-info">
            Showing {{ $stories->firstItem() ?? 0 }}–{{ $stories->lastItem() ?? 0 }} of {{ number_format($stories->total()) }} stories · Page {{ $stories->currentPage() }} of {{ $stories->lastPage() }}
        </div>

    </div>

    <!-- Workflow Drawer (445px slide-over) -->
    <div class="wfd-overlay {{ $selected ? 'open' : '' }}" wire:click="closeDrawer"></div>
    <aside class="wfd {{ $selected ? 'open' : '' }}" id="wfd">
        @if($selected)
            <div class="wfd-head">
                <div class="wfd-title">{{ $selected->headline }}</div>
                <button type="button" wire:click="closeDrawer" class="wfd-close" title="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="wfd-body">
                @if($conflictError)
                    <div class="p-3 mb-3 bg-crimson-soft text-crimson-dark border border-crimson rounded-lg text-xs font-semibold">
                        {{ $conflictError }}
                    </div>
                @endif

                <!-- Workflow Stepper -->
                <div class="wfd-sec-label">Workflow</div>
                <div class="wfd-flow">
                    @php
                        $st = $selected->status;
                        $isNeedsWork = $st === 'changes_requested';
                        $isLive = $st === 'published';
                        $isApproved = $st === 'approved';
                        $isInReview = $st === 'in_review';
                        $isDraft = $st === 'draft';
                    @endphp

                    <span class="wfd-step {{ $isDraft ? 'now' : 'done' }}">Draft</span>
                    <span class="wfd-arrow">→</span>
                    <span class="wfd-step {{ $isInReview ? 'now' : ($isApproved || $isLive || $isNeedsWork ? 'done' : '') }}">In review</span>
                    <span class="wfd-arrow">→</span>

                    @if($isNeedsWork)
                        <span class="wfd-step now bad">Needs work</span>
                    @else
                        <span class="wfd-step {{ $isApproved ? 'now' : ($isLive ? 'done' : '') }}">Approved</span>
                    @endif

                    <span class="wfd-arrow">→</span>
                    <span class="wfd-step {{ $isLive ? 'now done' : '' }}">Published</span>
                </div>

                <!-- Owner Section -->
                <div class="wfd-sec-label">Owner</div>
                <div class="wfd-owner">
                    <span class="wf-ava">{{ strtoupper(substr($selected->owner?->name ?? 'UN', 0, 2)) }}</span>
                    <span class="wfd-owner-meta">
                        <b>{{ $selected->owner?->name ?? 'Unassigned' }}</b> ({{ $selected->owner?->role?->name ?? 'Newsroom' }})<br>
                        @if($selected->owner_id === auth()->id())
                            You took over this story
                        @else
                            on shift until 6:00 PM — another sub-editor can take over after that
                        @endif
                    </span>
                    @if($selected->owner_id !== auth()->id())
                        <button type="button" wire:click="takeOver" class="btn btn-outline" title="Take ownership — handover is audit-logged">Take over</button>
                    @endif
                </div>

                <!-- Internal Notes Thread -->
                <div class="wfd-sec-label">Internal notes</div>
                <div class="nt-list">
                    @forelse($selected->notes as $note)
                        @php
                            $kind = $note->kind;
                            $isSys = $kind === 'sys' || $kind === 'system';
                            $roleName = strtolower($note->user?->role?->name ?? '');
                            $isEditor = str_contains($roleName, 'editor') || str_contains($roleName, 'admin');
                            $itemClass = $isSys ? 'sys' : ($isEditor ? 'editor' : 'sub');
                        @endphp
                        <div class="nt-item {{ $itemClass }}">
                            @if($isSys)
                                <div class="nt-body">
                                    {{ $note->body }} — <b>{{ $note->created_at?->timezone('Asia/Dhaka')->format('g:i A') ?? 'Today' }}</b>
                                </div>
                            @else
                                <div class="nt-head">
                                    <b>{{ $note->user?->name ?? 'Staff' }}</b>
                                    <span class="nt-role {{ !$isEditor ? 'sub' : '' }}">{{ $note->user?->role?->name ?? 'Newsroom' }}</span>
                                    <span class="nt-time">{{ $note->created_at?->timezone('Asia/Dhaka')->format('D · g:i A') }}</span>
                                </div>
                                <div class="nt-body">{{ $note->body }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs text-muted italic p-2">No notes yet. Newsroom-only discussion appears here.</div>
                    @endforelse
                </div>

                <!-- Note Reply Composer -->
                <div class="nt-reply">
                    <textarea wire:model.live="noteText" rows="2" placeholder="Reply — visible to the newsroom only…"></textarea>
                    <button type="button" wire:click="addNote" class="btn btn-navy">Send</button>
                </div>
                @error('noteText')
                    <div class="text-[11px] text-crimson mt-1">{{ $message }}</div>
                @enderror

                <div class="wfd-hint">
                    Notes never leave the newsroom — not published, not sent to clients. Every status change and handover is recorded in the audit trail.
                </div>
            </div>
        @endif
    </aside>

    <x-toast />
</div>
