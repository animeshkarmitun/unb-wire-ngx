<div x-data="{
    dragDepth: 0,
    zipping: false,
    toastMsg: '',
    toastTimer: null,
    showToast(msg) {
        this.toastMsg = msg;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toastMsg = ''; }, 2600);
    },
    async downloadZip(selectedAssets) {
        if (!selectedAssets || selectedAssets.length === 0) return;
        this.zipping = true;
        try {
            if (typeof JSZip === 'undefined') {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }
            const zip = new JSZip();
            for (let i = 0; i < selectedAssets.length; i++) {
                const a = selectedAssets[i];
                const canvas = document.createElement('canvas');
                canvas.width = 1200; canvas.height = 800;
                const ctx = canvas.getContext('2d');
                const cols = [['#3b6fe0','#16204a'],['#e5484d','#7a1f2b'],['#16a34a','#0b3d24'],['#f0a832','#8a5410'],['#7c3aed','#2e1065'],['#0ea5e9','#0c4a6e'],['#db2777','#831843'],['#64748b','#1e293b']][i % 8];
                const g = ctx.createLinearGradient(0, 0, 1200, 800);
                g.addColorStop(0, cols[0]); g.addColorStop(1, cols[1]);
                ctx.fillStyle = g; ctx.fillRect(0, 0, 1200, 800);
                const blob = await new Promise(res => canvas.toBlob(res, 'image/png'));
                zip.file((a.id || ('p' + (i+1))) + '.png', blob);
            }
            const captionText = selectedAssets.map(a => (a.caption || a.title) + '\nPhoto: ' + (a.photographer || 'UNB') + ' / UNB · ' + (a.location || 'Dhaka')).join('\n\n');
            zip.file('captions.txt', captionText);
            const content = await zip.generateAsync({ type: 'blob' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(content);
            link.download = 'unb-photos-' + selectedAssets.length + '.zip';
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 4000);
            this.showToast('✓ ZIP downloaded (' + selectedAssets.length + ' photos)');
        } catch (e) {
            console.error('ZIP failed', e);
            this.showToast('ZIP generation failed');
        } finally {
            this.zipping = false;
        }
    }
}"
@toast.window="showToast($event.detail.message)"
@dragenter.window="dragDepth++;"
@dragleave.window="dragDepth = Math.max(0, dragDepth - 1);"
@dragover.prevent
@drop.prevent="dragDepth = 0; if ($event.dataTransfer.files.length) { $refs.uploadInput.files = $event.dataTransfer.files; $refs.uploadInput.dispatchEvent(new Event('change', { bubbles: true })); }"
class="relative">

    <!-- Drag & drop full-screen overlay -->
    <div x-show="dragDepth > 0" x-cloak class="dam-drop show">
        <div class="dam-drop-box">
            Drop photos to upload
            <span>They will land in “Needs review” for the photo editor</span>
        </div>
    </div>

    <!-- Hidden file upload input -->
    <input type="file" x-ref="uploadInput" wire:model="uploads" multiple accept="image/*" class="hidden">

    <!-- Breadcrumb & Topbar -->
    <div class="breadcrumb">
        <a href="{{ route('dashboard') }}">Home</a> &nbsp;/&nbsp; UNB Photo Manager
    </div>

    <div class="topbar">
        <h1>UNB Photo Manager</h1>
        <div class="topbar-actions">
            <button wire:click="toggleUnattached" class="btn {{ $unattachedOnly ? 'btn-navy' : 'btn-outline' }}" id="unattachedBtn" aria-label="Toggle unattached photos filter">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
                Unattached only
            </button>
            <button @click="$refs.uploadInput.click()" class="btn btn-primary" id="uploadBtn" aria-label="Upload photos">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span wire:loading.remove wire:target="uploads">Upload photos</span>
                <span wire:loading wire:target="uploads">Uploading…</span>
            </button>
        </div>
    </div>

    <!-- 7 Workflow Tabs with alert counts -->
    <div class="wf-tabs" id="wfTabs">
        @php
            $tabDefs = [
                'all' => 'All assets',
                'field' => '📥 Field intake',
                'review' => 'Needs review',
                'library' => 'In library',
                'packaged' => 'Packaged',
                'published' => 'Published',
                'embargo' => 'Embargoed',
            ];
        @endphp
        @foreach($tabDefs as $key => $label)
            <button wire:click="setTab('{{ $key }}')" class="wf-tab {{ $tab === $key ? 'active' : '' }}" data-st="{{ $key }}" aria-label="Filter by {{ $label }}">
                {{ $label }}
                <span class="n {{ (($key === 'review' || $key === 'field') && ($counts[$key] ?? 0) > 0) ? 'alert' : '' }}">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </button>
        @endforeach
    </div>

    <!-- Filters Toolbar -->
    <div class="toolbar">
        <input wire:model.live.debounce.300ms="search" class="search-input" type="text" id="damSearch" placeholder="Search captions, photographers, locations, keywords…">
        
        <select wire:model.live="photographer" class="filter-select" id="damBy">
            <option value="all">All photographers</option>
            @foreach($photographersList as $pName)
                <option value="{{ $pName }}">{{ $pName }}</option>
            @endforeach
        </select>

        <select wire:model.live="category" class="filter-select" id="damCat">
            <option value="all">All categories</option>
            @foreach($categoriesList as $cName)
                <option value="{{ $cName }}">{{ $cName }}</option>
            @endforeach
        </select>

        <select wire:model.live="sort" class="filter-select" id="damSort">
            <option value="new">Newest first</option>
            <option value="dl">Most downloaded</option>
        </select>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulkbar {{ count($selectedIds) > 0 ? 'show' : '' }}" id="bulkbar">
        <span class="bulk-count" id="bulkCount">{{ count($selectedIds) }} selected</span>
        
        <button wire:click="bulkApprove" class="bulk-btn accent" id="bulkApprove" aria-label="Approve selected photos and move to library">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Approve → library
        </button>

        <select wire:change="bulkAssignPackage($event.target.value); $event.target.value='';" class="bulk-sel" id="bulkPkg">
            <option value="">Assign package…</option>
            <option value="Standard">Standard</option>
            <option value="Exclusive">Exclusive</option>
        </select>

        @php
            $selectedAssetsJson = [];
            if ($assets && count($selectedIds) > 0) {
                foreach ($assets as $asset) {
                    if (in_array($asset->id, $selectedIds)) {
                        $selectedAssetsJson[] = [
                            'id' => 'p' . $asset->id,
                            'title' => $asset->title,
                            'caption' => $asset->caption ?: $asset->title,
                            'photographer' => $asset->photographer?->name ?: str_replace(['Photo: ', ' / UNB'], '', $asset->credit_line),
                            'location' => $asset->location_city ?: 'Dhaka',
                        ];
                    }
                }
            }
        @endphp

        <button @click="downloadZip({{ json_encode($selectedAssetsJson) }})" class="bulk-btn" id="bulkZip" aria-label="Download selected photos as ZIP">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <span x-text="zipping ? 'Zipping…' : 'ZIP'"></span>
        </button>

        <button wire:click="clearSelection" class="bulk-clear" id="bulkClear" aria-label="Clear photo selection">Clear</button>
    </div>

    <!-- Main Grid + Sticky Inspector Area -->
    <div class="dam-layout {{ $selectedAssetId ? 'insp' : '' }}" id="damLayout">

        @if($tab === 'field')
            <!-- ============ Field Intake Queue View ============ -->
            <div class="w-full">
                @if(!$fieldBatches || $fieldBatches->isEmpty())
                    <div class="fq-empty">
                        <b>Queue is clear ✓</b>
                        New batches from field photographers will land here for review.
                    </div>
                @else
                    @php
                        $totalFieldPhotos = $fieldBatches->sum(fn($b) => $b->assets->count());
                    @endphp
                    <div class="fq-summary">
                        📥 <b>{{ $totalFieldPhotos }} photos</b> in <b>{{ $fieldBatches->count() }} batch{{ $fieldBatches->count() > 1 ? 'es' : '' }}</b> waiting for desk review
                        <span class="fq-live"><i></i>Watching for new uploads</span>
                    </div>

                    @foreach($fieldBatches as $batch)
                        @php
                            $uploaderName = $batch->uploader?->name ?? 'Photographer';
                            $initials = collect(explode(' ', $uploaderName))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join('');
                            $waitMinutes = $batch->submitted_at ? max(1, $batch->submitted_at->diffInMinutes(now())) : 10;
                            $waitStr = $waitMinutes >= 60 ? round($waitMinutes / 60) . ' hr' : $waitMinutes . ' min';
                            $urgencyClass = strtolower($batch->urgency) === 'urgent' ? 'urgent' : (str_contains(strtolower($batch->event_label), 'breaking') ? 'breaking' : 'normal');
                        @endphp
                        <div class="fq-batch">
                            <div class="fq-bhead">
                                <div class="fq-av">{{ strtoupper($initials) }}</div>
                                <div class="fq-who">
                                    <b>{{ $uploaderName }}</b>
                                    <span>{{ $batch->assets->first()?->location_city ?: 'Dhaka' }} · received {{ $waitStr }} ago</span>
                                </div>
                                <span class="fq-event">{{ $batch->event_label }}</span>
                                <span class="fq-urg {{ $urgencyClass }}">{{ $batch->urgency ?: 'routine' }}</span>
                                <span class="fq-wait">{{ $batch->assets->count() }} frame{{ $batch->assets->count() > 1 ? 's' : '' }}</span>
                            </div>

                            <div class="fq-photos">
                                @foreach($batch->assets as $asset)
                                    @php
                                        $grad = $asset->derivatives['grad'] ?? ('g' . (($asset->id % 8) + 1));
                                    @endphp
                                    <div class="fq-ph">
                                        <div class="fq-thumb {{ $grad }}">
                                            <svg class="ph" viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <path d="m21 15-5-5L5 21"/>
                                            </svg>
                                            <div class="fq-pacts">
                                                <button wire:click="approveFieldAsset({{ $asset->id }})" class="fq-pact ok" title="Approve to library">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="20 6 9 17 4 12"/>
                                                    </svg>
                                                </button>
                                                <button wire:click="openModal('reject_photo', null, {{ $asset->id }})" class="fq-pact no" title="Reject with reason">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="fq-cap">
                                            {{ $asset->caption ?: $asset->title }}
                                            <small>Photo: {{ $uploaderName }} / UNB · {{ is_array($asset->en_tags) ? implode(', ', $asset->en_tags) : ($asset->event_label ?: 'general') }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="fq-bfoot">
                                <button wire:click="approveFieldBatch({{ $batch->id }})" class="fq-btn ok">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Approve all {{ $batch->assets->count() }}
                                </button>
                                <button wire:click="openModal('reedit_batch', {{ $batch->id }})" class="fq-btn warn">
                                    ↩ Request re-edit…
                                </button>
                                <button wire:click="openModal('reject_batch', {{ $batch->id }})" class="fq-btn danger">
                                    ✕ Reject batch…
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

        @else
            <!-- ============ Justified Flex Grid View ============ -->
            <div class="dam-grid" id="damGrid">
                @if(!$assets || $assets->isEmpty())
                    <div class="dam-empty">
                        No assets match — adjust filters or drop photos to upload.
                    </div>
                @else
                    @foreach($assets as $asset)
                        @php
                            $r = $asset->derivatives['r'] ?? (
                                ($asset->width && $asset->height)
                                    ? round($asset->width / $asset->height, 2)
                                    : 1.5
                            );
                            $grad = $asset->derivatives['grad'] ?? ('g' . (($asset->id % 8) + 1));
                            $isSelected = in_array($asset->id, $selectedIds, true);
                            $stackCount = $asset->derivatives['stack'] ?? null;
                            $isStackCover = $stackCount && $stackCount > 0;
                            $isExpanded = $expandedStackId === $asset->id;

                            // Computed status label & color
                            if ($asset->embargo_until && $asset->embargo_until->isFuture()) {
                                $statusLabel = 'Embargoed';
                                $statusColor = '#16204a';
                            } elseif ($asset->stories->where('status', 'published')->count() > 0) {
                                $statusLabel = 'Published';
                                $statusColor = '#16a34a';
                            } elseif ($asset->packages->count() > 0) {
                                $statusLabel = 'Packaged';
                                $statusColor = '#7c3aed';
                            } elseif ($asset->status === 'field' || !$asset->approved_at) {
                                $statusLabel = 'Needs review';
                                $statusColor = '#f0a832';
                            } else {
                                $statusLabel = 'In library';
                                $statusColor = '#3b6fe0';
                            }
                        @endphp

                        <!-- Main Item -->
                        <div wire:click="selectAsset({{ $asset->id }})"
                             class="dam-item {{ $isSelected ? 'selected' : '' }}"
                             style="--r: {{ $r }};"
                             title="{{ $asset->caption ?: $asset->title }}">
                            
                            <div class="dam-thumb {{ $grad }}">
                                <svg class="ph" viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="m21 15-5-5L5 21"/>
                                </svg>
                            </div>

                            <input type="checkbox"
                                   @checked($isSelected)
                                   wire:click.stop="toggleSelect({{ $asset->id }})"
                                   class="dam-check">

                            @if($isStackCover)
                                <button wire:click.stop="toggleStack({{ $asset->id }})"
                                        class="dam-stack-badge"
                                        style="{{ $isExpanded ? 'top:auto;bottom:8px;' : '' }}">
                                    {{ $isExpanded ? 'Collapse ▴' : '▤ +' . $stackCount }}
                                </button>
                            @else
                                <span class="dam-status" style="background: {{ $statusColor }};">
                                    {{ $statusLabel }}
                                </span>
                            @endif

                            @if($asset->kind === 'video')
                                <span class="dam-vid">
                                    <svg viewBox="0 0 24 24"><polygon points="7 4 20 12 7 20 7 4"/></svg>
                                    {{ $asset->duration_ms ? sprintf('%02d:%02d', floor($asset->duration_ms / 60000), floor(($asset->duration_ms % 60000) / 1000)) : '00:48' }}
                                </span>
                            @endif

                            <div class="dam-overlay">
                                {{ $asset->caption ?: $asset->title }}
                            </div>
                        </div>

                        <!-- Expanded Burst Stack Child Frames -->
                        @if($isExpanded && $stackCount)
                            @for($frame = 2; $frame <= min(12, $stackCount + 1); $frame++)
                                @php
                                    $childR = [1.5, 1.33, 1.78, 1.5, 0.8][($frame - 1) % 5];
                                    $childGrad = 'g' . (($frame % 8) + 1);
                                @endphp
                                <div wire:click.stop="setStackCover({{ $asset->id }}, {{ $frame }})"
                                     class="dam-item in-stack"
                                     style="--r: {{ $childR }};"
                                     title="{{ $asset->caption }} — frame {{ $frame }} (Click to set as cover)">
                                    <div class="dam-thumb {{ $childGrad }}">
                                        <svg class="ph" viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    </div>
                                    <span class="dam-status" style="background: rgba(15,23,48,0.75);">
                                        Frame {{ $frame }}
                                    </span>
                                    <div class="dam-overlay">
                                        {{ $asset->caption }} — frame {{ $frame }} (Click to set as cover)
                                    </div>
                                </div>
                            @endfor
                        @endif

                    @endforeach
                @endif
            </div>

            <!-- Custom Pagination -->
            @if($assets && $assets->hasPages())
                <div class="mt-4">
                    {{ $assets->links() }}
                </div>
            @endif
        @endif

        <!-- ============ Sticky Inspector Panel ============ -->
        @if($selected)
            @php
                $selGrad = $selected->derivatives['grad'] ?? ('g' . (($selected->id % 8) + 1));
                if ($selected->embargo_until && $selected->embargo_until->isFuture()) {
                    $selStatusLabel = 'Embargoed';
                    $selStatusColor = '#16204a';
                } elseif ($selected->stories->where('status', 'published')->count() > 0) {
                    $selStatusLabel = 'Published';
                    $selStatusColor = '#16a34a';
                } elseif ($selected->packages->count() > 0) {
                    $selStatusLabel = 'Packaged';
                    $selStatusColor = '#7c3aed';
                } elseif ($selected->status === 'field' || !$selected->approved_at) {
                    $selStatusLabel = 'Needs review';
                    $selStatusColor = '#f0a832';
                } else {
                    $selStatusLabel = 'In library';
                    $selStatusColor = '#3b6fe0';
                }
            @endphp
            <aside class="insp-panel" id="inspPanel">
                <div class="insp-head">
                    <div class="insp-title">Asset details</div>
                    <button wire:click="closeInspector" class="insp-close" id="inspClose">✕</button>
                </div>

                <div class="insp-preview {{ $selGrad }}">
                    <svg class="ph" viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="m21 15-5-5L5 21"/>
                    </svg>
                </div>

                <div class="insp-body">
                    <div class="insp-status-row">
                        <span class="status-pill" style="background: {{ $selStatusColor }};">
                            {{ $selStatusLabel }}
                        </span>
                        @if($selected->embargo_until && $selected->embargo_until->isFuture())
                            <span style="font-size:11.5px;color:var(--muted)">
                                ⏱ lifts {{ $selected->embargo_until->format('M j, g:i A') }}
                            </span>
                        @endif
                    </div>

                    <div class="f-group">
                        <label class="f-label">Caption</label>
                        <textarea wire:model.defer="inspCaption" class="f-ta" id="iCap"></textarea>
                    </div>

                    <div class="f-row">
                        <div class="f-group">
                            <label class="f-label">Photographer</label>
                            <input wire:model.defer="inspPhotographer" class="f-inp" id="iBy">
                        </div>
                        <div class="f-group">
                            <label class="f-label">Location</label>
                            <input wire:model.defer="inspLocation" class="f-inp" id="iLoc">
                        </div>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Keywords (IPTC)</label>
                        <input wire:model.defer="inspKeywords" class="f-inp" id="iKw">
                    </div>

                    <div class="f-group">
                        <label class="f-label">Package</label>
                        <select wire:model.defer="inspPackage" class="f-sel" id="iPkg">
                            <option value="—">—</option>
                            <option value="Standard">Standard</option>
                            <option value="Exclusive">Exclusive</option>
                        </select>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Attached story</label>
                        @if($selected->stories->isNotEmpty())
                            @foreach($selected->stories as $story)
                                <a class="story-link" href="{{ route('admin.news', 'en') }}">
                                    📰 {{ $story->headline }}
                                </a>
                            @endforeach
                        @else
                            <div class="story-none">Not attached to any story yet</div>
                            <div style="display:flex;gap:7px;">
                                <input wire:model.defer="inspStoryInput" class="f-inp" id="iStory" placeholder="Paste story title or ID…">
                                <button wire:click="linkAssetStory" class="btn btn-outline sm" id="iLink">Link</button>
                            </div>
                        @endif
                    </div>

                    <div class="f-group">
                        <label class="f-label">Client usage</label>
                        <div class="usage-box">
                            <div class="usage-total">
                                {{ $selected->download_count }} downloads <span>all time</span>
                            </div>
                            @php
                                $clientsList = [
                                    ['Daily Star', round($selected->download_count * 0.4)],
                                    ['Prothom Alo', round($selected->download_count * 0.3)],
                                    ['bdnews24', round($selected->download_count * 0.2)],
                                ];
                            @endphp
                            @if($selected->download_count > 0)
                                @foreach($clientsList as $c)
                                    @if($c[1] > 0)
                                        <div class="client-row">
                                            <span>{{ $c[0] }}</span>
                                            <b>{{ $c[1] }}</b>
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                <div style="font-size:12px;color:var(--muted-2)">No client downloads yet</div>
                            @endif
                        </div>
                    </div>

                    <div class="insp-actions">
                        @if($selected->status === 'field' || !$selected->approved_at)
                            <button wire:click="approveInspected" class="btn btn-navy" id="iApprove">
                                ✓ Approve → library
                            </button>
                        @endif
                        <button wire:click="saveAssetMetadata" class="btn btn-primary" id="iSave">
                            Save changes
                        </button>
                    </div>
                </div>
            </aside>
        @endif

    </div>

    <!-- ============ 460px Field Intake Decision Modal ============ -->
    @if($modalOpen)
        <div class="fq-overlay open" id="fqOverlay">
            <div class="fq-modal">
                <div class="fq-mhead">
                    <div class="fq-mtitle" id="fqTitle">{{ $modalTitle }}</div>
                    <button wire:click="closeModal" class="insp-close" id="fqClose">✕</button>
                </div>
                <div class="fq-mbody">
                    <div id="fqReasons">
                        @php
                            $reasons = str_contains($modalType, 'reedit') ? [
                                'Captions need names / places filled in',
                                'Crop tighter on the lead frames',
                                'Exposure or color needs correction',
                                'Remove banner / watermark visible in frame',
                            ] : [
                                'Out of focus / soft at full size',
                                'Duplicate of frames already in the library',
                                'Weak composition — not publishable',
                                'Caption inaccurate or names missing',
                            ];
                        @endphp
                        @foreach($reasons as $idx => $r)
                            <label class="fq-reason {{ $selectedReason === $r ? 'sel' : '' }}">
                                <input type="radio" wire:model.live="selectedReason" name="fqReason" value="{{ $r }}">
                                <span>{{ $r }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="f-group" style="margin-bottom:0;margin-top:14px;">
                        <label class="f-label">Note to photographer (optional)</label>
                        <textarea wire:model.defer="reasonNote" class="f-ta" id="fqNote" placeholder="Anything specific to fix or know…"></textarea>
                    </div>
                </div>
                <div class="fq-mfoot">
                    <button wire:click="closeModal" class="btn btn-outline" id="fqCancel">Cancel</button>
                    <button wire:click="confirmModalAction" class="btn btn-primary" id="fqConfirm">Confirm</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Toast Component -->
    <div x-show="toastMsg" x-cloak class="toast-msg show" x-text="toastMsg"></div>

</div>
