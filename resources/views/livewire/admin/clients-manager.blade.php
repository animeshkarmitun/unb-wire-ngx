<div>
  <div class="breadcrumb"><a href="{{ route('dashboard') }}">Home</a> &nbsp;/&nbsp; Clients</div>

  <div class="topbar">
    <h1 class="font-serif text-[30px] font-semibold tracking-tight">Clients</h1>
    <div class="topbar-actions">
      <button class="btn btn-outline" wire:click="exportCsv(false)" title="Export filtered clients to CSV">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </button>
      <button class="btn btn-primary" wire:click="openOnboard">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Onboard client
      </button>
    </div>
  </div>

  {{-- 5-Stat Strip --}}
  <div class="stat-strip">
    <div class="stat-card">
      <div class="stat-num" id="stTotal">{{ $stats['total'] }}</div>
      <div class="stat-label">Total clients</div>
    </div>
    <div class="stat-card">
      <div class="stat-num green" id="stActive">{{ $stats['active'] }}</div>
      <div class="stat-label">Active subscriptions</div>
    </div>
    <div class="stat-card">
      <div class="stat-num amber" id="stPaused">{{ $stats['paused'] }}</div>
      <div class="stat-label">Paused</div>
    </div>
    <div class="stat-card">
      <div class="stat-num amber" id="stRenew">{{ $stats['renew'] }}</div>
      <div class="stat-label">Renewals due ≤ 45 days</div>
    </div>
    <div class="stat-card">
      <div class="stat-num red" id="stIssues">{{ $stats['issues'] }}</div>
      <div class="stat-label">Delivery issues</div>
    </div>
  </div>

  {{-- Toolbar --}}
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" wire:model.live.debounce.300ms="search" id="clSearch" placeholder="Search client, city, contact…">
    </div>
    <div class="st-chips" id="statusChips">
      @foreach(['all' => 'All', 'active' => 'Active', 'paused' => 'Paused', 'deactivated' => 'Deactivated'] as $stKey => $stLabel)
        <button type="button" wire:click="setStatusFilter('{{ $stKey }}')" class="st-chip {{ $statusFilter === $stKey ? 'active' : '' }}" data-st="{{ $stKey }}">
          {{ $stLabel }}
        </button>
      @endforeach
    </div>
    <select class="select-input" wire:model.live="tierFilter" id="tierSel">
      <option value="all">All tiers</option>
      <option value="Premium">Premium</option>
      <option value="Standard">Standard</option>
      <option value="Basic">Basic</option>
    </select>
    <select class="select-input" wire:model.live="sort" id="sortSel">
      <option value="name">Sort: Name</option>
      <option value="renewal">Sort: Renewal date</option>
      <option value="usage">Sort: Usage</option>
    </select>
  </div>

  {{-- Sticky Navy Bulk Bar --}}
  <div class="bulk-bar {{ count($selectedIds) > 0 ? 'show' : '' }}" id="bulkBar">
    <span class="bulk-count" id="bulkCount">{{ count($selectedIds) }} selected</span>
    <button type="button" class="bulk-btn" wire:click="bulkPause" id="bulkPause">Pause selected</button>
    <select class="bulk-select" wire:model="bulkPackage" id="bulkPkg">
      <option value="">Change package…</option>
      <option value="Premium Wire + Media">Premium Wire + Media</option>
      <option value="Standard Wire">Standard Wire</option>
      <option value="Basic Headlines">Basic Headlines</option>
    </select>
    <button type="button" class="bulk-btn" wire:click="bulkApplyPackage" id="bulkPkgApply">Apply</button>
    <button type="button" class="bulk-btn" wire:click="exportCsv(true)" id="bulkCsv">Export selected</button>
    <button type="button" class="bulk-clear" wire:click="clearSelection" id="bulkClear">Clear</button>
  </div>

  {{-- Client List Table --}}
  <div class="cl-list" id="clList">
    <div class="cl-row head">
      <span class="cl-check">
        @php
          $allVisibleIds = $clients->pluck('id')->toArray();
          $allSelected = count($allVisibleIds) > 0 && empty(array_diff($allVisibleIds, $selectedIds));
        @endphp
        <input type="checkbox"
          {{ $allSelected ? 'checked' : '' }}
          wire:change="toggleSelectAll($event.target.checked, {{ json_encode($allVisibleIds) }})"
          title="Select all"
        >
      </span>
      <span></span>
      <span>Client</span>
      <span class="cl-hide-m">Channels</span>
      <span class="cl-hide-m">Package</span>
      <span class="cl-hide-m">Usage this cycle</span>
      <span>Status</span>
      <span></span>
    </div>

    @forelse($clients as $c)
      @php
        $meta = $this->getClientMeta($c);
        $tier = $meta['tier'] ?? 'Standard';
        $pkgName = $c->clientPackages->first()?->package?->name ?? ($meta['package_name'] ?? 'Standard Wire');
        $uiStatus = $c->status === 'suspended' ? 'paused' : ($c->status === 'closed' ? 'deactivated' : 'active');
        $issue = $this->clientHasIssue($c);
        $dl = $meta['usage']['dl'] ?? 0;
        $quota = max(1, $meta['usage']['quota'] ?? 800);
        $pct = min(100, (int) round(($dl / $quota) * 100));
        $isSelected = in_array($c->id, $selectedIds);

        // Channels health evaluation
        $portalOn = $c->status === 'active';
        $emailMeta = $meta['channels']['email'] ?? ['on' => true, 'health' => 'ok'];
        $ftpChan = $c->clientChannels->firstWhere('type', 'ftp');
        $ftpOn = $ftpChan && $ftpChan->status === 'active';
        $ftpHealth = $ftpChan ? ($ftpChan->failure_count > 0 || ($ftpChan->config['health'] ?? '') === 'fail' ? 'fail' : 'ok') : 'off';
        $apiChan = $c->clientChannels->firstWhere('type', 'api');
        $apiOn = $apiChan && $apiChan->status === 'active';
        $apiHealth = $apiChan ? ($apiChan->failure_count > 0 || ($apiChan->config['health'] ?? '') === 'fail' ? 'fail' : 'ok') : 'off';
      @endphp

      <div class="cl-row {{ $isSelected ? 'selected' : '' }}" wire:key="client-row-{{ $c->id }}" wire:click="selectClient({{ $c->id }})" data-id="{{ $c->id }}">
        <span class="cl-check" wire:click.stop>
          <input type="checkbox"
            data-check
            {{ $isSelected ? 'checked' : '' }}
            wire:click.stop="toggleSelect({{ $c->id }})"
          >
        </span>
        <span class="cl-logo {{ $meta['grad'] ?? 'g1' }}">{{ $meta['ini'] ?? strtoupper(substr($c->name, 0, 2)) }}</span>
        <span>
          <span class="cl-name">
            <span class="nm">{{ $c->name }}</span>
            @if($issue)
              <span class="chan-health fail">Delivery issue</span>
            @endif
          </span>
          <span class="cl-type">{{ $meta['display_type'] ?? $c->type }} · {{ $meta['city'] ?? 'Dhaka' }}</span>
        </span>
        <span class="cl-chs cl-hide-m">
          {{-- Portal --}}
          <span class="ch-ic {{ $portalOn ? 'on' : '' }}" title="Portal — {{ $portalOn ? 'on' : 'off' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span class="ch-dot"></span>
          </span>
          {{-- Email --}}
          <span class="ch-ic {{ ($emailMeta['on'] ?? false) ? 'on' : '' }}" title="Email — {{ ($emailMeta['on'] ?? false) ? 'on' : 'off' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span class="ch-dot"></span>
          </span>
          {{-- FTP --}}
          <span class="ch-ic {{ $ftpHealth === 'fail' ? 'fail' : ($ftpOn ? 'on' : '') }}" title="FTP/SFTP — {{ $ftpHealth === 'fail' ? 'failing' : ($ftpOn ? 'on' : 'off') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span class="ch-dot"></span>
          </span>
          {{-- API --}}
          <span class="ch-ic {{ $apiHealth === 'fail' ? 'fail' : ($apiOn ? 'on' : '') }}" title="API — {{ $apiHealth === 'fail' ? 'failing' : ($apiOn ? 'on' : 'off') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            <span class="ch-dot"></span>
          </span>
        </span>
        <span class="cl-hide-m">
          <span class="cl-pkg">{{ $pkgName }}</span><br>
          <span class="cl-tier {{ $tier }}">{{ $tier }}</span>
        </span>
        <span class="cl-hide-m">
          <span class="cl-usage">{{ number_format($dl) }} / {{ number_format($quota) }} media</span>
          <span class="u-bar"><span class="u-fill" style="display:block;width:{{ $pct }}%{{ $pct > 85 ? ';background:var(--crimson, #e5484d)' : '' }}"></span></span>
        </span>
        <span class="cl-status {{ $uiStatus }}"><i></i>{{ ucfirst($uiStatus) }}</span>
        <span class="cl-chev">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
      </div>
    @empty
      <div class="cl-empty">No clients match — try clearing the search or status filter.</div>
    @endforelse
  </div>

  {{-- 500px Detail Drawer --}}
  <div class="drawer-overlay {{ $selected ? 'open' : '' }}" wire:click="closeDrawer" id="drawerOverlay"></div>
  <aside class="drawer {{ $selected ? 'open' : '' }}" id="drawer">
    @if($selected)
      @php
        $selMeta = $selectedMeta;
        $selTier = $selMeta['tier'] ?? 'Standard';
        $selPkg = $selected->clientPackages->first()?->package?->name ?? ($selMeta['package_name'] ?? 'Standard Wire');
        $selStatus = $selected->status === 'suspended' ? 'paused' : ($selected->status === 'closed' ? 'deactivated' : 'active');
        $selIssue = $this->clientHasIssue($selected);
        $selDl = $selMeta['usage']['dl'] ?? 0;
        $selQuota = max(1, $selMeta['usage']['quota'] ?? 800);
        $selPct = min(100, (int) round(($selDl / $selQuota) * 100));

        // Renewal due soon check
        $endsAt = $selected->clientPackages->first()?->ends_at;
        $diffDays = $endsAt ? (int) now()->diffInDays($endsAt, false) : 999;
        $renewDueSoon = $endsAt && $diffDays >= 0 && $diffDays <= 45 && $selected->status === 'active';

        // Channels for drawer
        $emData = $selMeta['channels']['email'] ?? ['on' => true, 'list' => [], 'health' => 'ok'];
        $ftChan = $selected->clientChannels->firstWhere('type', 'ftp');
        $ftOn = $ftChan && $ftChan->status === 'active';
        $ftFail = $ftChan && ($ftChan->failure_count > 0 || ($ftChan->config['health'] ?? '') === 'fail');
        $apChan = $selected->clientChannels->firstWhere('type', 'api');
        $apOn = $apChan && $apChan->status === 'active';
      @endphp

      <div class="dr-head">
        <div class="dr-top">
          <div class="dr-logo {{ $selMeta['grad'] ?? 'g1' }}" id="drLogo">{{ $selMeta['ini'] ?? strtoupper(substr($selected->name, 0, 2)) }}</div>
          <div>
            <div class="dr-name" id="drName">{{ $selected->name }}</div>
            <div class="dr-sub" id="drSub">
              <span class="cl-status {{ $selStatus }}"><i></i>{{ ucfirst($selStatus) }}</span>
              <span>{{ $selMeta['display_type'] ?? $selected->type }} · {{ $selMeta['city'] ?? 'Dhaka' }} · since {{ $selMeta['since'] ?? '2020' }}</span>
              @if($selIssue)
                <span class="chan-health fail">Delivery issue</span>
              @endif
            </div>
          </div>
          <button type="button" class="dr-close" wire:click="closeDrawer" id="drClose" title="Close" aria-label="Close client details">✕</button>
        </div>

        <div class="dr-actions" id="drActions">
          @if($selected->status === 'active')
            <button type="button" class="btn btn-outline btn-sm" wire:click="openPauseModal({{ $selected->id }})" data-dact="pause">Pause deliveries</button>
            <button type="button" class="btn btn-outline btn-sm" wire:click="openDeactModal({{ $selected->id }})" data-dact="deact" style="color:var(--crimson-dark, #d13438);border-color:#f0c9ca">Deactivate</button>
          @elseif($selected->status === 'suspended')
            <button type="button" class="btn btn-navy btn-sm" wire:click="resumeClient({{ $selected->id }})" data-dact="resume">Resume deliveries</button>
            <button type="button" class="btn btn-outline btn-sm" wire:click="openDeactModal({{ $selected->id }})" data-dact="deact" style="color:var(--crimson-dark, #d13438);border-color:#f0c9ca">Deactivate</button>
          @else
            <button type="button" class="btn btn-primary btn-sm" wire:click="reactivateClient({{ $selected->id }})" data-dact="reactivate">Reactivate client</button>
          @endif
        </div>

        <div class="dr-tabs" id="drTabs">
          <button type="button" wire:click="setDrawerTab('overview')" class="dr-tab {{ $drawerTab === 'overview' ? 'active' : '' }}" data-dtab="overview" aria-label="Client overview tab">Overview</button>
          <button type="button" wire:click="setDrawerTab('channels')" class="dr-tab {{ $drawerTab === 'channels' ? 'active' : '' }}" data-dtab="channels" aria-label="Client channels tab">Channels</button>
          <button type="button" wire:click="setDrawerTab('package')" class="dr-tab {{ $drawerTab === 'package' ? 'active' : '' }}" data-dtab="package" aria-label="Client package tab">Package</button>
          <button type="button" wire:click="setDrawerTab('activity')" class="dr-tab {{ $drawerTab === 'activity' ? 'active' : '' }}" data-dtab="activity" aria-label="Client activity tab">Activity</button>
          <button type="button" wire:click="setDrawerTab('portal-users')" class="dr-tab {{ $drawerTab === 'portal-users' ? 'active' : '' }}" data-dtab="portal-users" aria-label="Portal users tab">Portal Users</button>
        </div>
      </div>

      <div class="dr-body" id="drBody">
        {{-- Overview Tab --}}
        @if($drawerTab === 'overview')
          <div class="dr-sec">
            <div class="dr-sec-title">Contacts</div>
            @if(!empty($selMeta['contacts']))
              @foreach($selMeta['contacts'] as $con)
                <div class="dr-kv">
                  <span class="k">{{ $con['r'] ?? 'Desk' }}</span>
                  <span class="v">{{ $con['n'] ?? 'Contact' }} · <a href="mailto:{{ $con['e'] }}" style="color:var(--blue, #3b6fe0)">{{ $con['e'] }}</a></span>
                </div>
              @endforeach
            @elseif($selected->clientUsers->isNotEmpty())
              @foreach($selected->clientUsers as $u)
                <div class="dr-kv">
                  <span class="k">Desk</span>
                  <span class="v">{{ $u->name }} · <a href="mailto:{{ $u->email }}" style="color:var(--blue, #3b6fe0)">{{ $u->email }}</a></span>
                </div>
              @endforeach
            @else
              <div class="dr-kv"><span class="k">Billing</span><span class="v">{{ $selected->billing_email }}</span></div>
            @endif
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Entitlements</div>
            <div class="dr-kv"><span class="k">Package</span><span class="v">{{ $selPkg }}</span></div>
            <div class="dr-kv"><span class="k">Tier</span><span class="v"><span class="cl-tier {{ $selTier }}">{{ $selTier }}</span></span></div>
            <div class="dr-kv">
              <span class="k">Add-ons</span>
              <span class="v">
                <span class="addon-chips">
                  @if(!empty($selMeta['addons']))
                    @foreach($selMeta['addons'] as $add)
                      <span class="addon-chip">{{ $add }}</span>
                    @endforeach
                  @else
                    <span class="addon-chip none">None</span>
                  @endif
                </span>
              </span>
            </div>
            <div class="dr-kv">
              <span class="k">Renewal</span>
              <span class="v">
                {{ $endsAt ? $endsAt->format('j M Y') : '—' }}
                @if($renewDueSoon)
                  · <b style="color:#b7791f">due soon</b>
                @endif
              </span>
            </div>
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Usage this cycle</div>
            <div class="usage-grid">
              <div class="usage-box">
                <div class="usage-num">{{ number_format($selDl) }}</div>
                <div class="usage-label">Media downloads ({{ $selPct }}% of quota)</div>
              </div>
              <div class="usage-box">
                <div class="usage-num">{{ $selMeta['usage']['api'] ?? '—' }}</div>
                <div class="usage-label">API calls</div>
              </div>
              <div class="usage-box">
                <div class="usage-num" style="font-size:14px;padding-top:4px">{{ $selMeta['usage']['last'] ?? '1 hr ago' }}</div>
                <div class="usage-label">Last active</div>
              </div>
              <div class="usage-box">
                @php
                  $liveChanCount = ($selected->status === 'active' ? 1 : 0)
                    + (!empty($emData['on']) ? 1 : 0)
                    + ($ftOn ? 1 : 0)
                    + ($apOn ? 1 : 0);
                @endphp
                <div class="usage-num" style="font-size:14px;padding-top:4px">{{ $liveChanCount }} / 4</div>
                <div class="usage-label">Channels live</div>
              </div>
            </div>
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Internal note</div>
            <textarea class="note-area" wire:model="clientNoteText" id="noteArea" placeholder="Context for the newsroom — billing, preferences, escalation history…">{{ $clientNoteText }}</textarea>
            <div style="margin-top:9px;text-align:right">
              <button type="button" class="btn btn-outline btn-sm" wire:click="saveNote" id="noteSave">Save note</button>
            </div>
          </div>

        {{-- Channels Tab --}}
        @elseif($drawerTab === 'channels')
          {{-- 1. Portal --}}
          <div class="chan-row">
            <div class="chan-top">
              <span class="chan-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              </span>
              <span>
                <span class="chan-name">Client portal dashboard</span>
                <div class="chan-sub">Wire, media library, UNB Photos showcase</div>
              </span>
              <span class="chan-health {{ $selected->status === 'active' ? 'ok' : 'off' }}">
                {{ $selected->status === 'active' ? 'Always on' : 'Suspended' }}
              </span>
            </div>
          </div>

          {{-- 2. Email --}}
          @php $emOn = !empty($emData['on']); @endphp
          <div class="chan-row {{ $emOn ? 'open' : '' }}">
            <div class="chan-top">
              <span class="chan-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </span>
              <span>
                <span class="chan-name">Email alerts</span>
                <div class="chan-sub">
                  @php $emList = $emData['list'] ?? []; @endphp
                  {{ count($emList) ? count($emList) . ' recipient' . (count($emList) > 1 ? 's' : '') : 'No recipients yet' }}
                </div>
              </span>
              <span class="chan-health {{ $emOn ? 'ok' : 'off' }}">{{ $emOn ? 'Healthy' : 'Off' }}</span>
              <label class="switch">
                <input type="checkbox" wire:click="toggleChannel('email')" {{ $emOn ? 'checked' : '' }}>
                <span class="tr"></span>
              </label>
            </div>
            <div class="chan-body">
              <div class="mini-chips">
                @foreach($emList as $em)
                  <span class="mini-chip">
                    {{ $em }}
                    <button type="button" wire:click="removeEmailRecipient('{{ $em }}')" title="Remove email">✕</button>
                  </span>
                @endforeach
              </div>
              <div class="chip-input-row">
                <input class="chan-input" wire:model="newEmailRecipient" wire:keydown.enter="addEmailRecipient" id="emAdd" placeholder="Add recipient email…">
                <button type="button" class="key-btn" wire:click="addEmailRecipient" id="emAddBtn">Add</button>
              </div>
            </div>
          </div>

          {{-- 3. FTP --}}
          <div class="chan-row {{ $ftOn ? 'open' : '' }}">
            <div class="chan-top">
              <span class="chan-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              </span>
              <span>
                <span class="chan-name">FTP / SFTP auto-push</span>
                <div class="chan-sub">{{ $this->ftpHost ? $this->ftpHost . ' · port ' . $this->ftpPort : 'Not configured' }}</div>
              </span>
              <span class="chan-health {{ $ftFail ? 'fail' : ($ftOn ? 'ok' : 'off') }}">
                {{ $ftFail ? 'Failing' : ($ftOn ? 'Healthy' : 'Off') }}
              </span>
              <label class="switch">
                <input type="checkbox" wire:click="toggleChannel('ftp')" {{ $ftOn ? 'checked' : '' }}>
                <span class="tr"></span>
              </label>
            </div>
            <div class="chan-body">
              <div class="chan-3col">
                <div class="chan-field">
                  <label>Host server</label>
                  <input class="chan-input" wire:model="ftpHost" id="ftHost" placeholder="ftp.client.com">
                </div>
                <div class="chan-field">
                  <label>Username</label>
                  <input class="chan-input" wire:model="ftpUser" id="ftUser">
                </div>
                <div class="chan-field">
                  <label>Port</label>
                  <input class="chan-input" wire:model="ftpPort" id="ftPort">
                </div>
              </div>
              <div class="chan-field">
                <label>Password</label>
                <input class="chan-input" type="password" wire:model="ftpPass" id="ftPass">
              </div>
              <div style="display:flex;gap:8px;align-items:center">
                <button type="button" class="btn btn-outline btn-sm" wire:click="testFtpConnection" id="ftTest">Test connection</button>
                <button type="button" class="btn btn-navy btn-sm" wire:click="saveFtpCredentials" id="ftSave">Save credentials</button>
              </div>
              @if($ftFail)
                <div class="test-note" style="color:var(--crimson-dark, #d13438)">
                  ⚠ Last push failed — auth timeout. Fix credentials or ask client to whitelist UNB IPs.
                </div>
              @endif
            </div>
          </div>

          {{-- 4. API --}}
          <div class="chan-row {{ $apOn ? 'open' : '' }}">
            <div class="chan-top">
              <span class="chan-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
              </span>
              <span>
                <span class="chan-name">API access</span>
                <div class="chan-sub">{{ $apOn ? 'Live key · JSON feed + webhooks' : 'Not enabled' }}</div>
              </span>
              <span class="chan-health {{ $apOn ? 'ok' : 'off' }}">{{ $apOn ? 'Healthy' : 'Off' }}</span>
              <label class="switch">
                <input type="checkbox" wire:click="toggleChannel('api')" {{ $apOn ? 'checked' : '' }}>
                <span class="tr"></span>
              </label>
            </div>
            <div class="chan-body">
              <div class="chan-field">
                <label>API key</label>
                <div class="key-row">
                  <input class="chan-input" type="{{ $showApiKey ? 'text' : 'password' }}" readonly value="{{ $apiKey }}" placeholder="Generated on enable" id="apiKey">
                  <button type="button" class="key-btn" wire:click="toggleApiKeyReveal" id="apiReveal">{{ $showApiKey ? 'Hide' : 'Reveal' }}</button>
                  <button type="button" class="key-btn" wire:click="regenerateApiKey" id="apiRegen">{{ $confirmRegen ? 'Sure? Old key dies' : 'Regenerate' }}</button>
                </div>
              </div>
              <div class="chan-field" style="margin-bottom:0">
                <label>Webhook URL</label>
                <div class="flex gap-2">
                  <input class="chan-input" wire:model="webhookUrl" placeholder="https://…" id="apiHook">
                  <button type="button" class="key-btn" wire:click="saveWebhookUrl">Save</button>
                </div>
              </div>
            </div>
          </div>

        {{-- Package Tab --}}
        @elseif($drawerTab === 'package')
          <div class="pkg-current">
            <div class="pkg-name">{{ $selPkg }}</div>
            <div class="pkg-since">
              Current package · since {{ $selMeta['since'] ?? 'Mar 2014' }} · renews {{ $endsAt ? $endsAt->format('j M Y') : '—' }}
            </div>
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Change package</div>
            @php
              $pkgOptions = [
                ['Premium Wire + Media', 'Full wire + photos/videos + priority support', '৳85k/mo'],
                ['Standard Wire', 'Full text wire + 200 photos/mo', '৳45k/mo'],
                ['Basic Headlines', 'Headlines + briefs only, no media', '৳18k/mo'],
              ];
            @endphp
            @foreach($pkgOptions as $opt)
              <label class="pkg-opt {{ $drawerPackageName === $opt[0] ? 'sel' : '' }}">
                <input type="radio" wire:model.live="drawerPackageName" value="{{ $opt[0] }}" name="drawerPackageName">
                <span>
                  <span class="pn">{{ $opt[0] }}</span>
                  <span class="pd" style="display:block">{{ $opt[1] }}</span>
                </span>
                <span class="pp">{{ $opt[2] }}</span>
              </label>
            @endforeach
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Add-ons</div>
            <label class="addon-check">
              <input type="checkbox" wire:model="drawerAddons" value="AP World pack">
              AP World pack (photos + world wire)
              <span class="ap">+৳30k/mo</span>
            </label>
            <label class="addon-check">
              <input type="checkbox" wire:model="drawerAddons" value="Bangla service">
              Bangla service
              <span class="ap">+৳20k/mo</span>
            </label>
          </div>

          <div class="dr-sec">
            <div class="dr-sec-title">Effective</div>
            <select class="select-input" wire:model="drawerEffective" id="pkgWhen" style="width:100%">
              <option value="Immediately (prorated)">Immediately (prorated)</option>
              <option value="Next billing cycle — {{ $endsAt ? $endsAt->format('j M Y') : 'Soon' }}">
                Next billing cycle — {{ $endsAt ? $endsAt->format('j M Y') : 'Soon' }}
              </option>
            </select>
            <div style="margin-top:13px;text-align:right">
              <button type="button" class="btn btn-primary btn-sm" wire:click="applyPackageChange" id="pkgApply">Apply changes</button>
            </div>
          </div>

        {{-- Activity Tab --}}
        @elseif($drawerTab === 'activity')
          <div class="dr-sec">
            <div class="dr-sec-title">Timeline</div>
            @if(!empty($selMeta['activity']))
              @foreach($selMeta['activity'] as $act)
                <div class="tl-item">
                  <span class="tl-dot" style="background:{{ $act['c'] ?? '#16a34a' }}"></span>
                  <div>
                    <div class="tl-text">{{ $act['t'] ?? '' }}</div>
                    <div class="tl-time">{{ $act['w'] ?? 'Recently' }}</div>
                  </div>
                </div>
              @endforeach
            @else
              <div class="text-xs text-muted">No activity records logged yet.</div>
            @endif
          </div>

        {{-- Portal Users Tab --}}
        @elseif($drawerTab === 'portal-users')
          <div class="dr-sec">
            <div class="dr-sec-title">Portal Users</div>
            @if($selected->clientUsers->isEmpty())
              <div class="text-xs text-muted" style="padding:12px 0">No portal users yet.</div>
            @else
              @foreach($selected->clientUsers as $pu)
                @php
                  $puStatus = $pu->status ?? 'active';
                  $statusColors = [
                    'active' => 'background:var(--green,#16a34a);color:#fff',
                    'invited' => 'background:#b7791f;color:#fff',
                    'deactivated' => 'background:var(--crimson,#e5484d);color:#fff',
                  ];
                @endphp
                <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f0f0;{{ $puStatus === 'deactivated' ? 'opacity:0.6;' : '' }}">
                  <div style="flex:1;min-width:0">
                    <div style="font-size:14px;font-weight:600;{{ $puStatus === 'deactivated' ? 'text-decoration:line-through;' : '' }}">
                      {{ $pu->name }}
                    </div>
                    <div style="font-size:12px;color:#666">{{ $pu->email }}</div>
                  </div>
                  <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;{{ $statusColors[$puStatus] ?? '' }}">
                    {{ ucfirst($puStatus) }}
                  </span>
                  @if($pu->clientRole)
                    <span style="font-size:11px;background:#f0f0f0;padding:2px 8px;border-radius:9999px;color:#555">
                      {{ $pu->clientRole->name }}
                    </span>
                  @endif
                </div>
                <div style="display:flex;gap:6px;padding:6px 0 10px;flex-wrap:wrap">
                  @if($puStatus === 'active')
                    <select class="select-input" style="font-size:12px;padding:4px 8px;width:auto"
                      wire:change="updatePortalUserRole({{ $pu->id }}, $event.target.value)">
                      <option value="">Change role…</option>
                      @foreach($this->clientRoles as $rId => $rName)
                        <option value="{{ $rId }}" {{ $pu->client_role_id == $rId ? 'selected' : '' }}>{{ $rName }}</option>
                      @endforeach
                    </select>
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:12px;color:var(--crimson-dark,#d13438);border-color:#f0c9ca"
                      wire:click="deactivatePortalUser({{ $pu->id }})">Deactivate</button>
                  @elseif($puStatus === 'invited')
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:12px"
                      wire:click="resendPortalInvite({{ $pu->id }})">Resend invite</button>
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:12px;color:var(--crimson-dark,#d13438);border-color:#f0c9ca"
                      wire:click="deactivatePortalUser({{ $pu->id }})">Deactivate</button>
                  @else
                    <button type="button" class="btn btn-primary btn-sm" style="font-size:12px"
                      wire:click="reactivatePortalUser({{ $pu->id }})">Reactivate</button>
                  @endif
                </div>
              @endforeach
            @endif
            <div style="margin-top:12px">
              <button type="button" class="btn btn-outline btn-sm" wire:click="openPortalInviteModal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="width:14px;height:14px;vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Invite portal user
              </button>
            </div>
          </div>
        @endif
      </div>
    @endif
  </aside>

  {{-- Pause Modal --}}
  <div class="modal-overlay {{ $showPauseModal ? 'open' : '' }}" id="pauseOverlay">
    <div class="modal">
      <div class="mo-head">
        <div class="mo-title">Pause client</div>
        <button type="button" class="mo-close" wire:click="$set('showPauseModal', false)" aria-label="Close pause dialog">✕</button>
      </div>
      <div class="mo-body">
        @php $pClient = $pauseClientId ? \App\Models\Client::find($pauseClientId) : null; @endphp
        <div class="field-hint" style="margin-bottom:14px">
          Deliveries to <b>{{ $pClient?->name ?? 'Client' }}</b> will be held (queued, not sent) until you resume. Portal login stays active but the wire stops updating.
        </div>
        <div class="field">
          <label class="field-label">Reason <span class="req">*</span></label>
          <select class="select-input" wire:model="pauseReason" id="pauseReason" style="width:100%">
            <option value="Payment hold">Payment hold</option>
            <option value="Client requested">Client requested</option>
            <option value="Technical issue on client side">Technical issue on client side</option>
            <option value="Contract renegotiation">Contract renegotiation</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Internal note</label>
          <textarea class="text-area" wire:model="pauseNote" id="pauseNote" placeholder="e.g. — Accounts following up on July invoice…"></textarea>
        </div>
        <div class="field" style="margin-bottom:0">
          <label class="field-label">Auto-resume on (optional)</label>
          <input class="text-input" type="date" wire:model="pauseResumeDate" id="pauseResume">
        </div>
      </div>
      <div class="mo-actions">
        <button type="button" class="btn btn-outline" wire:click="$set('showPauseModal', false)">Cancel</button>
        <button type="button" class="btn btn-primary" wire:click="confirmPause" id="pauseConfirm">Pause deliveries</button>
      </div>
    </div>
  </div>

  {{-- Deactivate Modal --}}
  <div class="modal-overlay {{ $showDeactModal ? 'open' : '' }}" id="deactOverlay">
    <div class="modal">
      <div class="mo-head">
        <div class="mo-title">Deactivate client</div>
        <button type="button" class="mo-close" wire:click="$set('showDeactModal', false)" aria-label="Close deactivate dialog">✕</button>
      </div>
      <div class="mo-body">
        @php $dClient = $deactClientId ? \App\Models\Client::find($deactClientId) : null; @endphp
        <div class="field-hint" style="margin-bottom:14px">
          This offboards <b>{{ $dClient?->name ?? 'Client' }}</b>: portal access revoked, all channels stopped, API keys invalidated. Download history and invoices are kept for records. You can reactivate later.
        </div>
        <label class="addon-check" style="font-weight:600">
          <input type="checkbox" wire:model.live="deactConfirmed" id="deactCheck">
          I understand — deactivate this client
        </label>
      </div>
      <div class="mo-actions">
        <button type="button" class="btn btn-outline" wire:click="$set('showDeactModal', false)">Cancel</button>
        <button type="button" class="btn btn-primary" wire:click="confirmDeactivate" id="deactConfirm" {{ ! $deactConfirmed ? 'disabled' : '' }}>Deactivate</button>
      </div>
    </div>
  </div>

  {{-- 3-Step Onboard Wizard Modal --}}
  <div class="modal-overlay {{ $showOnboardModal ? 'open' : '' }}" id="wizOverlay">
    <div class="modal modal-lg">
      <div class="mo-head">
        <div class="mo-title">Onboard new client</div>
        <button type="button" class="mo-close" wire:click="closeOnboard" aria-label="Close onboard wizard">✕</button>
      </div>
      <div class="wiz-steps" id="wizSteps">
        <div class="wiz-step {{ $wizStep === 1 ? 'active' : ($wizStep > 1 ? 'done' : '') }}" data-ws="1">1 · Details</div>
        <div class="wiz-step {{ $wizStep === 2 ? 'active' : ($wizStep > 2 ? 'done' : '') }}" data-ws="2">2 · Package &amp; channels</div>
        <div class="wiz-step {{ $wizStep === 3 ? 'active' : '' }}" data-ws="3">3 · Review &amp; activate</div>
      </div>
      <div class="mo-body">
        {{-- Step 1 Details --}}
        <div class="wiz-pane {{ $wizStep === 1 ? 'active' : '' }}" data-wp="1">
          <div class="field">
            <label class="field-label">Organisation name <span class="req">*</span></label>
            <input class="text-input" wire:model="wName" id="wName" placeholder="e.g. The Daily Ittefaq">
            @error('wName') <span class="text-xs text-crimson">{{ $message }}</span> @enderror
          </div>
          <div class="chan-2col">
            <div class="field">
              <label class="field-label">Type</label>
              <select class="select-input" wire:model="wType" id="wType" style="width:100%">
                <option value="National daily">National daily</option>
                <option value="English daily">English daily</option>
                <option value="Online portal">Online portal</option>
                <option value="TV channel">TV channel</option>
                <option value="Radio">Radio</option>
                <option value="Magazine">Magazine</option>
              </select>
            </div>
            <div class="field">
              <label class="field-label">City</label>
              <input class="text-input" wire:model="wCity" id="wCity" placeholder="Dhaka">
            </div>
          </div>
          <div class="chan-2col" style="margin-bottom:0">
            <div class="field" style="margin-bottom:0">
              <label class="field-label">Primary contact</label>
              <input class="text-input" wire:model="wContact" id="wContact" placeholder="Desk editor name">
            </div>
            <div class="field" style="margin-bottom:0">
              <label class="field-label">Contact email <span class="req">*</span></label>
              <input class="text-input" type="email" wire:model="wEmail" id="wEmail" placeholder="newsdesk@example.com">
              @error('wEmail') <span class="text-xs text-crimson">{{ $message }}</span> @enderror
            </div>
          </div>
        </div>

        {{-- Step 2 Package & channels --}}
        <div class="wiz-pane {{ $wizStep === 2 ? 'active' : '' }}" data-wp="2">
          <div class="field">
            <label class="field-label">Subscription package</label>
            <div id="wPkgs">
              <label class="pkg-opt {{ $wPackage === 'Premium Wire + Media' ? 'sel' : '' }}">
                <input type="radio" wire:model="wPackage" value="Premium Wire + Media" name="wpkg">
                <span>
                  <span class="pn">Premium Wire + Media</span>
                  <span class="pd" style="display:block">Full wire + photos/videos + priority support</span>
                </span>
                <span class="pp">৳85k/mo</span>
              </label>
              <label class="pkg-opt {{ $wPackage === 'Standard Wire' ? 'sel' : '' }}">
                <input type="radio" wire:model="wPackage" value="Standard Wire" name="wpkg">
                <span>
                  <span class="pn">Standard Wire</span>
                  <span class="pd" style="display:block">Full text wire + 200 photos/mo</span>
                </span>
                <span class="pp">৳45k/mo</span>
              </label>
              <label class="pkg-opt {{ $wPackage === 'Basic Headlines' ? 'sel' : '' }}">
                <input type="radio" wire:model="wPackage" value="Basic Headlines" name="wpkg">
                <span>
                  <span class="pn">Basic Headlines</span>
                  <span class="pd" style="display:block">Headlines + briefs only, no media</span>
                </span>
                <span class="pp">৳18k/mo</span>
              </label>
            </div>
          </div>
          <div class="field">
            <label class="field-label">Add-ons</label>
            <label class="addon-check">
              <input type="checkbox" wire:model="wAddons" value="AP World pack">
              AP World pack (photos + world wire)
              <span class="ap">+৳30k/mo</span>
            </label>
            <label class="addon-check">
              <input type="checkbox" wire:model="wAddons" value="Bangla service">
              Bangla service
              <span class="ap">+৳20k/mo</span>
            </label>
          </div>
          <div class="field" style="margin-bottom:0">
            <label class="field-label">Delivery channels</label>
            <label class="addon-check">
              <input type="checkbox" checked disabled>
              Client portal dashboard <span class="ap">always included</span>
            </label>
            <label class="addon-check">
              <input type="checkbox" wire:model="wChannels" value="email">
              Email alerts
            </label>
            <label class="addon-check">
              <input type="checkbox" wire:model="wChannels" value="ftp">
              FTP / SFTP auto-push
            </label>
            <label class="addon-check" style="margin-bottom:0">
              <input type="checkbox" wire:model="wChannels" value="api">
              API access
            </label>
          </div>
        </div>

        {{-- Step 3 Review & activate --}}
        <div class="wiz-pane {{ $wizStep === 3 ? 'active' : '' }}" data-wp="3">
          <div class="field-hint" style="margin-bottom:13px">
            Review the setup. Activating creates the portal account, provisions selected channels, and emails credentials to the client contact.
          </div>
          <div class="wiz-review" id="wizReview">
            <div class="dr-kv"><span class="k">Organisation</span><span class="v">{{ $wName ?: '—' }}</span></div>
            <div class="dr-kv"><span class="k">Type / city</span><span class="v">{{ $wType }} · {{ $wCity ?: 'Dhaka' }}</span></div>
            <div class="dr-kv"><span class="k">Contact</span><span class="v">{{ $wContact ?: '—' }} · {{ $wEmail ?: '—' }}</span></div>
            <div class="dr-kv"><span class="k">Package</span><span class="v">{{ $wPackage }}{{ !empty($wAddons) ? ' + ' . implode(', ', $wAddons) : '' }}</span></div>
            <div class="dr-kv">
              <span class="k">Channels</span>
              <span class="v">
                @php
                  $wizChans = ['Portal'];
                  if (in_array('email', $wChannels)) $wizChans[] = 'Email';
                  if (in_array('ftp', $wChannels)) $wizChans[] = 'FTP/SFTP';
                  if (in_array('api', $wChannels)) $wizChans[] = 'API';
                @endphp
                {{ implode(' · ', $wizChans) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="mo-actions">
        <button type="button" class="btn btn-outline" wire:click="wizBack" id="wizBack" style="{{ $wizStep > 1 ? 'visibility:visible' : 'visibility:hidden' }}">← Back</button>
        @if($wizStep < 3)
          <button type="button" class="btn btn-navy" wire:click="wizNext" id="wizNext">Continue →</button>
        @else
          <button type="button" class="btn btn-primary" wire:click="activateClient" id="wizActivate">Activate client</button>
        @endif
      </div>
    </div>
  </div>

  {{-- Portal Invite Modal --}}
  <div class="modal-overlay {{ $showPortalInviteModal ? 'open' : '' }}" id="portalInviteOverlay">
    <div class="modal">
      <div class="mo-head">
        <div class="mo-title">Invite portal user</div>
        <button type="button" class="mo-close" wire:click="closePortalInviteModal" aria-label="Close invite dialog">✕</button>
      </div>
      <div class="mo-body">
        <div class="field-hint" style="margin-bottom:14px">
          Send an invitation to access the UNB Wire portal for <b>{{ $selected?->name ?? 'this client' }}</b>. They will receive login credentials by email.
        </div>
        <div class="field">
          <label class="field-label">Name <span class="req">*</span></label>
          <input class="text-input" wire:model="portalInviteName" id="portalInviteName" placeholder="Full name">
          @error('portalInviteName') <span class="text-xs text-crimson">{{ $message }}</span> @enderror
        </div>
        <div class="field">
          <label class="field-label">Email <span class="req">*</span></label>
          <input class="text-input" type="email" wire:model="portalInviteEmail" id="portalInviteEmail" placeholder="user@example.com">
          @error('portalInviteEmail') <span class="text-xs text-crimson">{{ $message }}</span> @enderror
        </div>
        <div class="field" style="margin-bottom:0">
          <label class="field-label">Client role</label>
          <select class="select-input" wire:model="portalInviteRoleId" id="portalInviteRole" style="width:100%">
            <option value="">— No role —</option>
            @foreach($this->clientRoles as $rId => $rName)
              <option value="{{ $rId }}">{{ $rName }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mo-actions">
        <button type="button" class="btn btn-outline" wire:click="closePortalInviteModal">Cancel</button>
        <button type="button" class="btn btn-primary" wire:click="invitePortalUser" id="portalInviteConfirm">Send invite</button>
      </div>
    </div>
  </div>
</div>
