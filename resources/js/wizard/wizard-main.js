/**
 * UNB Wire — Add News Wizard Client Controller
 * 1:1 Implementation of app-data/add-news.html
 */

(function () {
  'use strict';

  function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function fmtDateTime(ts) {
    const time = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(ts));
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(new Date(ts)) + ', ' + time;
  }

  function miniToast(msg) {
    let t = document.querySelector('.mini-toast');
    if (!t) {
      t = document.createElement('div');
      t.className = 'mini-toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._tm);
    t._tm = setTimeout(() => t.classList.remove('show'), 2400);
  }

  const STEP_NAMES = { 1: 'Write', 2: 'Media', 3: 'Organize & access', 4: 'Review & publish' };

  const SAVED_TAGS = [
    ['bangladesh', '3,421'], ['dhaka', '1,284'], ['cricket', '1,678'], ['world', '1,120'],
    ['politics', '986'], ['crime', '842'], ['economy', '764'], ['education', '655'],
    ['t20', '530'], ['health', '489'], ['parliament', '412'], ['weather', '301'],
    ['europe', '265'], ['loadshedding', '218'], ['airport', '143'], ['stem', '54']
  ];

  const WIRE_STORIES = [
    { id: 4821, head: 'Padma Bridge toll revenue crosses Tk 800cr in first quarter', cat: 'Business', date: 'Aug 24' },
    { id: 4817, head: 'Met office forecasts heavy rain in 8 divisions this week', cat: 'Bangladesh', date: 'Aug 24' },
    { id: 4809, head: 'Tigers name squad for home Test series against New Zealand', cat: 'Sports', date: 'Aug 23' },
    { id: 4802, head: 'Exports rebound in July as RMG orders pick up', cat: 'Business', date: 'Aug 23' },
    { id: 4796, head: 'Dengue cases decline for third straight week: DGHS', cat: 'Health', date: 'Aug 22' },
    { id: 4788, head: 'New terminal to double airport capacity by December', cat: 'Bangladesh', date: 'Aug 21' }
  ];

  window.initAddNewsClientController = function () {
    const editorEl = document.getElementById('editorBody');
    if (!editorEl) return;
    if (window.__addNewsInitialized) return;
    window.__addNewsInitialized = true;

    let currentStep = 1;
    const headlineInput = document.getElementById('headlineInput');
    const headlineError = document.getElementById('headlineError');
    const briefInput = document.getElementById('briefInput');
    const briefCount = document.getElementById('briefCount');

    // ===== 1. Stepper Navigation & Validation =====
    function showStep(n) {
      currentStep = n;
      document.querySelectorAll('.step-panel').forEach(p =>
        p.classList.toggle('active', +p.dataset.step === n));

      document.querySelectorAll('.stp').forEach(s => {
        const num = +s.dataset.go;
        s.classList.toggle('active', num === n);
        s.classList.toggle('done', num < n);
      });
      document.querySelectorAll('.stp-line').forEach(l =>
        l.classList.toggle('done', +l.dataset.line < n));

      const progressText = document.getElementById('progressText');
      if (progressText) progressText.textContent = 'Step ' + n + ' of 4 — ' + STEP_NAMES[n];

      const backBtn = document.getElementById('backBtn');
      if (backBtn) backBtn.style.visibility = n === 1 ? 'hidden' : 'visible';

      const nextBtn = document.getElementById('nextBtn');
      if (nextBtn) {
        nextBtn.style.display = n === 4 ? 'none' : '';
        nextBtn.textContent = n === 3 ? 'Review →' : 'Continue →';
      }

      const publishBtn = document.getElementById('publishBtn');
      if (publishBtn) publishBtn.style.display = n === 4 ? '' : 'none';

      const quickPublishBtn = document.getElementById('quickPublishBtn');
      if (quickPublishBtn) quickPublishBtn.style.display = n === 4 ? 'none' : '';

      if (n === 4) renderReview();
      syncLivewireStep(n);
    }

    function validateStep(n) {
      if (n === 1) {
        if (!headlineInput || !headlineInput.value.trim()) {
          if (headlineInput) headlineInput.classList.add('input-error');
          if (headlineError) headlineError.classList.add('show');
          if (headlineInput) headlineInput.focus();
          return false;
        }
      }
      return true;
    }

    function syncLivewireStep(n) {
      const app = document.getElementById('addNewsApp');
      const wireEl = app ? app.closest('[wire\\:id]') : null;
      if (wireEl && window.Livewire) {
        const wireComponent = window.Livewire.find(wireEl.getAttribute('wire:id'));
        if (wireComponent) wireComponent.set('step', n);
      }
    }

    if (headlineInput) {
      headlineInput.addEventListener('input', () => {
        headlineInput.classList.remove('input-error');
        if (headlineError) headlineError.classList.remove('show');
      });
    }

    if (briefInput && briefCount) {
      briefInput.addEventListener('input', () => {
        briefCount.textContent = String(briefInput.value.length);
      });
    }

    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) nextBtn.onclick = () => { if (validateStep(currentStep)) showStep(currentStep + 1); };

    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.onclick = () => { if (currentStep > 1) showStep(currentStep - 1); };

    document.querySelectorAll('.stp').forEach(s => {
      s.onclick = () => {
        const target = +s.dataset.go;
        if (target <= currentStep || validateStep(currentStep)) {
          showStep(target);
        }
      };
    });

    // ===== 2. Render Review Rows on Step 4 =====
    function renderReview() {
      const briefVal = briefInput ? briefInput.value : '';
      const bodyTxt = quill ? quill.getText() : '';
      const words = bodyTxt.trim() ? bodyTxt.trim().split(/\s+/).length : 0;
      const catEl = document.getElementById('catSelect');
      const catText = catEl && catEl.selectedIndex >= 0 && catEl.value ? catEl.options[catEl.selectedIndex].text : '';
      const excChecked = document.querySelector('input[name="access"]:checked')?.value === 'exclusive';

      const featuredPreview = document.getElementById('featuredPreview');
      const featuredCap = featuredPreview && featuredPreview.classList.contains('has-photo')
        ? (featuredPreview.querySelector('.fp-cap')?.textContent || 'Featured Image')
        : '';
      const attachTiles = document.querySelectorAll('.attach-tile');

      function row(k, v, opts) {
        opts = opts || {};
        const cls = 'v' + (opts.missing ? ' missing' : '') + (opts.serif ? ' serif' : '');
        return '<div class="rv-row"><span class="k">' + k + '</span><span class="' + cls + '">' + v + '</span></div>';
      }

      const tags = [...document.querySelectorAll('.tag-chip')].map(c => c.textContent.replace('✕', '').trim());

      const reviewContainer = document.getElementById('reviewRows');
      if (reviewContainer) {
        reviewContainer.innerHTML =
          '<div class="rv-section">' +
            '<div class="rv-group-title">Story <button type="button" class="rv-edit" data-goto="1">Edit</button></div>' +
            (headlineInput && headlineInput.value.trim()
              ? row('Headline', esc(headlineInput.value), { serif: true })
              : row('Headline', 'Missing — required', { missing: true })) +
            row('Sub head', esc(document.getElementById('subheadInput')?.value) || '—') +
            row('Brief', briefVal.length + ' / 280 characters') +
            row('Body', words ? words.toLocaleString() + ' words' : 'Empty', { missing: !words }) +

            '<div class="rv-group-title">Media <button type="button" class="rv-edit" data-goto="2">Edit</button></div>' +
            (featuredCap
              ? row('Featured image', esc(featuredCap))
              : row('Featured image', 'Not set — can add later')) +
            row('Attachments', attachTiles.length ? attachTiles.length + ' items attached' : 'None') +

            '<div class="rv-group-title">Organize &amp; access <button type="button" class="rv-edit" data-goto="3">Edit</button></div>' +
            row('Category', catText ? esc(catText) : 'Not selected', { missing: !catText }) +
            row('Tags', tags.length ? esc(tags.join(', ')) : '—') +
            row('Access', excChecked ? 'Exclusive distribution' : 'Standard — all subscribers') +
          '</div>';

        reviewContainer.querySelectorAll('.rv-edit').forEach(b => {
          b.onclick = () => showStep(+b.dataset.goto);
        });
      }
    }

    // ===== 3. Notes Thread & Publishing =====
    const ntSend = document.getElementById('ntSend');
    if (ntSend) {
      ntSend.onclick = () => {
        const ta = document.getElementById('ntInput');
        const text = ta ? ta.value.trim() : '';
        if (!text) { if (ta) ta.focus(); return; }
        const ntList = document.getElementById('ntList');
        if (ntList) {
          const item = document.createElement('div');
          item.className = 'nt-item sub';
          item.innerHTML = '<div class="nt-head"><b>Staff</b><span class="nt-role sub">Desk</span><span class="nt-time">Just now</span></div><div class="nt-body">' + esc(text) + '</div>';
          ntList.appendChild(item);
        }
        if (ta) ta.value = '';
        miniToast('Note added to newsroom thread');
      };
    }

    // Explicit Livewire action callers (more reliable than wire:click on buttons that start hidden).
    // Publish, quickPublish, Send-to-editor, Next and Back use wire:click directly
// (only the buttons that started hidden — next/publish/quickPublish — keep their
// wire:click because they work fine, while JS showStep handles visual transitions).
// The JS below only resets the client view when the user chooses to add more media after publish.
const addMediaAfter = document.getElementById('addMediaAfter');
    if (addMediaAfter) {
      addMediaAfter.onclick = () => {
        const rc = document.getElementById('reviewCard');
        if (rc) rc.style.display = '';
        const wn = document.getElementById('wizardNav');
        if (wn) wn.style.display = '';
        const sc = document.getElementById('successCard');
        if (sc) sc.classList.remove('show');
        showStep(2);
      };
    }

    const sentBackBtn = document.getElementById('sentBackBtn');
    if (sentBackBtn) {
      sentBackBtn.onclick = () => {
        const rc = document.getElementById('reviewCard');
        if (rc) rc.style.display = '';
        const wn = document.getElementById('wizardNav');
        if (wn) wn.style.display = '';
        const sentCard = document.getElementById('sentCard');
        if (sentCard) sentCard.classList.remove('show');
        showStep(1);
      };
    }

    // ===== 4. Quill Editor & Wire Toolbar =====
    let quill = null;

    function initQuill() {
      if (quill) return;
      if (!window.Quill) {
        setTimeout(initQuill, 50);
        return;
      }
      try {
        quill = new Quill('#editorBody', {
          theme: 'snow',
          placeholder: 'Write the full story…',
          modules: {
            toolbar: {
              container: '#editorToolbar',
              handlers: {
                dateline: () => insertDateline(),
                signoff: () => insertSignoff(),
                pullquote: () => insertPullQuote(),
                related: () => openRelatedModal(),
                table: () => openTableModal(),
                cleanup: () => wireCleanup(),
                findreplace: () => toggleFindBar(),
                history: () => openHistoryModal()
              }
            }
          }
        });

        quill.on('text-change', (delta, oldDelta, source) => {
          updateEditorStats();
          syncPreview();
          if (source === 'user') {
            const html = quill.root.innerHTML;
            const app = document.getElementById('addNewsApp');
            const wireEl = app ? app.closest('[wire\\:id]') : null;
            if (wireEl && window.Livewire) {
              const wireComponent = window.Livewire.find(wireEl.getAttribute('wire:id'));
              if (wireComponent) wireComponent.syncBody(html);
            }
          }
        });

        if (window.Livewire) {
          window.Livewire.on('quill-set-content', ({ html }) => {
            if (quill && html) {
              quill.root.innerHTML = html;
              updateEditorStats();
              syncPreview();
            }
          });
        }
      } catch (e) {
        console.error('Quill initialization:', e);
      }
    }
    initQuill();

    // Wire button fallback click handlers & mousedown blur prevention
    document.querySelectorAll('#editorToolbar button.qlc').forEach(btn => {
      btn.addEventListener('mousedown', e => e.preventDefault());
    });

    const qlDateline = document.querySelector('#editorToolbar .ql-dateline');
    if (qlDateline) qlDateline.onclick = e => { e.preventDefault(); insertDateline(); };
    const qlSignoff = document.querySelector('#editorToolbar .ql-signoff');
    if (qlSignoff) qlSignoff.onclick = e => { e.preventDefault(); insertSignoff(); };
    const qlPullquote = document.querySelector('#editorToolbar .ql-pullquote');
    if (qlPullquote) qlPullquote.onclick = e => { e.preventDefault(); insertPullQuote(); };
    const qlRelated = document.querySelector('#editorToolbar .ql-related');
    if (qlRelated) qlRelated.onclick = e => { e.preventDefault(); openRelatedModal(); };
    const qlTable = document.querySelector('#editorToolbar .ql-table');
    if (qlTable) qlTable.onclick = e => { e.preventDefault(); openTableModal(); };
    const qlCleanup = document.querySelector('#editorToolbar .ql-cleanup');
    if (qlCleanup) qlCleanup.onclick = e => { e.preventDefault(); wireCleanup(); };
    const qlFindreplace = document.querySelector('#editorToolbar .ql-findreplace');
    if (qlFindreplace) qlFindreplace.onclick = e => { e.preventDefault(); toggleFindBar(); };
    const qlHistory = document.querySelector('#editorToolbar .ql-history');
    if (qlHistory) qlHistory.onclick = e => { e.preventDefault(); openHistoryModal(); };

    // Fullscreen toggle & full canvas focus delegation
    const editorWrap = document.getElementById('editorWrap');
    const fsBtn = document.getElementById('fsBtn');
    function toggleFullscreen(force) {
      if (!editorWrap) return;
      const on = force !== undefined ? force : !editorWrap.classList.contains('fullscreen');
      editorWrap.classList.toggle('fullscreen', on);
      if (fsBtn) {
        fsBtn.textContent = on ? '✕' : '⛶';
        fsBtn.title = on ? 'Exit fullscreen (Esc)' : 'Fullscreen (Esc to exit)';
      }
      document.body.style.overflow = on ? 'hidden' : '';
      if (quill) {
        setTimeout(() => {
          quill.focus();
        }, 50);
      }
    }

    if (fsBtn) {
      fsBtn.addEventListener('mousedown', e => e.preventDefault());
      fsBtn.onclick = () => toggleFullscreen();
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && editorWrap && editorWrap.classList.contains('fullscreen')) {
        toggleFullscreen(false);
      }
    });

    if (editorWrap) {
      const qlCont = editorWrap.querySelector('.ql-container');
      if (qlCont) {
        qlCont.addEventListener('click', e => {
          if (e.target === qlCont && quill) {
            quill.focus();
          }
        });
      }
    }

    function updateEditorStats() {
      const txt = quill ? quill.getText().trim() : '';
      const words = txt ? txt.split(/\s+/).filter(Boolean).length : 0;
      const efWords = document.getElementById('efWords');
      const efRead = document.getElementById('efRead');
      if (efWords) efWords.textContent = words.toLocaleString() + ' words';
      if (efRead) efRead.textContent = '~' + Math.max(1, Math.round(words / 200)) + ' min read';
    }

    const DATELINE_FMT = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Dhaka', month: 'short', day: 'numeric' });
    function insertDateline() {
      const city = document.getElementById('datelineCity')?.value || 'DHAKA';
      const d = DATELINE_FMT.format(new Date());
      const lead = city.toUpperCase() + ', ' + d + ' — ';
      if (quill) {
        const range = quill.getSelection(true) || { index: 0, length: 0 };
        quill.insertText(range.index, lead, { bold: true }, 'user');
        quill.setSelection(range.index + lead.length, 0);
      } else {
        const qlEditor = document.querySelector('.ql-editor');
        if (qlEditor) qlEditor.innerHTML = '<p><strong>' + esc(lead) + '</strong> ' + qlEditor.innerHTML + '</p>';
      }
      miniToast('Dateline inserted');
    }

    function insertSignoff() {
      const num = 1000 + Math.floor(Math.random() * 9000);
      const sign = '\nEND/UNB/' + num + '\n';
      if (quill) {
        quill.insertText(quill.getLength() - 1, sign, {}, 'user');
        quill.setSelection(quill.getLength(), 0);
      } else {
        const qlEditor = document.querySelector('.ql-editor');
        if (qlEditor) qlEditor.innerHTML += '<p>' + esc(sign) + '</p>';
      }
      miniToast('UNB signoff added');
    }

    function insertPullQuote() {
      if (!quill) return;
      const range = quill.getSelection(true) || { index: 0, length: 0 };
      const sel = range.length ? quill.getText(range.index, range.length).trim() : '';
      const quote = sel || 'Pull quote from the story';
      const at = range.index + range.length;
      quill.insertText(at, '\n“' + quote + '”\n', {}, 'user');
      quill.formatText(at + 1, quote.length + 2, { italic: true }, 'user');
      quill.formatLine(at + 1, quote.length + 2, { blockquote: true }, 'user');
      quill.setSelection(at + quote.length + 4, 0);
    }

    function wireCleanup() {
      if (!quill) return;
      let count = 0;
      const ops = (quill.getContents().ops || []).map(op => {
        if (typeof op.insert !== 'string') return op;
        const cleaned = op.insert
          .replace(/[ \t]{2,}/g, () => { count++; return ' '; })
          .replace(/"([^"\n]+)"/g, (m, g) => { count++; return '“' + g + '”'; })
          .replace(/ ?-- ?/g, () => { count++; return '—'; })
          .replace(/ +([,.;:!?])/g, (m, g) => { count++; return g; });
        return Object.assign({}, op, { insert: cleaned });
      });
      if (count) {
        quill.setContents({ ops }, 'user');
        miniToast('Cleaned formatting');
      } else {
        miniToast('Already clean');
      }
    }

    // ===== 5. Find & Replace =====
    const frBar = document.getElementById('frBar');
    const frFind = document.getElementById('frFind');
    const frCount = document.getElementById('frCount');
    let frMatches = [];
    let frIdx = -1;

    function toggleFindBar(show) {
      if (!frBar) return;
      const willShow = show !== undefined ? show : frBar.hidden;
      frBar.hidden = !willShow;
      if (willShow && frFind) {
        frFind.focus();
        frFind.select();
        runFind();
      } else if (quill) {
        quill.focus();
      }
    }

    function runFind() {
      if (!quill || !frFind) return;
      const q = frFind.value;
      frMatches = [];
      if (q) {
        const lower = quill.getText().toLowerCase();
        const needle = q.toLowerCase();
        let i = 0;
        while ((i = lower.indexOf(needle, i)) !== -1) {
          frMatches.push(i);
          i += needle.length;
        }
      }
      frIdx = frMatches.length ? 0 : -1;
      if (frCount) frCount.textContent = frMatches.length ? frMatches.length + ' found' : (q ? 'No matches' : '0 found');
      if (frIdx >= 0) jumpToMatch();
    }

    function jumpToMatch() {
      if (!quill || frIdx < 0 || frIdx >= frMatches.length) return;
      quill.setSelection(frMatches[frIdx], frFind.value.length, 'user');
      if (frCount) frCount.textContent = (frIdx + 1) + ' of ' + frMatches.length;
    }

    if (frFind) frFind.oninput = runFind;
    const frNext = document.getElementById('frNext');
    if (frNext) frNext.onclick = () => { if (!frMatches.length) return; frIdx = (frIdx + 1) % frMatches.length; jumpToMatch(); };
    const frPrev = document.getElementById('frPrev');
    if (frPrev) frPrev.onclick = () => { if (!frMatches.length) return; frIdx = (frIdx - 1 + frMatches.length) % frMatches.length; jumpToMatch(); };
    const frClose = document.getElementById('frClose');
    if (frClose) frClose.onclick = () => toggleFindBar(false);

    // ===== 6. Modals =====
    const relOverlay = document.getElementById('relOverlay');
    const relList = document.getElementById('relList');
    const relSearch = document.getElementById('relSearch');
    const relInsert = document.getElementById('relInsert');
    let relSel = null;

    function renderRelList() {
      if (!relList) return;
      const q = (relSearch?.value || '').trim().toLowerCase();
      const list = WIRE_STORIES.filter(s => !q || s.head.toLowerCase().includes(q) || s.cat.toLowerCase().includes(q));
      relList.innerHTML = list.length ? list.map(s =>
        '<div class="rel-item ' + (relSel === s.id ? 'sel' : '') + '" data-id="' + s.id + '">' +
          '<span class="rel-cat">' + esc(s.cat) + '</span>' +
          '<div><div class="rel-head">' + esc(s.head) + '</div><div class="rel-date">#' + s.id + ' · ' + s.date + '</div></div>' +
        '</div>'
      ).join('') : '<div class="his-empty">No stories match search query</div>';

      relList.querySelectorAll('.rel-item').forEach(el => {
        el.onclick = () => {
          relSel = +el.dataset.id;
          relList.querySelectorAll('.rel-item').forEach(x => x.classList.toggle('sel', +x.dataset.id === relSel));
          if (relInsert) relInsert.disabled = false;
        };
      });
    }

    function openRelatedModal() {
      relSel = null;
      if (relInsert) relInsert.disabled = true;
      if (relSearch) relSearch.value = '';
      renderRelList();
      relOverlay?.classList.add('open');
    }

    if (relSearch) relSearch.oninput = renderRelList;
    const closeRel = document.getElementById('closeRel');
    if (closeRel) closeRel.onclick = () => relOverlay?.classList.remove('open');
    const cancelRel = document.getElementById('cancelRel');
    if (cancelRel) cancelRel.onclick = () => relOverlay?.classList.remove('open');
    if (relInsert) {
      relInsert.onclick = () => {
        const story = WIRE_STORIES.find(s => s.id === relSel);
        if (!story || !quill) return;
        const range = quill.getSelection(true) || { index: 0, length: 0 };
        const at = range.index + range.length;
        quill.insertText(at, '\nAlso read: ', { italic: true }, 'user');
        quill.insertText(at + 11, story.head + '\n', { link: '#story-' + story.id }, 'user');
        quill.setSelection(at + 12 + story.head.length, 0);
        relOverlay?.classList.remove('open');
        miniToast('Related story link embedded');
      };
    }

    // Table generator modal
    const tblOverlay = document.getElementById('tblOverlay');
    function tableLines(rows, cols, header) {
      const w = 12;
      const pad = s => (s + ' '.repeat(w)).slice(0, w);
      const heads = [];
      for (let c = 0; c < cols; c++) heads.push('Column ' + (c + 1));
      const lines = [];
      if (header) {
        const h = heads.map(pad).join(' | ');
        lines.push(h, '-'.repeat(h.length).replace(/\|/g, '+'));
      }
      for (let r = 0; r < rows; r++) lines.push(Array(cols).fill('·').map(pad).join(' | '));
      return lines;
    }
    function syncTblPreview() {
      const rows = Math.min(10, Math.max(1, +document.getElementById('tblRows')?.value || 3));
      const cols = Math.min(5, Math.max(2, +document.getElementById('tblCols')?.value || 3));
      const header = document.getElementById('tblHeader')?.checked !== false;
      const prev = document.getElementById('tblPrev');
      if (prev) prev.textContent = tableLines(rows, cols, header).join('\n');
    }
    function openTableModal() {
      syncTblPreview();
      tblOverlay?.classList.add('open');
    }
    ['tblRows', 'tblCols', 'tblHeader'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.oninput = syncTblPreview;
    });
    const closeTbl = document.getElementById('closeTbl');
    if (closeTbl) closeTbl.onclick = () => tblOverlay?.classList.remove('open');
    const cancelTbl = document.getElementById('cancelTbl');
    if (cancelTbl) cancelTbl.onclick = () => tblOverlay?.classList.remove('open');
    const tblInsert = document.getElementById('tblInsert');
    if (tblInsert) {
      tblInsert.onclick = () => {
        const rows = Math.min(10, Math.max(1, +document.getElementById('tblRows')?.value || 3));
        const cols = Math.min(5, Math.max(2, +document.getElementById('tblCols')?.value || 3));
        const header = document.getElementById('tblHeader')?.checked !== false;
        const lines = tableLines(rows, cols, header);
        if (quill) {
          const range = quill.getSelection(true) || { index: 0, length: 0 };
          const at = range.index + range.length;
          quill.insertText(at, '\n' + lines.join('\n') + '\n', {}, 'user');
          let pos = at + 1;
          lines.forEach(l => {
            quill.formatLine(pos, l.length, { 'code-block': true }, 'user');
            pos += l.length + 1;
          });
          quill.setSelection(at + 1, 0);
        }
        tblOverlay?.classList.remove('open');
        miniToast('Table inserted');
      };
    }

    // Revision history modal
    let revisions = [];
    try { revisions = JSON.parse(localStorage.getItem('unb_rev_v1') || '[]'); } catch (e) { revisions = []; }
    function snapRevision() {
      if (!quill) return;
      const txt = quill.getText().trim();
      if (!txt) return;
      revisions.unshift({ t: Date.now(), words: txt.split(/\s+/).length, delta: quill.getContents() });
      if (revisions.length > 8) revisions.length = 8;
      try { localStorage.setItem('unb_rev_v1', JSON.stringify(revisions)); } catch (e) {}
    }
    setInterval(snapRevision, 60000);

    const hisOverlay = document.getElementById('hisOverlay');
    function openHistoryModal() {
      snapRevision();
      const hisList = document.getElementById('hisList');
      if (!hisList) return;
      if (!revisions.length) {
        hisList.innerHTML = '<div class="his-empty">No snapshots recorded yet in this session.</div>';
      } else {
        hisList.innerHTML = revisions.map((r, i) => {
          const firstLine = (r.delta.ops || []).map(o => typeof o.insert === 'string' ? o.insert : '').join('').trim().split('\n').find(Boolean) || '(empty)';
          return '<div class="his-row">' +
            '<div><span class="his-time">' + fmtDateTime(r.t) + '</span><span class="his-words">' + r.words + ' words</span></div>' +
            '<span class="his-preview">' + esc(firstLine) + '</span>' +
            '<button type="button" class="btn btn-outline btn-sm his-restore" data-i="' + i + '">Restore</button>' +
          '</div>';
        }).join('');
        hisList.querySelectorAll('.his-restore').forEach(b => {
          b.onclick = () => {
            if (quill) {
              quill.setContents(revisions[+b.dataset.i].delta, 'user');
              updateEditorStats();
              syncPreview();
              hisOverlay?.classList.remove('open');
              miniToast('Snapshot restored');
            }
          };
        });
      }
      hisOverlay?.classList.add('open');
    }
    const closeHis = document.getElementById('closeHis');
    if (closeHis) closeHis.onclick = () => hisOverlay?.classList.remove('open');
    const cancelHis = document.getElementById('cancelHis');
    if (cancelHis) cancelHis.onclick = () => hisOverlay?.classList.remove('open');

    // Document Importer (.docx / .txt)
    const docOverlay = document.getElementById('docOverlay');
    const docFile = document.getElementById('docFile');
    const docDrop = document.getElementById('docDrop');
    const docError = document.getElementById('docError');
    const docPreview = document.getElementById('docPreview');
    const docInsert = document.getElementById('docInsert');
    let pendingDoc = null;

    const openImport = document.getElementById('openImport');
    if (openImport) openImport.onclick = () => docOverlay?.classList.add('open');
    function closeDocModal() {
      docOverlay?.classList.remove('open');
      if (docPreview) docPreview.hidden = true;
      if (docError) docError.classList.remove('show');
      if (docFile) docFile.value = '';
      pendingDoc = null;
      if (docInsert) docInsert.disabled = true;
    }
    const closeDoc = document.getElementById('closeDoc');
    if (closeDoc) closeDoc.onclick = closeDocModal;
    const cancelDoc = document.getElementById('cancelDoc');
    if (cancelDoc) cancelDoc.onclick = closeDocModal;

    if (docFile) docFile.onchange = () => { if (docFile.files.length) parseDoc(docFile.files[0]); };
    if (docDrop) {
      ['dragover', 'dragleave', 'drop'].forEach(ev => {
        docDrop.addEventListener(ev, e => {
          e.preventDefault();
          docDrop.classList.toggle('drag', ev === 'dragover');
          if (ev === 'drop' && e.dataTransfer.files.length) parseDoc(e.dataTransfer.files[0]);
        });
      });
    }

    async function parseDoc(file) {
      if (!docError) return;
      docError.classList.remove('show');
      const ext = file.name.split('.').pop().toLowerCase();
      try {
        let text = '';
        if (ext === 'txt') {
          text = await file.text();
        } else if (ext === 'docx') {
          if (!window.mammoth) throw new Error('Mammoth parser not available');
          const res = await window.mammoth.extractRawText({ arrayBuffer: await file.arrayBuffer() });
          text = res.value;
        } else {
          docError.textContent = 'Only .docx and .txt files are supported.';
          docError.classList.add('show');
          return;
        }
        showDocPreview(text, file.name);
      } catch (err) {
        docError.textContent = 'Could not parse that file: ' + err.message;
        docError.classList.add('show');
      }
    }

    function showDocPreview(text, name) {
      const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
      if (!lines.length) {
        if (docError) {
          docError.textContent = 'Document is empty.';
          docError.classList.add('show');
        }
        return;
      }
      const headline = lines[0];
      const body = lines.slice(1).join('\n\n');
      const words = text.trim().split(/\s+/).length;
      const meta = document.getElementById('docMeta');
      if (meta) meta.innerHTML = '<span><strong>' + esc(name) + '</strong></span><span>' + words.toLocaleString() + ' words</span><span>' + lines.length + ' paragraphs</span>';
      const docHead = document.getElementById('docHeadline');
      if (docHead) docHead.textContent = headline;
      const docBodyEl = document.getElementById('docBody');
      if (docBodyEl) docBodyEl.textContent = body || '(no body paragraphs)';
      pendingDoc = { headline, body, brief: body.slice(0, 280) };
      if (docPreview) docPreview.hidden = false;
      if (docInsert) docInsert.disabled = false;
    }

    if (docInsert) {
      docInsert.onclick = () => {
        if (!pendingDoc) return;
        if (headlineInput) headlineInput.value = pendingDoc.headline;
        if (briefInput) {
          briefInput.value = pendingDoc.brief;
          if (briefCount) briefCount.textContent = String(pendingDoc.brief.length);
        }
        if (quill) {
          quill.setText(pendingDoc.body + '\n');
        }
        const app = document.getElementById('addNewsApp');
        const wireEl = app ? app.closest('[wire\\:id]') : null;
        if (wireEl && window.Livewire) {
          const wireComponent = window.Livewire.find(wireEl.getAttribute('wire:id'));
          if (wireComponent) wireComponent.syncFromDoc(pendingDoc.headline, pendingDoc.body, pendingDoc.brief);
        }
        closeDocModal();
        syncPreview();
      };
    }

    // Photo Archive Modal
    const mediaOverlay = document.getElementById('mediaOverlay');
    const insertPhoto = document.getElementById('insertPhoto');
    const selectedCount = document.getElementById('selectedCount');
    let archiveMode = 'featured';

    function openArchiveModal(mode) {
      archiveMode = mode;
      document.querySelectorAll('.photo-card').forEach(c => c.classList.remove('selected'));
      if (selectedCount) selectedCount.textContent = 'No photo selected';
      if (insertPhoto) insertPhoto.disabled = true;
      mediaOverlay?.classList.add('open');
    }

    document.addEventListener('click', (e) => {
      const openArchiveBtn = e.target.closest('#openArchive');
      if (openArchiveBtn) {
        openArchiveModal('featured');
        return;
      }

      const openAttachBtn = e.target.closest('#openAttachArchive');
      if (openAttachBtn) {
        openArchiveModal('attach');
        return;
      }

      const closeBtn = e.target.closest('#closeModal') || e.target.closest('#cancelModal');
      if (closeBtn) {
        mediaOverlay?.classList.remove('open');
        return;
      }

      const card = e.target.closest('.photo-card');
      if (card) {
        if (archiveMode === 'featured') {
          document.querySelectorAll('.photo-card').forEach(c => c.classList.remove('selected'));
          card.classList.add('selected');
        } else {
          card.classList.toggle('selected');
        }
        const sel = document.querySelectorAll('.photo-card.selected');
        if (selectedCount) selectedCount.textContent = sel.length ? sel.length + ' item(s) selected' : 'No photo selected';
        const insBtn = document.getElementById('insertPhoto');
        if (insBtn) insBtn.disabled = !sel.length;
        return;
      }

      const insertBtn = e.target.closest('#insertPhoto');
      if (insertBtn) {
        const sel = [...document.querySelectorAll('.photo-card.selected')];
        if (!sel.length) return;
        const app = document.getElementById('addNewsApp');
        const wireEl = app ? app.closest('[wire\\:id]') : null;
        const wireComponent = wireEl && window.Livewire ? window.Livewire.find(wireEl.getAttribute('wire:id')) : null;

        if (archiveMode === 'featured') {
          const c = sel[0];
          const id = +c.dataset.id;
          const cap = c.dataset.cap;
          const fp = document.getElementById('featuredPreview');
          if (fp) {
            fp.className = 'featured-preview has-photo';
            fp.innerHTML = '<div class="fp-photo g' + ((id % 8) + 1) + '"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div class="fp-cap">' + esc(cap) + '</div>';
          }
          if (wireComponent) wireComponent.setFeatured(id, cap);
        } else {
          sel.forEach(c => {
            const id = +c.dataset.id;
            const cap = c.dataset.cap;
            const kind = c.dataset.type || 'photo';
            if (wireComponent) wireComponent.toggleMedia(id, cap, kind);
          });
        }
        mediaOverlay?.classList.remove('open');
      }
    });

    // Tags Autocomplete
    const tagBox = document.getElementById('tagBox');
    const tagInput = document.getElementById('tagInput');
    const tagSuggest = document.getElementById('tagSuggest');
    let hlIndex = -1;

    function renderTagSuggest(q) {
      if (!tagSuggest) return;
      const matches = SAVED_TAGS.filter(t => t[0].includes(q)).slice(0, 7);
      let html = matches.map(t =>
        '<div class="tag-opt" data-name="' + t[0] + '"><span class="hash">#' + t[0] + '</span><span class="uses">' + t[1] + ' stories</span></div>'
      ).join('');
      if (q && !SAVED_TAGS.some(t => t[0] === q)) {
        html += '<div class="tag-opt" data-name="' + q + '"><span class="hash">Create #' + q + '</span><span class="uses">new tag</span></div>';
      }
      if (!html) {
        tagSuggest.classList.remove('open');
        return;
      }
      tagSuggest.innerHTML = html;
      tagSuggest.classList.add('open');
      hlIndex = -1;
      tagSuggest.querySelectorAll('.tag-opt').forEach(opt => {
        opt.onmousedown = e => {
          e.preventDefault();
          commitTag(opt.dataset.name);
        };
      });
    }

    function commitTag(name) {
      if (!name) return;
      name = name.replace(/^#/, '').trim().toLowerCase();
      if (tagInput) tagInput.value = '';
      if (tagSuggest) tagSuggest.classList.remove('open');

      if (tagBox) {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.dataset.name = name;
        chip.innerHTML = '#' + esc(name) + ' <button type="button" aria-label="Remove">✕</button>';
        const btn = chip.querySelector('button');
        if (btn) btn.onclick = () => chip.remove();
        tagBox.insertBefore(chip, tagInput);
      }

      const app = document.getElementById('addNewsApp');
      const wireEl = app ? app.closest('[wire\\:id]') : null;
      if (wireEl && window.Livewire) {
        const wireComponent = window.Livewire.find(wireEl.getAttribute('wire:id'));
        if (wireComponent) wireComponent.addTag(name);
      }
      syncPreview();
    }

    if (tagInput) {
      tagInput.oninput = () => {
        const v = tagInput.value;
        if (v.startsWith('#')) renderTagSuggest(v.slice(1).toLowerCase());
        else if (tagSuggest) tagSuggest.classList.remove('open');
      };

      tagInput.onkeydown = e => {
        const opts = tagSuggest ? tagSuggest.querySelectorAll('.tag-opt') : [];
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          if (!opts.length) return;
          e.preventDefault();
          hlIndex = e.key === 'ArrowDown' ? (hlIndex + 1) % opts.length : (hlIndex - 1 + opts.length) % opts.length;
          opts.forEach((o, i) => o.classList.toggle('hl', i === hlIndex));
        } else if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          if (tagSuggest && tagSuggest.classList.contains('open') && hlIndex >= 0 && opts[hlIndex]) {
            commitTag(opts[hlIndex].dataset.name);
          } else if (tagInput.value.trim()) {
            commitTag(tagInput.value);
          }
        } else if (e.key === 'Escape') {
          if (tagSuggest) tagSuggest.classList.remove('open');
        }
      };
    }

    // Access Segmented Control
    document.querySelectorAll('input[name="access"]').forEach(radio => {
      radio.onchange = () => {
        const isExc = document.querySelector('input[name="access"]:checked')?.value === 'exclusive';
        document.getElementById('exclusiveOpts')?.classList.toggle('show', isExc);
        syncPreview();
      };
    });

    // Live Preview Collapsing & Fullscreen Focus
    const addGrid = document.getElementById('addGrid');
    const pvToggleBtn = document.getElementById('pvToggleBtn');
    const pvStrip = document.getElementById('pvStrip');

    function setPvCollapsed(collapsed) {
      if (!addGrid) return;
      addGrid.classList.toggle('pv-collapsed', collapsed);
      if (pvToggleBtn) pvToggleBtn.classList.toggle('off', collapsed);
      try { localStorage.setItem('unb_pv_collapsed', collapsed ? '1' : '0'); } catch (e) {}
    }

    let pvCollapsed = true;
    try {
      const savedPv = localStorage.getItem('unb_pv_collapsed');
      if (savedPv !== null) pvCollapsed = savedPv === '1';
    } catch (e) {}
    setPvCollapsed(pvCollapsed);

    if (pvToggleBtn) pvToggleBtn.onclick = () => setPvCollapsed(!addGrid.classList.contains('pv-collapsed'));
    if (pvStrip) pvStrip.onclick = () => setPvCollapsed(false);

    const pvOverlay = document.getElementById('pvOverlay');
    const pvFocus = document.getElementById('pvFocus');
    const pvExpand = document.getElementById('pvExpand');
    if (pvExpand) {
      pvExpand.onclick = () => {
        syncPreview();
        const pvBody = document.getElementById('pvBody');
        const pvFocusBody = document.getElementById('pvFocusBody');
        if (pvFocusBody && pvBody) pvFocusBody.innerHTML = pvBody.innerHTML;
        pvOverlay?.classList.add('open');
      };
    }
    const pvFocusClose = document.getElementById('pvFocusClose');
    if (pvFocusClose) pvFocusClose.onclick = () => pvOverlay?.classList.remove('open');
    document.querySelectorAll('#pvDevSeg button').forEach(b => {
      b.onclick = () => {
        document.querySelectorAll('#pvDevSeg button').forEach(x => x.classList.toggle('active', x === b));
        if (pvFocus) pvFocus.className = 'pv-focus dev-' + b.dataset.dev;
      };
    });

    // Live Preview Rendering Sync
    function syncPreview() {
      const head = headlineInput ? headlineInput.value : '';
      const sub = document.getElementById('subheadInput')?.value || '';
      const brf = briefInput ? briefInput.value : '';
      const catEl = document.getElementById('catSelect');
      const cat = catEl && catEl.selectedIndex >= 0 && catEl.value ? catEl.options[catEl.selectedIndex].text : '';
      const author = document.getElementById('authorInput')?.value || 'UNB Desk';
      const isExc = document.querySelector('input[name="access"]:checked')?.value === 'exclusive';

      const paras = quill ? quill.getText().split(/\n+/).map(p => p.trim()).filter(Boolean) : [];
      const words = paras.join(' ').split(/\s+/).filter(Boolean).length;
      const readMin = Math.max(1, Math.round(words / 200));

      const tags = [...document.querySelectorAll('.tag-chip')].map(c => c.textContent.replace('✕', '').trim());

      const pvHtml =
        '<div class="pv-card">' +
          '<div class="pv-meta">' +
            '<span class="pv-cat ' + (cat && !cat.startsWith('Select') ? '' : 'empty') + '">' + esc(cat && !cat.startsWith('Select') ? cat : 'Category') + '</span>' +
            '<span class="pv-time">Just now</span>' +
            (isExc ? ' <span class="pv-badge exc">★ Exclusive</span>' : '') +
          '</div>' +
          '<div class="pv-headline ' + (head ? '' : 'ph') + '">' + (head ? esc(head) : 'Your headline will appear here as you type…') + '</div>' +
          (sub ? '<div class="pv-subhead">' + esc(sub) + '</div>' : '') +
          '<div class="pv-brief ' + (brf ? '' : 'ph') + '">' + (brf ? esc(brf) : 'Intro / brief shown on the wire feed…') + '</div>' +
          (tags.length ? '<div class="pv-tags">' + tags.map(t => '<span class="pv-tag">' + esc(t) + '</span>').join('') + '</div>' : '') +
          '<div class="pv-actions">' +
            '<span class="pv-btn primary">Wire Feed View</span><span class="pv-btn">Copy text</span>' +
          '</div>' +
        '</div>' +
        '<div class="pv-divider"><span>Article reader view</span></div>' +
        '<div class="pv-article">' +
          '<div class="pv-art-head ' + (head ? '' : 'ph') + '">' + (head ? esc(head) : 'Article Headline…') + '</div>' +
          (sub ? '<div class="pv-art-sub">' + esc(sub) + '</div>' : '') +
          '<div class="pv-art-byline">' + esc(author) + ' · ' + esc(cat || 'Category') + ' · Just now</div>' +
          '<div class="pv-art-body">' +
            (paras.length ? paras.slice(0, 3).map(p => '<p>' + esc(p) + '</p>').join('') : '<p class="ph">Story body paragraphs will flow in here from the editor…</p>') +
          '</div>' +
          '<div class="pv-stats"><span>' + words + ' words</span><span>~' + readMin + ' min read</span></div>' +
        '</div>';

      const pvBody = document.getElementById('pvBody');
      const pvFocusBody = document.getElementById('pvFocusBody');
      if (pvBody) pvBody.innerHTML = pvHtml;
      if (pvFocusBody && document.getElementById('pvOverlay')?.classList.contains('open')) pvFocusBody.innerHTML = pvHtml;
    }

    document.querySelectorAll('#headlineInput, #subheadInput, #briefInput, #catSelect, #authorInput')
      .forEach(el => el.addEventListener('input', syncPreview));

    syncPreview();
    updateEditorStats();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('addGrid')) window.initAddNewsClientController();
    });
  } else {
    if (document.getElementById('addGrid')) window.initAddNewsClientController();
  }
  document.addEventListener('livewire:navigated', () => {
    window.__addNewsInitialized = false;
    if (document.getElementById('addGrid')) window.initAddNewsClientController();
  });
})();
