<div>
    {{-- Breadcrumb & Page Topbar --}}
    <div class="breadcrumb">
        <a href="{{ route('dashboard') }}">Home</a> &nbsp;/&nbsp; Packages &amp; add-ons
    </div>

    <div class="topbar">
        <h1>Packages &amp; add-ons</h1>
        <div class="topbar-actions">
            <button class="btn btn-outline" wire:click="openAoModal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New add-on
            </button>
            <button class="btn btn-primary" wire:click="openPkgModal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New package
            </button>
        </div>
    </div>

    {{-- 4-Stat Strip --}}
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-num" id="stPkgs">{{ $stats['live_packages'] }}</div>
            <div class="stat-label">Live packages</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" id="stAddons">{{ $stats['live_addons'] }}</div>
            <div class="stat-label">Add-ons live</div>
        </div>
        <div class="stat-card">
            <div class="stat-num navy" id="stClients">{{ $stats['clients_covered'] }}</div>
            <div class="stat-label">Clients covered</div>
        </div>
        <div class="stat-card">
            <div class="stat-num green" id="stMrr">{{ $stats['mrr'] }}</div>
            <div class="stat-label">Monthly recurring</div>
        </div>
    </div>

    {{-- SECTION 1: Subscription Packages --}}
    <div class="sec-head">
        <div class="sec-title">Subscription packages</div>
        <div class="sec-sub">What clients subscribe to — assign from the Clients page</div>
    </div>

    <div class="pkg-grid" id="pkgGrid">
        @foreach($packages as $p)
            @php $isArch = $p->status === 'archived'; @endphp
            <div class="pkg-card {{ $isArch ? 'archived' : '' }}" data-id="{{ $p->id }}" id="pkg-card-{{ $p->id }}">
                <div class="pkg-top {{ $p->grad }}">
                    <span class="pkg-status {{ $p->status === 'draft' ? 'draft' : '' }}">{{ $p->status }}</span>
                    <div class="pkg-name">{{ $p->name }}</div>
                    <div class="pkg-price"><b>৳{{ number_format($p->price) }}</b> <span>/ month</span></div>
                    @if($p->desc)
                        <div class="pkg-desc">{{ $p->desc }}</div>
                    @endif
                </div>

                <div class="pkg-body">
                    <div class="pkg-feats">
                        {{-- 1. Wire Access --}}
                        <div class="pkg-feat">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>{{ $p->wire }}</span>
                        </div>

                        {{-- 2. Photo Quota --}}
                        <div class="pkg-feat {{ $p->quota === 'No photos' ? 'no' : '' }}">
                            @if($p->quota === 'No photos')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                <span>No photo downloads</span>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>{{ $p->quota }}{{ $p->quota === 'Unlimited' ? ' photo downloads' : ' / month' }}</span>
                            @endif
                        </div>

                        {{-- 3. Video Clips --}}
                        <div class="pkg-feat {{ ! $p->video ? 'no' : '' }}">
                            @if($p->video)
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            @endif
                            <span>Video clips</span>
                        </div>

                        {{-- 4. Exclusive Stories --}}
                        <div class="pkg-feat {{ ! $p->excl ? 'no' : '' }}">
                            @if($p->excl)
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            @endif
                            <span>Exclusive stories</span>
                        </div>

                        {{-- 5. API Access --}}
                        <div class="pkg-feat {{ ! $p->api ? 'no' : '' }}">
                            @if($p->api)
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            @endif
                            <span>API access</span>
                        </div>

                        {{-- 6. Priority Support --}}
                        <div class="pkg-feat {{ ! $p->support ? 'no' : '' }}">
                            @if($p->support)
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            @endif
                            <span>Priority desk support</span>
                        </div>
                    </div>

                    {{-- Client Avatars & Count --}}
                    <div class="pkg-clients">
                        <span class="pk-avatars">
                            @foreach($p->clients->take(4) as $c)
                                <span class="pk-av {{ $c['grad'] }}" title="{{ $c['name'] }}">{{ $c['ini'] }}</span>
                            @endforeach
                        </span>
                        <span class="pk-count">
                            @if(count($p->clients) > 0)
                                <b>{{ count($p->clients) }}</b> client{{ count($p->clients) > 1 ? 's' : '' }}
                            @else
                                No clients yet
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="pkg-actions">
                    <button class="pk-btn" wire:click="openPkgModal({{ $p->id }})" title="Edit package">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Edit
                    </button>
                    <button class="pk-btn" wire:click="duplicatePkg({{ $p->id }})" title="Duplicate package">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Duplicate
                    </button>

                    @if($isArch)
                        <button class="pk-btn" wire:click="restorePkg({{ $p->id }})" title="Restore to live">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            Restore
                        </button>
                        <button class="pk-btn danger" wire:click="deletePkg({{ $p->id }})" title="Delete package">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Delete
                        </button>
                    @else
                        <button class="pk-btn danger" wire:click="openArchiveModal({{ $p->id }})" title="Archive package">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            Archive
                        </button>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Create New Package Card --}}
        <button class="pkg-new" wire:click="openPkgModal" id="pkgNewCard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create a new package
        </button>
    </div>

    {{-- SECTION 2: Add-ons --}}
    <div class="sec-head">
        <div class="sec-title">Add-ons</div>
        <div class="sec-sub">Optional extras sold on top of any package</div>
    </div>

    <div class="addon-list" id="addonList">
        <div class="ao-row head">
            <span>Add-on</span>
            <span class="ao-hide-m">Description</span>
            <span class="ao-hide-m">Available with</span>
            <span>Price</span>
            <span>Status</span>
            <span></span>
        </div>

        @foreach($addOns as $a)
            <div class="ao-row" data-id="{{ $a->id }}" id="addon-row-{{ $a->id }}">
                <span class="ao-name">
                    <span class="ao-dot {{ $a->grad }}"></span>
                    <span>{{ $a->name }}</span>
                    @if(count($a->clients) > 0)
                        <span style="font-weight:500;color:var(--muted-2);font-size:11px">· {{ count($a->clients) }} client{{ count($a->clients) > 1 ? 's' : '' }}</span>
                    @endif
                </span>

                <span class="ao-desc ao-hide-m">{{ $a->desc }}</span>
                <span class="ao-tiers ao-hide-m">{{ count($a->tiers) === 3 ? 'All packages' : implode(' + ', $a->tiers) }}</span>
                <span class="ao-price">৳{{ number_format($a->price) }}<span> /mo</span></span>

                <span class="ao-status {{ $a->status }}"
                      style="cursor:pointer"
                      title="Click to toggle live / draft"
                      wire:click="toggleAoStatus({{ $a->id }})"
                      id="ao-status-{{ $a->id }}">
                    {{ $a->status }}
                </span>

                <span class="ao-actions">
                    <button class="ao-btn" wire:click="openAoModal({{ $a->id }})" title="Edit add-on">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button class="ao-btn" wire:click="deleteAo({{ $a->id }})" title="Delete add-on">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ========================================== --}}
    {{-- MODAL 1: PACKAGE EDITOR (modal-xl)        --}}
    {{-- ========================================== --}}
    <div class="modal-overlay {{ $showPkgModal ? 'open' : '' }}" id="pkgOverlay">
        @if($showPkgModal)
            <div class="modal modal-xl">
                <div class="mo-head">
                    <div class="mo-title" id="pkgMoTitle">{{ $editingPkgId ? 'Edit — ' . $pkgName : 'New package' }}</div>
                    <button class="mo-close" wire:click="closePkgModal">✕</button>
                </div>

                <div class="mo-body">
                    <div class="ed-grid">
                        {{-- Left Column: Form Inputs --}}
                        <div>
                            <div class="field">
                                <label class="field-label">Package name <span class="req">*</span></label>
                                <input class="text-input" wire:model.live="pkgName" id="pName" placeholder="e.g. Premium Wire + Media">
                                @error('pkgName') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="ed-2col">
                                <div class="field">
                                    <label class="field-label">Price (৳ / month) <span class="req">*</span></label>
                                    <input class="text-input" wire:model.live="pkgPrice" id="pPrice" type="number" min="0" step="500" placeholder="45000">
                                    @error('pkgPrice') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="field-label">Card colour</label>
                                    <div class="color-dots" id="pColors">
                                        @foreach(['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'] as $g)
                                            <span class="color-dot {{ $g }} {{ $pkgGrad === $g ? 'sel' : '' }}"
                                                  wire:click="setPkgGrad('{{ $g }}')"
                                                  data-g="{{ $g }}"></span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="field">
                                <label class="field-label">Short description</label>
                                <textarea class="text-area" wire:model.live="pkgDesc" id="pDesc" placeholder="Who this package is for…"></textarea>
                            </div>

                            <div class="ed-2col">
                                <div class="field">
                                    <label class="field-label">Wire access</label>
                                    <select class="select-input" wire:model.live="pkgWire" id="pWire" style="width:100%">
                                        <option value="Full wire — all categories">Full wire — all categories</option>
                                        <option value="Full wire — excluding exclusives">Full wire — excluding exclusives</option>
                                        <option value="Headlines + briefs only">Headlines + briefs only</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Photo quota / month</label>
                                    <select class="select-input" wire:model.live="pkgQuota" id="pQuota" style="width:100%">
                                        <option value="Unlimited">Unlimited</option>
                                        <option value="2,000 photos">2,000 photos</option>
                                        <option value="800 photos">800 photos</option>
                                        <option value="200 photos">200 photos</option>
                                        <option value="No photos">No photos</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field" style="margin-bottom:6px">
                                <label class="field-label">Included</label>
                                <label class="feat-check">
                                    <input type="checkbox" wire:model.live="pkgVideo" id="pVideo"> Video clips
                                </label>
                                <label class="feat-check">
                                    <input type="checkbox" wire:model.live="pkgExcl" id="pExcl"> Exclusive stories
                                </label>
                                <label class="feat-check">
                                    <input type="checkbox" wire:model.live="pkgApi" id="pApi"> API access
                                </label>
                                <label class="feat-check" style="margin-bottom:0">
                                    <input type="checkbox" wire:model.live="pkgSupport" id="pSupport"> Priority desk support
                                </label>
                            </div>
                        </div>

                        {{-- Right Column: Synchronized Client View Preview --}}
                        <div class="ed-prev">
                            <div class="ed-prev-label"><span class="ed-prev-dot"></span> Client view preview</div>
                            <div class="prev-card">
                                <div class="prev-top {{ $pkgGrad }}" id="pvTop">
                                    <div class="prev-name" id="pvName">{{ $pkgName ?: 'Package name' }}</div>
                                    <div class="prev-price">
                                        <b id="pvPrice">৳{{ number_format((float) ($pkgPrice ?: 0)) }}</b>
                                        <span>/ month</span>
                                    </div>
                                </div>
                                <div class="prev-body" id="pvFeats">
                                    {{-- Wire Access --}}
                                    <div class="prev-feat">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>{{ $pkgWire }}</span>
                                    </div>

                                    {{-- Photo Quota --}}
                                    <div class="prev-feat {{ $pkgQuota === 'No photos' ? 'no' : '' }}">
                                        @if($pkgQuota === 'No photos')
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            <span>No photo downloads</span>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span>{{ $pkgQuota }}{{ $pkgQuota === 'Unlimited' ? ' photo downloads' : ' / month' }}</span>
                                        @endif
                                    </div>

                                    {{-- Video clips --}}
                                    <div class="prev-feat {{ ! $pkgVideo ? 'no' : '' }}">
                                        @if($pkgVideo)
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        @endif
                                        <span>Video clips</span>
                                    </div>

                                    {{-- Exclusives --}}
                                    <div class="prev-feat {{ ! $pkgExcl ? 'no' : '' }}">
                                        @if($pkgExcl)
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        @endif
                                        <span>Exclusive stories</span>
                                    </div>

                                    {{-- API Access --}}
                                    <div class="prev-feat {{ ! $pkgApi ? 'no' : '' }}">
                                        @if($pkgApi)
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        @endif
                                        <span>API access</span>
                                    </div>

                                    {{-- Priority Desk Support --}}
                                    <div class="prev-feat {{ ! $pkgSupport ? 'no' : '' }}">
                                        @if($pkgSupport)
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        @endif
                                        <span>Priority desk support</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mo-actions">
                    <button class="btn btn-outline" wire:click="closePkgModal">Cancel</button>
                    <button class="btn btn-outline" wire:click="savePkg('draft')" id="pkgSaveDraft">Save as draft</button>
                    <button class="btn btn-primary" wire:click="savePkg('live')" id="pkgSave">Save &amp; publish</button>
                </div>
            </div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- MODAL 2: ADD-ON EDITOR                     --}}
    {{-- ========================================== --}}
    <div class="modal-overlay {{ $showAoModal ? 'open' : '' }}" id="aoOverlay">
        @if($showAoModal)
            <div class="modal">
                <div class="mo-head">
                    <div class="mo-title" id="aoMoTitle">{{ $editingAoId ? 'Edit — ' . $aoName : 'New add-on' }}</div>
                    <button class="mo-close" wire:click="closeAoModal">✕</button>
                </div>

                <div class="mo-body">
                    <div class="field">
                        <label class="field-label">Add-on name <span class="req">*</span></label>
                        <input class="text-input" wire:model.live="aoName" id="aName" placeholder="e.g. AP World pack">
                        @error('aoName') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Price (৳ / month) <span class="req">*</span></label>
                        <input class="text-input" wire:model.live="aoPrice" id="aPrice" type="number" min="0" step="500" placeholder="30000">
                        @error('aoPrice') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Description</label>
                        <textarea class="text-area" wire:model.live="aoDesc" id="aDesc" placeholder="What the client gets…"></textarea>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field-label">Available with</label>
                        <label class="feat-check">
                            <input type="checkbox"
                                   wire:click="toggleAoTier('Premium')"
                                   {{ in_array('Premium', $aoTiers, true) ? 'checked' : '' }}
                                   class="aTier" value="Premium"> Premium packages
                        </label>
                        <label class="feat-check">
                            <input type="checkbox"
                                   wire:click="toggleAoTier('Standard')"
                                   {{ in_array('Standard', $aoTiers, true) ? 'checked' : '' }}
                                   class="aTier" value="Standard"> Standard packages
                        </label>
                        <label class="feat-check" style="margin-bottom:0">
                            <input type="checkbox"
                                   wire:click="toggleAoTier('Basic')"
                                   {{ in_array('Basic', $aoTiers, true) ? 'checked' : '' }}
                                   class="aTier" value="Basic"> Basic packages
                        </label>
                        @error('aoTiers') <span class="text-xs text-crimson mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mo-actions">
                    <button class="btn btn-outline" wire:click="closeAoModal">Cancel</button>
                    <button class="btn btn-primary" wire:click="saveAo" id="aoSave">Save add-on</button>
                </div>
            </div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- MODAL 3: ARCHIVE & REASSIGN               --}}
    {{-- ========================================== --}}
    <div class="modal-overlay {{ $showArchiveModal ? 'open' : '' }}" id="archOverlay">
        @if($showArchiveModal && $archiveTargetPkg)
            <div class="modal">
                <div class="mo-head">
                    <div class="mo-title" id="archTitle">Archive — {{ $archiveTargetPkg->name }}</div>
                    <button class="mo-close" wire:click="closeArchiveModal">✕</button>
                </div>

                <div class="mo-body" id="archBody">
                    @if(count($archiveTargetPkg->clients) > 0)
                        <div class="warn-box">
                            <b>{{ count($archiveTargetPkg->clients) }} client{{ count($archiveTargetPkg->clients) > 1 ? 's are' : ' is' }} on this package:</b>
                            {{ implode(', ', array_column($archiveTargetPkg->clients->toArray(), 'name')) }}. Reassign them before archiving.
                        </div>

                        <div class="field" style="margin-bottom:0">
                            <label class="field-label">Move clients to</label>
                            <select class="select-input" wire:model="reassignPkgId" id="archReassign" style="width:100%">
                                @foreach($reassignOptions as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->name }} — ৳{{ number_format($opt->price) }}/mo</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="field-hint">
                            No clients are on this package. Archiving removes it from assignment lists — history stays in client records.
                        </div>
                    @endif
                </div>

                <div class="mo-actions">
                    <button class="btn btn-outline" wire:click="closeArchiveModal">Cancel</button>
                    <button class="btn btn-primary" wire:click="confirmArchive" id="archConfirm">Archive</button>
                </div>
            </div>
        @endif
    </div>
</div>
