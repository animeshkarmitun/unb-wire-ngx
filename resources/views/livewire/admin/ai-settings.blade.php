<div class="ai-settings-scope">
    {{-- Breadcrumb --}}
    <div class="breadcrumb text-[12.5px] text-muted-2 mb-3">
        <a href="{{ route('dashboard') }}" class="text-muted hover:text-navy-800">Home</a> &nbsp;/&nbsp; Settings &nbsp;/&nbsp; AI settings
    </div>

    {{-- Topbar --}}
    <div class="flex items-center justify-between gap-3.5 mb-4.5 flex-wrap">
        <h1 class="font-serif text-[25px] font-bold tracking-[-0.015em] text-ink">AI settings</h1>
    </div>

    {{-- Master Status Banner --}}
    <div class="status-banner {{ $killed ? 'off' : '' }}" id="statusBanner">
        <span class="pulse"></span>
        <div>
            <b id="statusTitle">{{ $killed ? 'All AI features are disabled (kill switch)' : 'AI pre-edit is active' }}</b><br>
            <span id="statusSub">{{ $killed ? 'Manual workflow unaffected — re-enable below' : 'Suggestions in the editor, photo captions and Bangla→English assist' }}</span>
        </div>
        <span class="spacer"></span>
        <span class="ai-note" style="font-size:11px;color:var(--muted)">
            Auto-publish: <b id="statusAuto" style="color:{{ $autoPublish ? 'var(--crimson-dark)' : 'var(--green)' }}">{{ $autoPublish ? 'ON' : 'OFF' }}</b>
        </span>
    </div>

    {{-- ===== AI pre-edit per desk ===== --}}
    <div class="card">
        <div class="card-title">✦ AI pre-edit</div>
        <div class="card-sub">LLM suggestions inside the editor — headline variants, body polish, category, tags, Bangla→English. Journalists always review before anything is applied or published.</div>

        <div class="sw-row">
            <div class="sw-meta">
                <b>English News</b>
                <span>AI desk in the Add News form</span>
            </div>
            <label class="sw">
                <input type="checkbox" id="swEn" wire:model.live="preeditEn">
                <i></i>
            </label>
        </div>
        <div class="sw-row">
            <div class="sw-meta">
                <b>Bangla News</b>
                <span>Same assist in the Bangla editor</span>
            </div>
            <label class="sw">
                <input type="checkbox" id="swBn" wire:model.live="preeditBn">
                <i></i>
            </label>
        </div>
        <div class="sw-row">
            <div class="sw-meta">
                <b>UNB Photos</b>
                <span>Caption &amp; keyword suggestions on field uploads</span>
            </div>
            <label class="sw">
                <input type="checkbox" id="swPhotos" wire:model.live="preeditPhotos">
                <i></i>
            </label>
        </div>
    </div>

    {{-- ===== AI auto-publish ===== --}}
    <div class="card">
        <div class="card-title">⚡ AI auto-publish</div>
        <div class="card-sub">When ON, AI-pre-edited stories in allowlisted categories skip the human checklist and publish straight to the wire. <strong>Default is OFF.</strong></div>

        <div class="danger-box" id="autoOffBox" style="{{ $autoPublish ? 'display:none' : '' }}">
            <b>Auto-publish is off — every AI-assisted story needs a human checklist before publishing.</b>
            This is the recommended posture for a news agency. Turn it on only for routine, low-risk categories.
        </div>
        <div class="warn-box" id="autoOnBox" style="{{ ! $autoPublish ? 'display:none' : '' }}">
            <b>⚠ Auto-publish is ON for the allowlisted categories below.</b>
            Every auto-publish is logged with the AI version and editor context, and can be rolled back from the distribution log.
        </div>

        <div class="sw-row">
            <div class="sw-meta">
                <b>Enable AI auto-publish</b>
                <span>Applies only to the allowlisted categories</span>
            </div>
            <label class="sw danger" x-data>
                <input type="checkbox" id="swAuto" :checked="$wire.autoPublish" @click.prevent="$wire.toggleAutoPublish()">
                <i></i>
            </label>
        </div>

        <div id="autoCatsWrap" style="{{ $autoPublish ? 'opacity:1;pointer-events:auto' : 'opacity:0.45;pointer-events:none' }}">
            <div class="f-label" style="margin-top:8px">Allowlisted categories</div>
            <div class="chip-row" id="autoCats">
                @foreach($allCategories as $cat)
                    <button type="button"
                            class="chip {{ in_array($cat, $autoCats, true) ? 'on' : '' }}"
                            wire:click="toggleCategory('{{ $cat }}')"
                            data-cat="{{ $cat }}">
                        {{ $cat }}
                    </button>
                @endforeach
            </div>
            <div class="f-hint">Routine, data-driven categories only. Never allowlist politics or breaking news.</div>
        </div>
    </div>

    {{-- ===== token usage ===== --}}
    <div class="card">
        <div class="card-title">Token usage &amp; budget</div>
        <div class="card-sub">LLM calls are billed per token. Set a monthly cap — pre-edit pauses gracefully when the cap is hit (never blocks manual work).</div>

        <div class="usage-bar">
            <span id="usageFill" style="width: {{ $usagePercent }}%"></span>
        </div>
        <div class="usage-num">
            <b id="usageNow">{{ number_format($totalTokens) }}</b> of <b id="usageCapLabel">{{ number_format($monthlyCap) }}</b> tokens this month · est. cost <b id="usageCost">৳{{ number_format($costEst) }}</b>
        </div>

        <table class="desk-table">
            <thead>
                <tr>
                    <th>Desk</th>
                    <th>Calls</th>
                    <th>Tokens</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>English News</td>
                    <td>{{ number_format($callsEn) }}</td>
                    <td>{{ number_format($tokensEn) }}</td>
                </tr>
                <tr>
                    <td>Bangla News</td>
                    <td>{{ number_format($callsBn) }}</td>
                    <td>{{ number_format($tokensBn) }}</td>
                </tr>
                <tr>
                    <td>UNB Photos</td>
                    <td>{{ number_format($callsPhotos) }}</td>
                    <td>{{ number_format($tokensPhotos) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="cap-row" style="margin-top:14px">
            <div class="f-wrap">
                <label class="f-label" for="capInput">Monthly token cap</label>
                <input class="f-inp" type="number" id="capInput" wire:model.blur="monthlyCap" min="50000" step="50000">
            </div>
            <div class="f-hint" style="padding-bottom:10px">A pre-edit call uses ~1,000–2,000 tokens depending on story length.</div>
        </div>
    </div>

    {{-- ===== house style prompt ===== --}}
    <div class="card">
        <div class="card-title">House style prompt</div>
        <div class="card-sub">The system prompt every AI call follows — UNB wire style, attribution rules, banned phrasing.</div>
        <textarea class="f-ta" id="stylePrompt" wire:model="stylePrompt"></textarea>
        <div class="f-hint">Changes apply to new AI calls only. Already-generated suggestions keep the old prompt's output in the audit log.</div>
    </div>

    {{-- ===== kill switch ===== --}}
    <div class="kill-zone">
        <div class="card-title" style="color:var(--crimson-dark)">Emergency kill switch</div>
        <div class="card-sub">Instantly disables every AI feature newsroom-wide — pre-edit, translation, captions, auto-publish. Manual work is never affected.</div>
        <button type="button" class="kill-btn {{ $killed ? 'restore' : '' }}" id="killBtn" wire:click="toggleKill">
            {{ $killed ? 'Re-enable AI features' : 'Disable all AI features now' }}
        </button>
    </div>

    {{-- Sticky save bar --}}
    <div class="save-bar">
        <button type="button" class="btn btn-outline" id="resetBtn" wire:click="resetDefaults">Reset to defaults</button>
        <button type="button" class="btn btn-primary" id="saveBtn" wire:click="save">Save settings</button>
    </div>

    {{-- auto-publish enable confirmation modal --}}
    <div class="ai-settings-modal-overlay" id="autoModal" style="{{ $showAutoModal ? 'display:flex' : 'display:none' }}" wire:cloak>
        <div class="ai-settings-modal">
            <h3>⚠ Enable AI auto-publish?</h3>
            <p>AI-pre-edited stories in the allowlisted categories will go <strong>straight to the wire</strong> without a human checklist. Every auto-publish is logged and can be rolled back, but clients may see the story before anyone at UNB reads it.</p>
            <div class="m-foot">
                <button type="button" class="btn btn-outline" id="autoCancel" wire:click="cancelAutoPublish">Keep it off</button>
                <button type="button" class="btn btn-primary" id="autoConfirm" wire:click="confirmAutoPublish">Yes, enable for allowlist only</button>
            </div>
        </div>
    </div>
</div>
