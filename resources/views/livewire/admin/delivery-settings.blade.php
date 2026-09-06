<div class="delivery-settings-scope" x-data="deliverySettings()">
    {{-- Breadcrumb --}}
    <div class="breadcrumb text-[12.5px] text-muted-2 mb-3">
        <a href="{{ route('dashboard') }}" class="text-muted hover:text-navy-800">Home</a> &nbsp;/&nbsp; Distribution &nbsp;/&nbsp; Delivery settings
    </div>

    {{-- Masthead Context Bar --}}
    <div class="mast-context">
        <div class="mast-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="#e5484d" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M2 12h20"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            UNB <span class="portal-tag">Client Portal</span>
        </div>

        <div class="mast-clock">
            <b id="delivClockTime" x-text="clockTime">--:--</b> Dhaka · <span id="delivClockDate" x-text="clockDate">---</span>
        </div>

        <span class="spacer"></span>

        {{-- Client Context Switcher --}}
        <div class="client-selector-wrap">
            <label for="clientSelect" class="client-selector-label">Client:</label>
            <select id="clientSelect" class="client-selector-sel" wire:model.live="selectedClientId">
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                @endforeach
            </select>
        </div>

        {{-- Client Info Chip --}}
        @php
            $notes = is_string($client?->notes) ? json_decode($client->notes, true) : ($client->notes ?? []);
            $ini = $notes['ini'] ?? ($client ? substr($client->name, 0, 2) : 'DS');
            $grad = $notes['grad'] ?? 'g1';
            $tier = $notes['tier'] ?? 'Premium';
        @endphp
        <div class="user-chip">
            <div class="user-av {{ $grad }}">{{ $ini }}</div>
            <div class="user-meta">
                <b id="clientNameDisplay">{{ $client?->name ?? 'The Daily Star' }}</b>
                <span id="clientTierDisplay">★ {{ $tier }}</span>
            </div>
        </div>
    </div>

    {{-- Page Header --}}
    <div class="page-head">
        <h1 class="page-title">Delivery settings</h1>
        <p class="page-sub">Zero-touch delivery — UNB pushes the wire and licensed media straight into your systems, the moment it publishes. Configure once; no manual downloading needed.</p>
    </div>

    <div class="max-w-[880px]">
        {{-- ============ 1. AUTO-PUSH (FTP / SFTP) ============ --}}
        <section class="card">
            <div class="card-head">
                <div class="card-ic navy">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title">Auto-push (FTP / SFTP)</div>
                    <div class="card-desc">New stories and media packs land in your server folders automatically</div>
                </div>
                <span class="spacer"></span>
                <label class="switch" title="Toggle auto-push">
                    <input type="checkbox" id="pushMaster" wire:click="togglePushMaster" {{ $pushMaster ? 'checked' : '' }}>
                    <span class="sl"></span>
                </label>
            </div>

            <div class="card-body">
                <div class="status-row">
                    <span class="dot {{ $pushMaster ? 'live' : 'paused' }}" id="pushDot"></span>
                    <div class="status-txt">
                        <b id="connectionEndpointText">Connected to {{ $connectionEndpoint }}</b>
                        <span id="lastPushInfo">{{ $lastPushText }}</span>
                    </div>
                    <span class="spacer"></span>
                    <button type="button" class="btn ghost sm" id="testBtn" wire:click="testConnection">
                        {{ $testBtnText }}
                    </button>
                    <button type="button" class="btn ghost sm" id="credBtn" wire:click="toggleCreds">
                        {{ $showCreds ? 'Hide credentials' : 'Edit credentials' }}
                    </button>
                </div>

                {{-- Credentials Drawer --}}
                <div id="credFields" @if(!$showCreds) hidden @endif style="margin-bottom:16px">
                    <div class="grid-2">
                        <div class="field">
                            <label for="sftpHost">SFTP host</label>
                            <input class="inp mono" id="sftpHost" wire:model="sftpHost" placeholder="ftp.dailystar.com">
                        </div>
                        <div class="field">
                            <label for="sftpPort">Port</label>
                            <input class="inp mono" id="sftpPort" wire:model="sftpPort" placeholder="22">
                        </div>
                        <div class="field">
                            <label for="sftpUser">Username</label>
                            <input class="inp mono" id="sftpUser" wire:model="sftpUser" placeholder="unb-delivery">
                        </div>
                        <div class="field">
                            <label for="sftpAuth">Authentication</label>
                            <select class="sel" id="sftpAuth" wire:model="sftpAuth">
                                <option value="SSH key (recommended)">SSH key (recommended)</option>
                                <option value="Password">Password</option>
                            </select>
                        </div>
                    </div>
                    <div class="field mt-3">
                        <label for="sftpPassword">Credentials Key / Password</label>
                        <input type="password" class="inp mono" id="sftpPassword" wire:model="sftpPassword">
                    </div>
                    <button type="button" class="btn navy sm" id="credSave" wire:click="saveCredentials">Save credentials</button>
                    <hr class="divider">
                </div>

                {{-- Channel Rows --}}
                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="swEnglishWire" wire:model.live="chanEnglishWire">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">English news wire <span class="fmt-tag">NewsML XML</span></div>
                        <div class="chan-path">→ /unb-wire/english/ &nbsp;·&nbsp; pushes instantly on publish</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="swUnbPhotos" wire:model.live="chanUnbPhotos">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">UNB photos <span class="fmt-tag">JPEG + IPTC</span></div>
                        <div class="chan-path">→ /unb-media/photos/ &nbsp;·&nbsp; print resolution, captions embedded</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="swUnbVideo" wire:model.live="chanUnbVideo">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">UNB video <span class="fmt-tag">MP4 · 1080p</span></div>
                        <div class="chan-path">→ /unb-media/video/ &nbsp;·&nbsp; proxy pushed first, full file follows</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="swTigersPack" wire:model.live="chanTigersPack">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">Tigers Test series pack <span class="fmt-tag">JPEG + MP4</span></div>
                        <div class="chan-path">→ /unb-media/packs/tigers/ &nbsp;·&nbsp; included in your media pack</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="swApWorld" wire:model.live="chanApWorld" {{ $canAccessAp ? '' : 'disabled' }}>
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">
                            AP World photo pack
                            @if($canAccessAp)
                                <span class="fmt-tag">JPEG + IPTC</span>
                            @else
                                <span class="lock-tag">🔒 Add-on not enabled</span>
                            @endif
                        </div>
                        <div class="chan-path">
                            → /ap-media/world/ &nbsp;·&nbsp;
                            @if(!$canAccessAp)
                                <a href="{{ route('admin.clients') }}" style="text-decoration:underline;color:var(--amber)">request from UNB sales</a>
                            @else
                                full wire editorial license
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="divider">

                <div class="grid-2">
                    <div class="field">
                        <label for="wireFmt">Wire format</label>
                        <select class="sel" id="wireFmt" wire:model="wireFormat">
                            <option value="NewsML-G2 (XML)">NewsML-G2 (XML)</option>
                            <option value="NITF (XML)">NITF (XML)</option>
                            <option value="JSON (UNB v1)">JSON (UNB v1)</option>
                            <option value="RSS 2.0">RSS 2.0</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="pushSchedule">Push schedule</label>
                        <select class="sel" id="pushSchedule" wire:model="pushSchedule">
                            <option value="Instantly on publish">Instantly on publish</option>
                            <option value="Batch — every 15 minutes">Batch — every 15 minutes</option>
                            <option value="Batch — hourly">Batch — hourly</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn navy" id="pushSave" wire:click="savePushSettings">Save push settings</button>
            </div>
        </section>

        {{-- ============ 2. API ACCESS ============ --}}
        <section class="card">
            <div class="card-head">
                <div class="card-ic crimson">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6"/>
                        <polyline points="8 6 2 12 8 18"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title">API access</div>
                    <div class="card-desc">Pull the wire and media library directly from your CMS</div>
                </div>
            </div>

            <div class="card-body">
                <div class="field">
                    <label for="apiEndpoint">Base endpoint</label>
                    <div class="key-row">
                        <input class="inp mono" id="apiEndpoint" value="{{ $apiEndpoint }}" readonly>
                        <button type="button" class="btn ghost sm" id="copyEndpoint" @click="copyValue('apiEndpoint', $el)">Copy</button>
                    </div>
                </div>

                <div class="field">
                    <label for="apiKey">
                        API key <span style="color:var(--muted-2);font-weight:400">— keep this secret; it authenticates your newsroom</span>
                    </label>
                    <div class="key-row">
                        <input class="inp mono" id="apiKey" value="{{ $isKeyRevealed ? $rawApiKey : $maskedApiKey }}" readonly>
                        <button type="button" class="btn ghost sm" id="revealKey" wire:click="toggleRevealKey">
                            {{ $isKeyRevealed ? 'Hide' : 'Reveal' }}
                        </button>
                        <button type="button" class="btn ghost sm" id="copyKey" @click="copyValue('apiKey', $el)">Copy</button>

                        @if($regenStep === 0)
                            <button type="button" class="btn crimson sm" id="regenKey" wire:click="requestRegenerateKey">Regenerate</button>
                        @else
                            <button type="button" class="btn crimson sm" id="regenKey" wire:click="confirmRegenerateKey">Click again to confirm</button>
                        @endif
                    </div>
                    <div class="note">Regenerating revokes the old key immediately — remember to update your CMS plugin.</div>
                </div>

                <hr class="divider">

                <div class="field">
                    <label for="webhookUrl">
                        Webhook URL <span style="color:var(--muted-2);font-weight:400">— we POST here the moment something relevant publishes</span>
                    </label>
                    <input class="inp mono" id="webhookUrl" placeholder="https://cms.dailystar.com/hooks/unb" wire:model="webhookUrl">
                </div>

                <div class="field">
                    <label>Notify on</label>
                    <div class="check-list">
                        <label class="check-item">
                            <input type="checkbox" id="notifyBreaking" wire:model="notifyBreaking">
                            Breaking news published
                        </label>
                        <label class="check-item">
                            <input type="checkbox" id="notifyMediaPack" wire:model="notifyMediaPack">
                            New media pack published
                        </label>
                        <label class="check-item">
                            <input type="checkbox" id="notifyExclusive" wire:model="notifyExclusive">
                            Exclusive-to-you content published
                        </label>
                        <label class="check-item">
                            <input type="checkbox" id="notifyEmbargoed" wire:model="notifyEmbargoed">
                            Embargoed asset becomes available
                        </label>
                    </div>
                </div>

                <button type="button" class="btn navy" id="apiSave" wire:click="saveApiSettings">Save API settings</button>
                <div class="note" style="margin-top:10px">
                    Full API reference: <a href="{{ route('admin.distribution') }}" style="text-decoration:underline;color:var(--navy-800)">docs.unbnews.org/api</a> · CMS plugins available for WordPress, Ghost and Arc.
                </div>
            </div>
        </section>

        {{-- ============ 3. EMAIL ALERTS ============ --}}
        <section class="card">
            <div class="card-head">
                <div class="card-ic amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title">Email alerts</div>
                    <div class="card-desc">The right people hear about the right content, instantly</div>
                </div>
            </div>

            <div class="card-body">
                <div class="field">
                    <label>Recipients</label>
                    <div class="email-chips" id="emailChips">
                        @foreach($emailRecipients as $index => $email)
                            <span class="email-chip">
                                {{ $email }}
                                <button type="button" title="Remove" wire:click="removeEmailRecipient({{ $index }})">✕</button>
                            </span>
                        @endforeach
                    </div>
                    <div class="inp-row">
                        <input class="inp" id="emailInput" placeholder="Add an email address…" wire:model="emailInput" wire:keydown.enter.prevent="addEmailRecipient">
                        <button type="button" class="btn ghost" id="emailAdd" wire:click="addEmailRecipient">Add</button>
                    </div>
                </div>

                <hr class="divider">

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="alertBreaking" wire:model.live="alertBreaking">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">Breaking news</div>
                        <div class="chan-path">Instant email, headline + brief, link to full story</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="alertMediaPack" wire:model.live="alertMediaPack">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">New media pack published</div>
                        <div class="chan-path">Thumbnails + direct ZIP link</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="alertExclusive" wire:model.live="alertExclusive">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">Exclusive-to-you content</div>
                        <div class="chan-path">Instant — exclusivity windows are short, don't miss them</div>
                    </div>
                </div>

                <div class="chan-row">
                    <label class="switch">
                        <input type="checkbox" id="alertSavedSearch" wire:model.live="alertSavedSearch">
                        <span class="sl"></span>
                    </label>
                    <div class="chan-info">
                        <div class="chan-name">Saved-search alerts</div>
                        <div class="chan-path">e.g. "Bangladesh Bank", "Tigers" — instant or daily digest at 7 AM</div>
                    </div>
                </div>

                <button type="button" class="btn navy" id="alertSave" wire:click="saveAlertSettings">Save alert settings</button>
            </div>
        </section>

        {{-- ============ 4. DOWNLOAD & LICENSE HISTORY ============ --}}
        <section class="card">
            <div class="card-head">
                <div class="card-ic green">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title">Download &amp; license history</div>
                    <div class="card-desc">Every media download is logged for your license audit trail</div>
                </div>
                <span class="spacer"></span>
                <button type="button" class="btn ghost sm" id="histCsv" wire:click="exportCsv" @click="handleExportCsv($el)">
                    Export CSV
                </button>
            </div>

            <div class="card-body">
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>License</th>
                            <th>Downloaded by</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="histBody">
                        @forelse($downloads as $row)
                            <tr>
                                <td>{{ $row['asset'] }}</td>
                                <td><span class="lic-pill {{ $row['lic_class'] }}">{{ $row['license'] }}</span></td>
                                <td>{{ $row['downloaded_by'] }}</td>
                                <td>{{ $row['date'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No download history recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="audit-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span>This log is shared with UNB and AP for license compliance audits. AP-licensed images must be used within their license terms — single publication, credit mandatory, no archival beyond 30 days.</span>
                </div>
            </div>
        </section>

        {{-- ============ 5. ENGINE DISPATCH RULES (NFR §6) ============ --}}
        <section class="card">
            <div class="card-head">
                <div class="card-ic navy">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="21" x2="4" y2="14"/>
                        <line x1="4" y1="10" x2="4" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12" y2="3"/>
                        <line x1="20" y1="21" x2="20" y2="16"/>
                        <line x1="20" y1="12" x2="20" y2="3"/>
                        <line x1="1" y1="14" x2="7" y2="14"/>
                        <line x1="9" y1="8" x2="15" y2="8"/>
                        <line x1="17" y1="16" x2="23" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title">Engine dispatch rules</div>
                    <div class="card-desc">At-least-once fan-out, retry attempts, backoff intervals, and auto-pause threshold</div>
                </div>
            </div>

            <div class="card-body">
                <div class="grid-3">
                    <div class="field">
                        <label for="retryAttempts">Retry attempts</label>
                        <input type="number" id="retryAttempts" class="inp" wire:model="retryAttempts" min="1" max="10">
                    </div>
                    <div class="field">
                        <label for="backoffSeconds">Backoff (s)</label>
                        <input type="number" id="backoffSeconds" class="inp" wire:model="backoffSeconds" min="1" max="600">
                    </div>
                    <div class="field">
                        <label for="autoPauseAfter">Auto-pause after failures</label>
                        <input type="number" id="autoPauseAfter" class="inp" wire:model="autoPauseAfter" min="1" max="20">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="check-item">
                        <input type="checkbox" id="atLeastOnce" wire:model.live="atLeastOnce">
                        At-least-once delivery (idempotency key fan-out)
                    </label>
                </div>

                <button type="button" class="btn navy mt-4" id="engineSave" wire:click="saveEngineRules">Save engine rules</button>
            </div>
        </section>
    </div>
</div>

<script>
    function deliverySettings() {
        return {
            clockTime: '--:--',
            clockDate: '---',
            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);
            },
            updateClock() {
                const now = new Date();
                this.clockTime = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Dhaka', hour: '2-digit', minute: '2-digit'
                }).format(now);
                this.clockDate = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Dhaka', weekday: 'short', day: 'numeric', month: 'short'
                }).format(now);
            },
            copyValue(id, btn) {
                const el = document.getElementById(id);
                if (!el) return;
                const val = el.value;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(val).catch(() => {});
                }
                const oldText = btn.textContent;
                btn.textContent = '✓ Copied';
                setTimeout(() => { btn.textContent = oldText; }, 1400);
            },
            handleExportCsv(btn) {
                const oldText = btn.textContent;
                btn.textContent = '✓ Exported';
                setTimeout(() => { btn.textContent = oldText; }, 2000);
            }
        };
    }
</script>
