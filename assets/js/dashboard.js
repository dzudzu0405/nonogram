/* Projects dashboard: list, create, rename, duplicate, delete. */
(function () {
  'use strict';
  const E = window.NonoEngine, $ = s => document.querySelector(s);
  const box = $('#projects');
  if (!box) return;
  let projects = [];

  const editorUrl = (id, extra = '') => `${NS.base}/app/editor.php?id=${id}${extra}`;

  function levelsText(levels) {
    const order = ['Easy', 'Medium', 'Hard', 'Expert'];
    const have = order.filter(l => levels[l]);
    return have.length ? (have.length === 1 ? have[0] : have[0] + '–' + have[have.length - 1]) : '';
  }

  function card(p) {
    const thumb = p.cover ? `<img alt="" src="${Render.thumb(p.cover.w, p.cover.h, E.decodeGrid(p.cover.grid), p.cover.palette, 6)}">` : '<span class="empty">No puzzles yet</span>';
    const status = !p.count ? '<span class="chip">Empty</span>'
      : p.not_unique ? `<span class="chip warn">${p.not_unique} not unique</span>`
      : '<span class="chip ok">All puzzles checked</span>';
    return `<article class="pcard" data-id="${p.id}">
      <a class="thumb" href="${editorUrl(p.id)}" aria-label="Open ${App.esc(p.name)}">${thumb}</a>
      <div class="pbody">
        <h3><a href="${editorUrl(p.id)}">${App.esc(p.name)}</a></h3>
        <div class="pmeta"><span>${p.count} puzzle${p.count === 1 ? '' : 's'}</span>${p.sizes.length ? `<span>${p.sizes.slice(0, 3).join(', ')}${p.sizes.length > 3 ? '…' : ''}</span>` : ''}${levelsText(p.levels) ? `<span>${levelsText(p.levels)}</span>` : ''}<span>Edited ${App.fmtDate(p.updated_at)}</span></div>
        ${status}
        <div class="pactions">
          <a class="btn sm primary" href="${editorUrl(p.id)}">Open</a>
          <button class="btn sm" data-act="rename">Rename</button>
          <button class="btn sm" data-act="duplicate">Duplicate</button>
          <button class="btn sm danger" data-act="delete">Delete</button>
        </div>
      </div>
    </article>`;
  }

  function render() {
    const term = $('#filter').value.trim().toLowerCase();
    const list = projects.filter(p => !term || p.name.toLowerCase().includes(term));
    $('#stat-projects').textContent = projects.length;
    $('#stat-puzzles').textContent = projects.reduce((a, p) => a + p.count, 0);
    if (!projects.length) {
      box.innerHTML = `<div class="emptystate"><h2>Make your first puzzle project</h2><p class="muted">A project holds the puzzles for one book or worksheet set.</p><button class="btn primary" id="empty-new">New puzzle project</button></div>`;
      $('#empty-new').onclick = showNew;
      return;
    }
    box.innerHTML = list.length ? list.map(card).join('') : '<p class="muted">No projects match your search.</p>';
  }

  async function load() {
    try { projects = (await App.get('projects.php?action=list')).projects; render(); }
    catch (e) { box.innerHTML = `<p class="err-msg">${App.esc(e.error || 'Could not load projects.')}</p>`; }
  }

  async function create(name, extra) {
    try { const r = await App.post('projects.php?action=create', { name }); location.href = editorUrl(r.id, extra || ''); }
    catch (e) { App.fail(e); }
  }

  function showNew() { $('#newproj').hidden = false; $('#newname').focus(); }
  $('#qa-new').onclick = showNew;
  $('#newcancel').onclick = () => { $('#newproj').hidden = true; };
  $('#newproj').onsubmit = e => { e.preventDefault(); create($('#newname').value.trim() || 'Untitled project'); };
  $('#qa-upload').onclick = () => create('Uploaded pictures ' + new Date().toLocaleDateString(), '#upload');
  $('#filter').oninput = render;

  box.addEventListener('click', async e => {
    const btn = e.target.closest('[data-act]'); if (!btn) return;
    const el = btn.closest('.pcard'), id = +el.dataset.id, p = projects.find(x => x.id === id);
    const act = btn.dataset.act;
    if (act === 'rename') {
      const h = el.querySelector('h3');
      h.innerHTML = `<input value="${App.esc(p.name)}" maxlength="160" aria-label="Project name">`;
      const inp = h.querySelector('input'); inp.focus(); inp.select();
      let finished = false;
      const done = async save => {
        if (finished) return;
        finished = true;
        const name = inp.value.trim();
        if (save && name && name !== p.name) {
          try { await App.post('projects.php?action=rename', { id, name }); p.name = name; App.toast('Renamed'); } catch (err) { App.fail(err); }
        }
        render();
      };
      inp.onkeydown = ev => { if (ev.key === 'Enter') done(true); if (ev.key === 'Escape') done(false); };
      inp.onblur = () => done(true);
    }
    if (act === 'duplicate') {
      btn.disabled = true;
      try { await App.post('projects.php?action=duplicate', { id }); App.toast('Project duplicated'); load(); } catch (err) { App.fail(err); btn.disabled = false; }
    }
    if (act === 'delete') {
      if (!confirm(`Delete "${p.name}" and its ${p.count} puzzle${p.count === 1 ? '' : 's'}? This cannot be undone.`)) return;
      try { await App.post('projects.php?action=delete', { id }); projects = projects.filter(x => x.id !== id); render(); App.toast('Project deleted'); } catch (err) { App.fail(err); }
    }
  });

  load();
})();
