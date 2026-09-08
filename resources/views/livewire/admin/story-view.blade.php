<div class="story-reader-root" x-data="storyReader()" x-init="initReader()">
    <script>
    window.storyReader = function storyReader() {
        return {
            clockText: 'Dhaka',

            initReader() {
                this.updateClock();
                setInterval(() => this.updateClock(), 30000);
            },

            updateClock() {
                try {
                    const now = new Date();
                    const d = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', weekday: 'short', day: 'numeric', month: 'short' }).format(now);
                    const t = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Dhaka', hour: 'numeric', minute: '2-digit', hour12: true }).format(now);
                    this.clockText = 'Dhaka · ' + d + ' · ' + t;
                } catch (e) {
                    this.clockText = 'Dhaka · ' + new Date().toLocaleTimeString();
                }
            },

            saveBlob(content, filename, mimeType) {
                const blob = new Blob([content], { type: mimeType });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                setTimeout(() => {
                    document.body.removeChild(a);
                    URL.revokeObjectURL(a.href);
                }, 2000);
            },

            flashDone(buttonId, successLabel) {
                const btn = document.getElementById(buttonId);
                if (!btn) return;
                const originalHtml = btn.innerHTML;
                btn.classList.add('done');
                btn.textContent = '✓ ' + successLabel;
                setTimeout(() => {
                    btn.classList.remove('done');
                    btn.innerHTML = originalHtml;
                }, 1800);
            },

            getStoryMeta() {
                const head = document.getElementById('storyHead')?.textContent.trim() || 'UNB Story';
                const sub = document.getElementById('storySub')?.textContent.trim() || '';
                const date = document.getElementById('storyDate')?.textContent.trim() || '';
                const bodyElements = [...document.querySelectorAll('#storyBody p, #storyBody .story-pull-quote')];
                const body = bodyElements.map(p => p.textContent.trim()).filter(Boolean).join('\n\n');
                const id = '{{ $story->public_id }}';
                const cat = '{{ $story->category->name_en ?? "Bangladesh" }}';
                return { head, sub, date, body, id, cat };
            },

            downloadWord() {
                const { head, sub, date, body, id } = this.getStoryMeta();
                const doc =
                    '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"></head><body>' +
                    '<h1>' + head + '</h1>' +
                    (sub ? '<p><i>' + sub + '</i></p>' : '') +
                    '<p><b>UNB News</b> · ' + date + '</p>' +
                    body.split('\n\n').map(p => '<p>' + p + '</p>').join('') +
                    '</body></html>';
                this.saveBlob(doc, 'unb-story-' + id + '.doc', 'application/msword');
                this.flashDone('dlWord', 'Word saved');
            },

            downloadText() {
                const { head, sub, date, body, id } = this.getStoryMeta();
                const text = head + '\n\n' + (sub ? sub + '\n\n' : '') + 'UNB News · ' + date + '\n\n' + body;
                this.saveBlob(text, 'unb-story-' + id + '.txt', 'text/plain;charset=utf-8');
                this.flashDone('dlText', 'Text saved');
            },

            downloadXml() {
                const { head, sub, date, body, id, cat } = this.getStoryMeta();
                const escapeXml = (str) => str.replace(/[<>&'"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '\'': '&apos;', '"': '&quot;' }[c]));
                const xml =
                    '<?xml version="1.0" encoding="UTF-8"?>\n' +
                    '<story id="' + id + '" service="{{ $story->language }}">\n' +
                    '  <category>' + escapeXml(cat) + '</category>\n' +
                    '  <headline>' + escapeXml(head) + '</headline>\n' +
                    '  <subhead>' + escapeXml(sub) + '</subhead>\n' +
                    '  <published>' + escapeXml(date) + '</published>\n' +
                    '  <body>\n' +
                    body.split('\n\n').map(p => '    <p>' + escapeXml(p) + '</p>').join('\n') + '\n' +
                    '  </body>\n' +
                    '</story>';
                this.saveBlob(xml, 'unb-story-' + id + '.xml', 'application/xml');
                this.flashDone('dlXml', 'XML saved');
            },

            downloadImage() {
                const { head, id } = this.getStoryMeta();
                const canvas = document.createElement('canvas');
                canvas.width = 1200;
                canvas.height = 630;
                const ctx = canvas.getContext('2d');

                // Gradient Background
                const grad = ctx.createLinearGradient(0, 0, 1200, 630);
                grad.addColorStop(0, '#16204a');
                grad.addColorStop(1, '#0f1730');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, 1200, 630);

                // Crimson accent bar
                ctx.fillStyle = '#e5484d';
                ctx.fillRect(80, 80, 120, 36);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 16px sans-serif';
                ctx.fillText('UNB WIRE', 96, 104);

                // Story headline wrap
                ctx.font = 'bold 42px serif';
                const words = head.split(' ');
                let line = '';
                let y = 220;
                for (let n = 0; n < words.length; n++) {
                    const testLine = line + words[n] + ' ';
                    const metrics = ctx.measureText(testLine);
                    if (metrics.width > 1040 && n > 0) {
                        ctx.fillText(line, 80, y);
                        line = words[n] + ' ';
                        y += 54;
                    } else {
                        line = testLine;
                    }
                }
                ctx.fillText(line, 80, y);

                // Footer brand
                ctx.fillStyle = '#aab3cf';
                ctx.font = '18px sans-serif';
                ctx.fillText('United News of Bangladesh · Official Wire Service', 80, 560);

                canvas.toBlob(blob => {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'unb-story-' + id + '-banner.png';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(() => {
                        document.body.removeChild(a);
                        URL.revokeObjectURL(a.href);
                    }, 2000);
                }, 'image/png');

                this.flashDone('dlImage', 'Image saved');
            },

            printStory() {
                window.print();
            },

            copyDispatch() {
                const { head, sub, date, body } = this.getStoryMeta();
                const dispatchText = head + '\n\n' + (sub ? sub + '\n\n' : '') + 'UNB News · ' + date + '\n\n' + body;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(dispatchText);
                    this.flashDone('copyDispatch', 'Dispatch copied');
                }
            },

            copyLink() {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(window.location.href);
                    const btn = document.getElementById('copyLink');
                    if (btn) {
                        btn.style.borderColor = 'var(--green)';
                        btn.style.color = 'var(--green)';
                        setTimeout(() => {
                            btn.style.borderColor = '';
                            btn.style.color = '';
                        }, 1400);
                    }
                }
            },

            shareSocial(platform) {
                const url = encodeURIComponent(window.location.href);
                const { head } = this.getStoryMeta();
                const text = encodeURIComponent(head);
                if (platform === 'facebook') {
                    window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank', 'width=600,height=400');
                } else if (platform === 'x') {
                    window.open('https://x.com/intent/tweet?url=' + url + '&text=' + text, '_blank', 'width=600,height=400');
                }
            }
        };
    };
    </script>

    {{-- Sub-masthead bar with Back navigation and live Asia/Dhaka clock --}}
    <div class="story-subhead-bar">
        <a href="{{ route('admin.news', $story->language ?? 'en') }}" class="story-back-link">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>{{ $story->language === 'bn' ? '← বাংলা সংবাদ' : '← English news' }}</span>
        </a>

        <div class="live-clock-badge">
            <span class="pulse-dot"></span>
            <span id="readerClockTime" x-text="clockText">Dhaka</span>
        </div>
    </div>

    {{-- Main 2-column Grid --}}
    <div class="story-grid-container">

        {{-- Left Column: Article Reader --}}
        <article class="story-main-column">
            {{-- Breadcrumb --}}
            <nav class="story-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.news', $story->language ?? 'en') }}">Newsroom</a>
                <span class="text-muted-2">/</span>
                <span>{{ $story->category->name_en ?? 'General' }}</span>
                @if($story->dateline_city)
                    <span class="text-muted-2">/</span>
                    <span>{{ $story->dateline_city }}</span>
                @endif
            </nav>

            {{-- Action Buttons Bar --}}
            <div class="story-actions-bar">
                <button type="button" class="story-action-btn" id="dlWord" @click="downloadWord()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <path d="M9 15l1.5-5L12 15l1.5-5L15 15"></path>
                    </svg>
                    <span>Download Word</span>
                </button>

                <button type="button" class="story-action-btn" id="dlText" @click="downloadText()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="9" y1="13" x2="15" y2="13"></line>
                        <line x1="9" y1="17" x2="13" y2="17"></line>
                    </svg>
                    <span>Download Text</span>
                </button>

                <button type="button" class="story-action-btn" id="dlImage" @click="downloadImage()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <path d="m21 15-5-5L5 21"></path>
                    </svg>
                    <span>Download Image</span>
                </button>

                <button type="button" class="story-action-btn" id="dlXml" @click="downloadXml()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                    <span>Download XML</span>
                </button>

                <button type="button" class="story-action-btn" id="printBtn" @click="printStory()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    <span>Print</span>
                </button>

                <button type="button" class="story-action-btn" id="copyDispatch" @click="copyDispatch()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span>Copy Dispatch</span>
                </button>

                <a href="{{ route('admin.add-news') }}?id={{ $story->id }}" class="story-action-btn text-navy-800 font-bold border-navy-800 hover:bg-navy-800 hover:text-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span>Edit in Wizard</span>
                </a>
            </div>

            {{-- Category Pill & Breaking Badge --}}
            <div class="flex items-center gap-2 mb-3">
                <span class="story-category-pill">{{ $story->category->name_en ?? 'Bangladesh' }}</span>
                @if($story->is_breaking)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-crimson text-white">
                        BREAKING
                    </span>
                @endif
                @if($story->priority === 'urgent')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-amber-500 text-white">
                        URGENT
                    </span>
                @endif
            </div>

            {{-- Main Headline --}}
            <h1 class="story-heading-main" id="storyHead">{{ $story->headline }}</h1>

            {{-- Subhead --}}
            @if($story->sub_head)
                <p class="story-subheading-main" id="storySub">{{ $story->sub_head }}</p>
            @endif

            {{-- Byline Bar --}}
            <div class="story-byline-bar">
                <span class="story-byline-brand">{{ $story->source ?: 'UNB News' }}</span>
                <span id="storyDate">
                    {{ $story->published_at ? $story->published_at->format('F j, Y, h:i A') : ($story->created_at ? $story->created_at->format('F j, Y, h:i A') : 'Recently') }}
                </span>

                <span class="story-byline-stat">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    </svg>
                    <span>{{ $story->word_count ?: str_word_count(strip_tags($story->body_html ?? '')) }} words</span>
                </span>

                <span class="story-byline-stat">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>{{ $this->readingTime }} min read</span>
                </span>

                <span class="story-byline-stat">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>1,420 views</span>
                </span>

                <div class="story-byline-share-group">
                    <button type="button" class="story-share-icon-btn" title="Share on Facebook" @click="shareSocial('facebook')">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                    </button>
                    <button type="button" class="story-share-icon-btn" title="Share on X" @click="shareSocial('x')">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4l16 16"></path>
                            <path d="M20 4L4 20"></path>
                        </svg>
                    </button>
                    <button type="button" class="story-share-icon-btn" title="Copy link" id="copyLink" @click="copyLink()">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Featured Image Stage --}}
            @php
                $feat = $this->featuredMedia;
                $gradClass = $feat ? ($feat->derivatives['grad'] ?? ('g' . (($feat->id % 8) + 1))) : 'g1';
            @endphp
            <div class="story-featured-box">
                <div class="story-featured-stage {{ $feat && !empty($feat->original_path) ? '' : $gradClass }}" id="featuredStage">
                    @if($feat && !empty($feat->original_path))
                        <img src="{{ Storage::url($feat->original_path) }}" alt="{{ $feat->caption ?: $story->headline }}">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="story-featured-caption">
                {{ $feat?->caption ?: ($story->brief ?: 'UNB wire photo coverage and news dispatch.') }}
                <strong>— Photo: {{ $feat?->credit_line ?: 'UNB' }}</strong>
            </div>

            {{-- Article Body Content --}}
            <div class="story-rendered-body" id="storyBody">
                @if($story->brief)
                    <div class="story-pull-quote">{{ $story->brief }}</div>
                @endif

                @if(!empty($story->body_html))
                    {!! $story->body_html !!}
                @else
                    <p>{{ $story->body_text ?: 'Full dispatch content in review.' }}</p>
                @endif

                <p class="story-signoff-line">
                    END/UNB/{{ strtoupper(substr(md5($story->id), 0, 4)) }}/{{ $story->dateline_city ?: 'DHAKA' }}
                </p>
            </div>

            {{-- Story Tags --}}
            @if($story->tags && $story->tags->count() > 0)
                <div class="story-tag-cloud">
                    <svg class="w-4 h-4 text-muted-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    @foreach($story->tags as $tag)
                        <a href="{{ route('admin.news', $story->language ?? 'en') }}?tag={{ urlencode($tag->name) }}" class="story-tag-pill">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Related Articles Section --}}
            <div class="story-related-section">
                <h3 class="story-section-header">Related articles</h3>
                <div class="story-card-grid">
                    @forelse($this->relatedStories as $rel)
                        @php
                            $relGrad = 'g' . (($rel->id % 8) + 1);
                            $relMedia = $rel->media->first();
                        @endphp
                        <a class="story-mini-card" href="{{ route('admin.story', $rel->public_id) }}">
                            <div class="story-mini-thumb {{ $relMedia && !empty($relMedia->original_path) ? '' : $relGrad }}">
                                @if($relMedia && !empty($relMedia->original_path))
                                    <img src="{{ Storage::url($relMedia->original_path) }}" alt="{{ $rel->headline }}">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <path d="m21 15-5-5L5 21"></path>
                                    </svg>
                                @endif
                            </div>
                            <div class="story-mini-body">
                                <div class="story-mini-cat">{{ $rel->category->name_en ?? 'Wire' }}</div>
                                <h4 class="story-mini-title">{{ $rel->headline }}</h4>
                                <div class="story-mini-time">
                                    {{ $rel->published_at ? $rel->published_at->format('M j, h:i A') : 'Recent' }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="text-xs text-muted col-span-3 py-4">No other articles found in this category.</p>
                    @endforelse
                </div>
            </div>
        </article>

        {{-- Right Column: Newsroom Editorial Chrome & Rail --}}
        <aside class="space-y-6">

            {{-- Editorial Workflow Card (FR-NWS-019) --}}
            <div class="story-editorial-panel">
                <div class="story-editorial-title">
                    <span>Newsroom Control</span>
                    @php
                        $statusStyles = [
                            'draft' => 'bg-amber-100 text-amber-800 border-amber-300',
                            'in_review' => 'bg-blue-100 text-blue-800 border-blue-300',
                            'changes_requested' => 'bg-purple-100 text-purple-800 border-purple-300',
                            'approved' => 'bg-teal-100 text-teal-800 border-teal-300',
                            'published' => 'bg-green-100 text-green-800 border-green-300',
                            'killed' => 'bg-red-100 text-red-800 border-red-300',
                            'archived' => 'bg-gray-100 text-gray-800 border-gray-300',
                        ];
                        $stClass = $statusStyles[$story->status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                    @endphp
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider {{ $stClass }}">
                        {{ str_replace('_', ' ', $story->status) }}
                    </span>
                </div>

                {{-- Key Story Metadata --}}
                <div class="space-y-2 text-xs text-muted border-b border-border pb-3 mb-3">
                    <div class="flex justify-between items-center">
                        <span>Wire Public ID:</span>
                        <code class="font-mono text-[11px] bg-paper px-1.5 py-0.5 rounded text-ink select-all">{{ $story->public_id }}</code>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Language / Desk:</span>
                        <span class="font-semibold text-ink uppercase">{{ $story->language }} Wire</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Current Version:</span>
                        <span class="font-semibold text-ink">v{{ $story->version }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Owner / Reporter:</span>
                        <span class="font-semibold text-ink">{{ $story->owner?->name ?? 'Newsroom Desk' }}</span>
                    </div>
                </div>

                {{-- Status Transition Controls --}}
                <div class="space-y-2 mb-4">
                    @if($story->status === 'draft')
                        <button type="button" wire:click="transitionStatus('in_review')" class="w-full text-xs font-semibold py-2 px-3 bg-navy-800 text-white rounded-lg hover:bg-navy-900 transition flex items-center justify-center gap-1.5">
                            <span>Submit for Editor Review</span>
                        </button>
                    @elseif($story->status === 'in_review')
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="transitionStatus('approved')" class="text-xs font-semibold py-2 px-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                Approve Story
                            </button>
                            <button type="button" wire:click="transitionStatus('changes_requested')" class="text-xs font-semibold py-2 px-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                                Request Edit
                            </button>
                        </div>
                    @elseif($story->status === 'changes_requested')
                        <button type="button" wire:click="transitionStatus('in_review')" class="w-full text-xs font-semibold py-2 px-3 bg-navy-800 text-white rounded-lg hover:bg-navy-900 transition">
                            Re-submit to Review
                        </button>
                    @elseif($story->status === 'approved')
                        <button type="button" wire:click="transitionStatus('published')" class="w-full text-xs font-semibold py-2 px-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            Publish to Wire
                        </button>
                    @elseif($story->status === 'published')
                        <button type="button" wire:click="transitionStatus('killed')" wire:confirm="Are you sure you want to kill/unpublish this live story? Clients will receive kill notices." class="w-full text-xs font-semibold py-2 px-3 border border-crimson text-crimson rounded-lg hover:bg-crimson-soft transition">
                            Kill / Unpublish Story
                        </button>
                    @endif
                </div>

                {{-- Internal Notes Thread --}}
                <div class="mt-4 pt-3 border-t border-border">
                    <div class="text-[11.5px] font-bold uppercase tracking-wider text-navy-800 mb-2 flex items-center justify-between">
                        <span>Internal Notes ({{ $story->notes()->count() }})</span>
                        <span class="text-[10px] text-muted font-normal">Newsroom only</span>
                    </div>

                    <div class="max-h-48 overflow-y-auto space-y-2 mb-3 pr-1" id="notesFeed">
                        @forelse($story->notes()->with('user')->orderBy('created_at')->get() as $note)
                            <div class="story-note-item">
                                <div class="story-note-meta">
                                    <span class="font-semibold text-ink">{{ $note->user?->name ?? 'Staff' }}</span>
                                    <span>{{ $note->created_at ? $note->created_at->format('M j, h:i A') : '' }}</span>
                                </div>
                                <div class="text-ink leading-snug">{{ $note->body }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-muted italic py-2 text-center">No internal notes yet.</div>
                        @endforelse
                    </div>

                    <div class="space-y-2">
                        <textarea
                            wire:model.live="newNote"
                            placeholder="Add confidential editorial note..."
                            rows="2"
                            class="w-full text-xs border border-border rounded-lg p-2 focus:ring-1 focus:ring-navy-800 focus:outline-none"
                            required
                        ></textarea>
                        @error('newNote') <span class="text-crimson text-[11px] block">{{ $message }}</span> @enderror
                        <button type="button" wire:click="addNote" class="w-full py-1.5 px-3 bg-paper border border-border text-xs font-semibold text-navy-800 rounded-lg hover:bg-gray-100 transition">
                            Post Internal Note
                        </button>
                    </div>
                </div>
            </div>

            {{-- Timeline + Versions (gated history.can_view) --}}
            @if($this->canViewHistory)
                {{-- Workflow Timeline --}}
                <div class="story-editorial-panel mt-4">
                    <div class="story-editorial-title">
                        <span>Workflow Timeline</span>
                        <span class="text-[10px] text-muted font-normal">{{ $story->events->count() }} events</span>
                    </div>
                    <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                        @forelse($story->events->sortByDesc('created_at') as $evt)
                            @php
                                $label = \App\Models\StoryEvent::ACTIONS[$evt->action] ?? $evt->action;
                                $actorName = $evt->actor?->name ?? 'System';
                                $isSystem = in_array($evt->action, ['created', 'sent_to_review', 'auto_published']);
                            @endphp
                            <div class="flex gap-2 text-[11px] leading-snug">
                                <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full {{ $evt->action === 'published' ? 'bg-green-500' : ($evt->action === 'killed' ? 'bg-crimson' : ($evt->action === 'handover' ? 'bg-amber-500' : 'bg-navy-300')) }}"></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-semibold text-ink">{{ $actorName }}</span>
                                        <span class="text-muted">{{ $label }}</span>
                                    </div>
                                    @if($evt->payload)
                                        <div class="text-muted mt-0.5">
                                            @if(isset($evt->payload['gate']))
                                                <span class="px-1 py-0.5 rounded text-[9px] font-bold uppercase {{ $evt->payload['gate'] === 'auto' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600' }}">{{ $evt->payload['gate'] }}</span>
                                            @endif
                                            @if(isset($evt->payload['from_user']) && isset($evt->payload['to_user']))
                                                {{ $evt->payload['from_user'] }} → {{ $evt->payload['to_user'] }}
                                            @endif
                                            @if(isset($evt->payload['reason']))
                                                "{{ $evt->payload['reason'] }}"
                                            @endif
                                            @if(isset($evt->payload['fields']))
                                                {{ implode(', ', $evt->payload['fields']) }}
                                            @endif
                                        </div>
                                    @endif
                                    @if($evt->from_status && $evt->to_status && $evt->from_status !== $evt->to_status)
                                        <div class="text-muted mt-0.5">
                                            <span class="px-1 py-0.5 rounded text-[9px] bg-gray-100">{{ str_replace('_', ' ', $evt->from_status) }}</span>
                                            <span class="mx-0.5">→</span>
                                            <span class="px-1 py-0.5 rounded text-[9px] bg-gray-100">{{ str_replace('_', ' ', $evt->to_status) }}</span>
                                        </div>
                                    @endif
                                    <div class="text-muted text-[10px] mt-0.5">{{ $evt->created_at?->timezone('Asia/Dhaka')->format('M j, h:i A') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-muted italic py-2 text-center">No events recorded.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Version History --}}
                <div class="story-editorial-panel mt-4">
                    <div class="story-editorial-title">
                        <span>Version History</span>
                        <span class="text-[10px] text-muted font-normal">{{ $story->versions->count() }} snapshots</span>
                    </div>

                    {{-- Version list with compare checkboxes --}}
                    <div class="max-h-48 overflow-y-auto space-y-1.5 pr-1 mb-3">
                        @php $sortedVersions = $story->versions->sortByDesc('version'); @endphp
                        @forelse($sortedVersions as $ver)
                            <div class="flex items-center justify-between text-[11px] py-1 px-2 rounded {{ $ver->version === $story->version ? 'bg-navy-50 border border-navy-200' : 'hover:bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox"
                                        wire:model.live="diffA"
                                        value="{{ $ver->version }}"
                                        x-on:change="
                                            if ($el.checked) {
                                                if (!@js($diffA)) { $wire.set('diffA', {{ $ver->version }}) }
                                                else if (!@js($diffB)) { $wire.set('diffB', {{ $ver->version }}) }
                                                else { $el.checked = false }
                                            } else {
                                                if (@js($diffA) === {{ $ver->version }}) { $wire.set('diffA', null) }
                                                if (@js($diffB) === {{ $ver->version }}) { $wire.set('diffB', null) }
                                            }
                                        "
                                        class="rounded border-gray-300 text-navy-800 focus:ring-navy-800"
                                    >
                                    <span class="font-mono font-bold text-ink">v{{ $ver->version }}</span>
                                    <span class="text-muted">{{ $ver->creator?->name ?? 'System' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-muted text-[10px]">{{ $ver->created_at?->timezone('Asia/Dhaka')->format('M j, h:i A') }}</span>
                                    @if($this->canEditStory && $ver->version !== $story->version && in_array($story->status, ['draft', 'in_review', 'changes_requested']))
                                        <button type="button" wire:click="requestRestore({{ $ver->version }})" class="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 hover:bg-amber-200 font-bold uppercase">Restore</button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-muted italic py-2 text-center">No versions recorded.</div>
                        @endforelse
                    </div>

                    {{-- Compare button --}}
                    @if($diffA && $diffB && $diffA !== $diffB && !$diffResult)
                        <button type="button" wire:click="compareVersions" class="w-full py-1.5 px-3 bg-navy-800 text-white text-xs font-semibold rounded-lg hover:bg-navy-900 transition">
                            Compare v{{ $diffA }} ↔ v{{ $diffB }}
                        </button>
                    @endif

                    {{-- Diff result panel --}}
                    @if($diffResult)
                        <div class="border-t border-border pt-3 mt-2">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold text-ink">Diff: v{{ $diffA }} ↔ v{{ $diffB }}</span>
                                <button type="button" wire:click="clearDiff" class="text-[10px] text-muted hover:text-ink">✕ Close</button>
                            </div>

                            {{-- Field changes --}}
                            @if(!empty($diffResult['fields']))
                                <div class="space-y-1 mb-3">
                                    @foreach($diffResult['fields'] as $f)
                                        <div class="text-[10px] flex items-start gap-2 py-0.5">
                                            <span class="font-mono font-bold text-ink min-w-[60px]">{{ $f['field'] }}</span>
                                            <span class="text-crimson line-through flex-1">{{ is_array($f['from']) ? implode(', ', $f['from']) : ($f['from'] ?? '—') }}</span>
                                            <span class="text-green-600 flex-1">{{ is_array($f['to']) ? implode(', ', $f['to']) : ($f['to'] ?? '—') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-[10px] text-muted italic mb-2">No field changes.</div>
                            @endif

                            {{-- Body diff --}}
                            @if(!empty($diffResult['body']))
                                <div class="bg-paper border border-border rounded-lg p-2 text-[10px] leading-relaxed max-h-32 overflow-y-auto">
                                    @foreach($diffResult['body'] as $seg)
                                        @if(($seg['type'] ?? '') === 'added')
                                            <span class="bg-green-100 text-green-800 px-0.5 rounded">{{ $seg['token'] }}</span>
                                        @elseif(($seg['type'] ?? '') === 'removed')
                                            <span class="bg-red-100 text-red-800 line-through px-0.5 rounded">{{ $seg['token'] }}</span>
                                        @else
                                            <span>{{ $seg['token'] }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-[10px] text-muted italic">Body unchanged.</div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Restore confirmation modal --}}
                @if($showRestoreConfirm)
                    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click="cancelRestore">
                        <div class="bg-white rounded-xl shadow-2xl p-5 w-80" wire:click.stop>
                            <h3 class="text-sm font-bold text-ink mb-2">Restore to v{{ $restoreTarget }}?</h3>
                            <p class="text-xs text-muted mb-4">This will create a new version with the content from v{{ $restoreTarget }}. Current version (v{{ $story->version }}) is preserved. This cannot be undone.</p>
                            <div class="flex gap-2">
                                <button type="button" wire:click="cancelRestore" class="flex-1 py-1.5 px-3 border border-border text-xs font-semibold rounded-lg hover:bg-gray-50">Cancel</button>
                                <button type="button" wire:click="confirmRestore" class="flex-1 py-1.5 px-3 bg-amber-600 text-white text-xs font-semibold rounded-lg hover:bg-amber-700">Restore</button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Latest News Wire Rail --}}
            <div class="story-wire-rail">
                <div class="story-rail-header">Latest wire news</div>
                <div>
                    @forelse($this->latestRail as $item)
                        @php
                            $railGrad = 'g' . (($item->id % 8) + 1);
                            $railMedia = $item->media->first();
                        @endphp
                        <a class="story-rail-row" href="{{ route('admin.story', $item->public_id) }}">
                            <div class="story-rail-row-thumb {{ $railMedia && !empty($railMedia->original_path) ? '' : $railGrad }}">
                                @if($railMedia && !empty($railMedia->original_path))
                                    <img src="{{ Storage::url($railMedia->original_path) }}" alt="{{ $item->headline }}">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <path d="m21 15-5-5L5 21"></path>
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h5 class="story-rail-row-head">{{ $item->headline }}</h5>
                                <div class="story-rail-row-time">
                                    {{ $item->published_at ? $item->published_at->format('M j, h:i A') : 'Recent' }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-4 text-xs text-muted text-center">No other recent wire stories.</div>
                    @endforelse
                </div>
            </div>

            {{-- Distribution Summary Box --}}
            <div class="bg-panel border border-border rounded-xl p-4 text-xs text-muted">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px] mb-2">Wire Distribution Status</div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span>Portal Availability:</span>
                        <span class="font-semibold {{ $story->status === 'published' ? 'text-green-600' : 'text-amber-600' }}">
                            {{ $story->status === 'published' ? '● Live on Wire' : '○ Staged' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Client Fan-out:</span>
                        <span>{{ $story->deliveries->count() }} dispatches</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Search Index:</span>
                        <span class="text-green-600">Synced to Meili</span>
                    </div>
                </div>
            </div>

        </aside>

    </div>
</div>
