/* Projects dashboard: list, filter, sort, create, rename, duplicate, delete. */
(function () {
  'use strict';
  const E = window.NonoEngine, $ = s => document.querySelector(s);
  const box = $('#projects');
  if (!box) return;
  let projects = [], limit = 0, loaded = false;

  // Per-browser preferences (sort, layout). Storage may be blocked, so never rely on it.
  const pref = {
    get: (k, d) => { try { return localStorage.getItem('ns.' + k) || d; } catch (e) { return d; } },
    set: (k, v) => { try { localStorage.setItem('ns.' + k, v); } catch (e) { /* ignore */ } },
  };
  const ui = { show: 'all', sort: pref.get('sort', 'updated'), view: pref.get('view', 'grid') };

  const editorUrl = (id, extra = '') => `${NS.base}/app/editor.php?id=${id}${extra}`;
  const plural = (n, w) => `${n} ${w}${n === 1 ? '' : 's'}`;

  function levelsText(levels) {
    const order = ['Easy', 'Medium', 'Hard', 'Expert'];
    const have = order.filter(l => levels[l]);
    return have.length ? (have.length === 1 ? have[0] : have[0] + '–' + have[have.length - 1]) : '';
  }

  function ago(s) {
    const d = new Date(String(s).replace(' ', 'T') + 'Z');
    if (isNaN(d)) return '';
    const sec = (Date.now() - d) / 1000;
    if (sec < 60) return 'just now';
    if (sec < 3600) return plural(Math.floor(sec / 60), 'minute') + ' ago';
    if (sec < 86400) return plural(Math.floor(sec / 3600), 'hour') + ' ago';
    if (sec < 86400 * 2) return 'yesterday';
    if (sec < 86400 * 7) return Math.floor(sec / 86400) + ' days ago';
    return App.fmtDate(s);
  }

  function greet() {
    const el = $('#greet'); if (!el) return;
    const h = new Date().getHours();
    const part = h < 5 ? 'Working late' : h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    el.textContent = `${part}, ${el.dataset.name}`;
  }

  const kind = p => !p.count ? 'empty' : p.not_unique ? 'warn' : 'ok';
  function statusChip(p) {
    const k = kind(p);
    if (k === 'empty') return '<span class="chip">Empty</span>';
    if (k === 'warn') return `<span class="chip warn">${p.not_unique} to check</span>`;
    return '<span class="chip ok">✓ All unique</span>';
  }

  function thumb(p) {
    if (!p.cover) return '<span class="empty"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add puzzles</span>';
    const k = Math.max(2, Math.round(96 / Math.max(p.cover.w, p.cover.h)));
    return `<img alt="" src="${Render.thumb(p.cover.w, p.cover.h, E.decodeGrid(p.cover.grid), p.cover.palette, k)}">`;
  }

  const menu = `<div class="more">
      <button class="iconbtn" data-act="menu" aria-haspopup="true" aria-expanded="false" aria-label="More actions"><svg class="ic" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button>
      <div class="menu" role="menu" hidden>
        <button role="menuitem" data-act="rename">Rename</button>
        <button role="menuitem" data-act="duplicate">Duplicate</button>
        <button role="menuitem" data-act="delete" class="danger">Delete</button>
      </div>
    </div>`;

  function card(p) {
    const name = App.esc(p.name);
    const meta = [plural(p.count, 'puzzle'), p.sizes.length ? p.sizes.slice(0, 2).join(', ') + (p.sizes.length > 2 ? '…' : '') : '', levelsText(p.levels)]
      .filter(Boolean).map(t => `<span>${App.esc(t)}</span>`).join('');
    return `<article class="pcard ${kind(p)}" data-id="${p.id}">
      <a class="thumb" href="${editorUrl(p.id)}" tabindex="-1" aria-hidden="true">${thumb(p)}${p.count ? `<span class="count">${p.count}</span>` : ''}</a>
      <div class="pbody">
        <div class="ptitle"><h3><a href="${editorUrl(p.id)}">${name}</a></h3>${menu}</div>
        <div class="pmeta">${meta}</div>
        <div class="pfoot">${statusChip(p)}<span class="edited" title="${App.esc(p.updated_at)} UTC">${ago(p.updated_at)}</span></div>
      </div>
    </article>`;
  }

  function visible() {
    const term = $('#filter').value.trim().toLowerCase();
    const list = projects.filter(p => (!term || p.name.toLowerCase().includes(term)) && (ui.show === 'all' || kind(p) === ui.show));
    const by = {
      updated: (a, b) => (b.updated_at > a.updated_at) - (b.updated_at < a.updated_at) || b.id - a.id,
      created: (a, b) => (b.created_at > a.created_at) - (b.created_at < a.created_at) || b.id - a.id,
      name: (a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }),
      count: (a, b) => b.count - a.count || a.name.localeCompare(b.name),
    }[ui.sort];
    return list.sort(by);
  }

  function renderStats() {
    const n = { all: projects.length, warn: 0, empty: 0 }; let puzzles = 0, toCheck = 0;
    projects.forEach(p => { puzzles += p.count; toCheck += p.not_unique; if (kind(p) !== 'ok') n[kind(p)]++; });
    $('#stat-projects').textContent = projects.length;
    $('#stat-meter').style.width = Math.min(100, limit ? projects.length / limit * 100 : 0) + '%';
    $('#stat-puzzles').textContent = puzzles;
    $('#stat-warn').textContent = toCheck;
    $('#stat-check').classList.toggle('warn', toCheck > 0);
    $('#stat-check').disabled = !toCheck;
    $('#stat-warn-note').textContent = toCheck ? `in ${plural(n.warn, 'project')} · click to show` : 'every puzzle has one solution';
    for (const k in n) $('#n-' + k).textContent = n[k];
  }

  function render() {
    if (!loaded) return;
    renderStats();
    box.classList.toggle('list', ui.view === 'list');
    $('.toolbar').hidden = !projects.length;
    if (!projects.length) {
      box.innerHTML = `<div class="emptystate">
        <h2>Make your first puzzle book</h2>
        <p class="muted">It takes three steps. No drawing skills needed.</p>
        <ol class="steps">
          <li><b>1</b><span><strong>Create a project</strong>One project holds the puzzles for one book.</span></li>
          <li><b>2</b><span><strong>Add pictures</strong>Pick a starter picture or upload your own. Each one becomes a puzzle.</span></li>
          <li><b>3</b><span><strong>Export the PDF</strong>Puzzles and solutions, ready for KDP or Etsy.</span></li>
        </ol>
        <div class="row-actions"><button class="btn lg primary" id="empty-new">New project</button><button class="btn lg" id="empty-upload">Upload images</button></div>
      </div>`;
      $('#empty-new').onclick = () => openName();
      $('#empty-upload').onclick = uploadNew;
      return;
    }
    const list = visible();
    if (list.length) { box.innerHTML = list.map(card).join(''); return; }
    const why = $('#filter').value.trim() ? `No projects match “${App.esc($('#filter').value.trim())}”.` : 'No projects in this view.';
    box.innerHTML = `<div class="nomatch"><p>${why}</p><button class="btn" id="clear-filter">Show all projects</button></div>`;
    $('#clear-filter').onclick = () => { $('#filter').value = ''; setShow('all'); };
  }

  async function load() {
    try {
      const r = await App.get('projects.php?action=list');
      projects = r.projects; limit = r.limit; loaded = true; render();
    } catch (e) {
      box.innerHTML = `<div class="nomatch"><p class="err-msg">${App.esc(e.error || 'Could not load projects.')}</p><button class="btn" id="retry">Try again</button></div>`;
      $('#retry').onclick = load;
    }
  }

  /* ---------- dialogs ---------- */

  const dlgName = $('#dlg-name'), nameInput = $('#dlg-name-input');
  let nameAction = null;
  function openName(p) {
    const renaming = !!p;
    $('#dlg-name-title').textContent = renaming ? 'Rename project' : 'New project';
    $('#dlg-name-ok').textContent = renaming ? 'Save name' : 'Create project';
    $('#dlg-suggest').hidden = renaming;
    nameInput.value = renaming ? p.name : '';
    nameAction = renaming ? name => rename(p, name) : name => create(name);
    dlgName.showModal(); nameInput.focus(); nameInput.select();
  }
  // Act on submit rather than on the dialog's close event, which some browsers deliver late or not at all.
  dlgName.querySelector('form').addEventListener('submit', e => {
    e.preventDefault();
    const name = nameInput.value.trim();
    if (!name) { nameInput.focus(); return; }
    dlgName.close();
    if (nameAction) nameAction(name);
  });
  $('#dlg-suggest').addEventListener('click', e => { const b = e.target.closest('.chip'); if (b) { nameInput.value = b.textContent; nameInput.focus(); } });

  const dlgConfirm = $('#dlg-confirm');
  let confirmAction = null;
  function confirmDelete(p) {
    $('#dlg-confirm-title').textContent = `Delete “${p.name}”?`;
    $('#dlg-confirm-text').textContent = p.count ? `Its ${plural(p.count, 'puzzle')} will be deleted too. This cannot be undone.` : 'This project is empty. This cannot be undone.';
    confirmAction = () => remove(p);
    dlgConfirm.showModal();
  }
  dlgConfirm.querySelector('form').addEventListener('submit', e => { e.preventDefault(); dlgConfirm.close(); if (confirmAction) confirmAction(); });
  document.querySelectorAll('.dlg [data-close]').forEach(b => { b.onclick = () => b.closest('dialog').close(); });
  // Click on the dimmed backdrop closes the dialog.
  document.querySelectorAll('.dlg').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));

  /* ---------- actions ---------- */

  async function create(name, extra) {
    try { const r = await App.post('projects.php?action=create', { name }); location.href = editorUrl(r.id, extra || ''); }
    catch (e) { App.fail(e); }
  }
  const uploadNew = () => create('Uploaded pictures ' + new Date().toLocaleDateString(), '#upload');

  async function rename(p, name) {
    if (name === p.name) return;
    try { await App.post('projects.php?action=rename', { id: p.id, name }); p.name = name; render(); App.toast('Renamed'); }
    catch (e) { App.fail(e); }
  }

  async function duplicate(p) {
    try { await App.post('projects.php?action=duplicate', { id: p.id }); App.toast('Project duplicated'); load(); }
    catch (e) { App.fail(e); }
  }

  async function remove(p) {
    try { await App.post('projects.php?action=delete', { id: p.id }); projects = projects.filter(x => x.id !== p.id); render(); App.toast('Project deleted'); }
    catch (e) { App.fail(e); }
  }

  /* ---------- menus and controls ---------- */

  function closeMenus(except) {
    box.querySelectorAll('.menu:not([hidden])').forEach(m => {
      if (m === except) return;
      m.hidden = true; m.previousElementSibling.setAttribute('aria-expanded', 'false');
    });
  }

  box.addEventListener('click', e => {
    const btn = e.target.closest('[data-act]');
    const el = e.target.closest('.pcard');
    if (!el || el.classList.contains('skeleton')) return;
    const p = projects.find(x => x.id === +el.dataset.id);
    if (!btn) {
      // Clicking anywhere on the card (outside links and menus) opens the project.
      if (!e.target.closest('a, .more')) location.href = editorUrl(p.id);
      return;
    }
    const act = btn.dataset.act;
    if (act === 'menu') {
      const m = btn.nextElementSibling, open = m.hidden;
      closeMenus(m); m.hidden = !open; btn.setAttribute('aria-expanded', String(open));
      if (open) m.querySelector('button').focus();
      return;
    }
    closeMenus();
    if (act === 'rename') openName(p);
    if (act === 'duplicate') duplicate(p);
    if (act === 'delete') confirmDelete(p);
  });
  document.addEventListener('click', e => { if (!e.target.closest('.more')) closeMenus(); });

  function setShow(v) {
    ui.show = v;
    document.querySelectorAll('.seg [data-show]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.show === v)));
    render();
  }
  document.querySelectorAll('.seg [data-show]').forEach(b => { b.onclick = () => setShow(b.dataset.show); });
  $('#stat-check').onclick = () => { setShow('warn'); box.scrollIntoView({ behavior: 'smooth', block: 'start' }); };

  function setView(v) {
    ui.view = v; pref.set('view', v);
    document.querySelectorAll('.viewtoggle [data-view]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.view === v)));
    render();
  }
  document.querySelectorAll('.viewtoggle [data-view]').forEach(b => { b.onclick = () => setView(b.dataset.view); });

  $('#sort').value = ui.sort;
  $('#sort').onchange = () => { ui.sort = $('#sort').value; pref.set('sort', ui.sort); render(); };
  $('#filter').oninput = render;
  $('#qa-new').onclick = () => openName();
  $('#qa-upload').onclick = uploadNew;

  // Shortcuts: "/" searches, "N" starts a new project, Escape closes menus or clears the search.
  document.addEventListener('keydown', e => {
    const typing = e.target.closest('input, textarea, select, [contenteditable]');
    if (e.key === 'Escape') {
      closeMenus();
      if (e.target === $('#filter') && $('#filter').value) { $('#filter').value = ''; render(); }
      return;
    }
    if (typing || e.ctrlKey || e.metaKey || e.altKey || document.querySelector('dialog[open]')) return;
    if (e.key === '/') { e.preventDefault(); $('#filter').focus(); }
    if (e.key === 'n' || e.key === 'N') { e.preventDefault(); openName(); }
  });

  greet();
  setView(ui.view);
  load();
})();
