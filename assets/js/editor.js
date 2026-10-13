/* Puzzle editor: picture -> puzzle, check, edit, save into the project, export. */
(function () {
  'use strict';
  const E = window.NonoEngine, R = window.Render, P = window.Pictures;
  const $ = s => document.querySelector(s);
  const F = NS.features;
  const projectId = +document.querySelector('.editor').dataset.project;

  const state = {
    puzzles: [], currentId: null, dirty: false,
    src: null, base: null,                       // either a picture source, or a saved grid being edited
    W: 15, Hsel: 'auto', thr: 150, ct: 0, br: 0, invert: false, clean: true, colors: 1,
    edits: new Map(), sizeKey: '', p: null, s: null, tab: 'puzzle', coll: 'starter', pic: 'Heart',
  };
  const PROCESS_CONTROLS = ['#w', '#h', '#colors', '#thr', '#ct', '#br', '#inv', '#clean'];

  /* ---------- Build + render ---------- */
  let checkTimer = 0, gen = 0, fixing = false;
  function rebuild() {
    gen++;
    let base;
    if (state.src) {
      base = E.gridFromSource(state.src, { W: state.W, Hsel: state.Hsel, max: F.max_grid, thr: state.thr, invert: state.invert, colors: state.colors, clean: state.clean, contrast: state.ct, brightness: state.br });
    } else {
      base = { W: state.base.W, H: state.base.H, g: state.base.g.slice(), palette: state.base.palette.slice() };
    }
    state.fitted = !!base.fitted;
    const key = base.W + 'x' + base.H + '|' + base.palette.join();
    if (key !== state.sizeKey) { state.edits.clear(); state.sizeKey = key; }
    for (const [i, v] of state.edits) base.g[i] = Math.min(v, base.palette.length);
    state.p = E.makePuzzle(base.W, base.H, base.g, base.palette);
    state.s = E.solve(state.p);
    state.checked = state.s.lineSolvable || !!state.s.bad;
    render();
    clearTimeout(checkTimer);
    if (!state.checked) checkTimer = setTimeout(() => { ensureChecked(); render(); }, 250);
  }

  // When line logic cannot finish, a bounded search decides whether there is still exactly one solution.
  // It can take a moment on big grids, so it waits until the controls stop moving; saving and exporting
  // call this first so they never use an unchecked result.
  function ensureChecked() {
    if (state.checked) return;
    clearTimeout(checkTimer);
    state.s = E.analyze(state.p); state.checked = true;
  }

  function render() {
    const p = state.p, s = state.s, cv = $('#cv'), stage = $('#stage');
    const pad = Math.max(10, Math.min(24, stage.clientWidth * 0.03));
    const uw = p.W + p.maxR * 0.8, uh = p.H + p.maxC * 0.8;
    const cell = Math.max(6, Math.min(30, (stage.clientWidth - 32 - pad * 2) / uw, (Math.min(innerHeight * 0.7, 720) - pad * 2) / uh));
    const w = uw * cell + pad * 2, h = uh * cell + pad * 2, dpr = devicePixelRatio || 1;
    cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr); cv.style.width = w + 'px'; cv.style.height = h + 'px';
    const x = cv.getContext('2d'); x.setTransform(dpr, 0, 0, dpr, 0, 0); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h);
    const sol = state.tab === 'sol';
    const { gx, gy } = R.drawPuzzle(R.canvasR(x), p, pad, pad, cell, { solution: sol, st: sol ? s.st : null, thin: 1, thick: 2 });
    state.hit = { gx, gy, cell };
    cv.classList.toggle('edit', sol);

    const lg = $('#legend'); lg.hidden = !sol;
    const ambLabel = s.unique ? 'Needs advanced logic' : 'Undecided by logic';
    lg.innerHTML = p.palette.map((c, i) => `<span><i style="background:${c}"></i>${p.palette.length > 1 ? 'Color ' + (i + 1) : 'Filled'}</span>`).join('') + (s.lineSolvable ? '' : `<span><i style="background:${R.PAPER.amb}"></i>${ambLabel}</span>`);
    const filled = p.g.reduce((a, b) => a + (b ? 1 : 0), 0);
    const chips = [];
    if (!filled) chips.push('<span class="chip warn">Empty grid: raise the cut-off</span>');
    else if (!state.checked) chips.push('<span class="chip">Checking for one solution…</span>');
    else if (s.lineSolvable) chips.push('<span class="chip ok">✓ One solution, no guessing</span>');
    else if (s.unique) chips.push('<span class="chip ok" title="Solvers need to try a cell and follow it through. Fine for Expert books.">✓ One solution · needs advanced logic</span>');
    else if (s.multiple) chips.push(`<span class="chip warn">More than one solution: ${s.und} cells undecided</span>`);
    else chips.push(`<span class="chip warn" title="The puzzle is too open to confirm one solution. Auto-fix makes it solvable.">Could not confirm one solution: ${s.und} cells need guessing</span>`);
    if (filled && state.checked && s.unique) chips.push(`<span class="chip">${E.level(E.score(s, p))}</span>`);
    if (p.palette.length > 1) chips.push(`<span class="chip">${p.palette.length} colors</span>`);
    if (state.fitted) chips.push(`<span class="chip warn" title="The picture is taller than your plan's limit, so the width was reduced to keep its shape.">Width ${p.W}: fitted to your ${F.max_grid}×${F.max_grid} limit</span>`);
    const pl = R.printLayout(p, $('#trim').value);
    if (filled && pl.small) chips.push(`<span class="chip warn" title="Cells under ${R.MIN_CELL_MM} mm are hard to fill in with a pencil. Pick a bigger page or a smaller grid.">Small in print: ${pl.mm.toFixed(1)} mm cells on this page</span>`);
    $('#chips').innerHTML = chips.join('');
    $('#stats').textContent = `${p.W} × ${p.H} · ${filled} filled (${Math.round(filled / (p.W * p.H) * 100)}%) · ${s.rounds || 0} logic passes${state.edits.size ? ` · ${state.edits.size} edits` : ''}`;
    $('#fix').disabled = fixing || !filled || s.lineSolvable;
    $('#fix').textContent = fixing ? 'Fixing…' : s.unique && !s.lineSolvable ? 'Remove the guessing' : 'Auto-fix to one solution';
  }

  function markDirty() { state.dirty = true; }
  function setProcessingEnabled(on) {
    PROCESS_CONTROLS.forEach(s => { $(s).disabled = !on; });
    if (on) $('#inv').disabled = state.colors > 1;
    $('#savednote').hidden = on;
  }
  function updateSaveButtons() {
    $('#save').textContent = state.currentId ? 'Update puzzle' : 'Save to project';
    $('#saveas').hidden = !state.currentId;
    document.querySelectorAll('.pitem').forEach(li => li.setAttribute('aria-current', String(+li.dataset.id === state.currentId)));
  }

  /* ---------- Sources ---------- */
  function setSource(src, name) {
    state.src = src; state.base = null; state.sizeKey = '';
    $('#pname').value = name;
    setProcessingEnabled(true);
    rebuild(); markDirty();
  }
  function setPic(k) {
    state.pic = k; setSource(P.canvas(k), k);
    document.querySelectorAll('#samples button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.k === k)));
  }
  function renderSamples() {
    const box = $('#samples'); box.innerHTML = '';
    for (const k of Object.keys(P.PICS[state.coll])) {
      const b = document.createElement('button'); b.type = 'button'; b.dataset.k = k; b.title = k;
      b.setAttribute('aria-label', 'Use ' + k); b.setAttribute('aria-pressed', String(k === state.pic));
      const t = document.createElement('canvas'); t.width = t.height = 60; t.getContext('2d').drawImage(P.canvas(k), 0, 0, 60, 60);
      const im = document.createElement('img'); im.src = t.toDataURL(); im.alt = ''; b.appendChild(im);
      b.onclick = () => { if (confirmDiscard()) { state.currentId = null; updateSaveButtons(); setPic(k); } };
      box.appendChild(b);
    }
  }
  function loadFile(f) {
    return new Promise((ok, no) => {
      if (f.size > 10 * 1024 * 1024) return no(new Error(f.name + ' is larger than 10 MB.'));
      const r = new FileReader();
      r.onload = () => { const im = new Image(); im.onload = () => ok(im); im.onerror = () => no(new Error('Could not read ' + f.name)); im.src = r.result; };
      r.onerror = () => no(new Error('Could not read ' + f.name));
      r.readAsDataURL(f);
    });
  }
  async function handleFiles(files) {
    files = [...files].filter(f => /^image\/(png|jpe?g|webp)$/.test(f.type));
    if (!files.length) { App.toast('Choose PNG, JPG or WEBP images.', 'err'); return; }
    if (!confirmDiscard()) return;
    state.pic = null; document.querySelectorAll('#samples button').forEach(b => b.setAttribute('aria-pressed', 'false'));
    state.currentId = null; updateSaveButtons();
    if (files.length === 1) {
      try { setSource(await loadFile(files[0]), files[0].name.replace(/\.[^.]+$/, '')); } catch (e) { App.toast(e.message, 'err'); }
      return;
    }
    // Several images: turn each into a checked puzzle and save it straight into the project.
    let saved = 0, dupes = 0, failed = 0;
    for (let i = 0; i < files.length; i++) {
      App.toast(`Processing ${i + 1} of ${files.length}…`);
      await new Promise(r => setTimeout(r, 0));
      try {
        setSource(await loadFile(files[i]), files[i].name.replace(/\.[^.]+$/, ''));
        if (!state.s.lineSolvable) await autoFixNow();
        const r = await save(true, false, true);
        if (r === 'dup') dupes++; else if (r) saved++; else failed++;
      } catch (e) { failed++; }
    }
    state.currentId = null; updateSaveButtons();
    App.toast(`Saved ${saved} puzzle${saved === 1 ? '' : 's'}` + (dupes ? `, skipped ${dupes} duplicate${dupes === 1 ? '' : 's'}` : '') + (failed ? `, ${failed} failed` : ''), failed ? 'err' : '');
  }

  /* ---------- Saved puzzles ---------- */
  function puzzleFromSaved(pz) { return E.makePuzzle(pz.w, pz.h, E.decodeGrid(pz.grid), pz.palette); }
  function loadSaved(pz) {
    if (!confirmDiscard()) return;
    state.src = null; state.base = { W: pz.w, H: pz.h, g: E.decodeGrid(pz.grid), palette: pz.palette.slice() };
    state.sizeKey = ''; state.currentId = pz.id; state.pic = null;
    document.querySelectorAll('#samples button').forEach(b => b.setAttribute('aria-pressed', 'false'));
    $('#pname').value = pz.name;
    setProcessingEnabled(false);
    rebuild(); state.dirty = false; updateSaveButtons();
  }
  function confirmDiscard() {
    if (!state.dirty || !state.currentId) return true;
    return confirm('You have unsaved changes to this puzzle. Discard them?');
  }

  function renderList() {
    const ol = $('#plist'), n = state.puzzles.length;
    $('#count').textContent = `${n} / ${F.max_book}`;
    $('#projmeta').textContent = n ? `${n} puzzle${n === 1 ? '' : 's'}` : 'No puzzles saved yet';
    if (!n) { ol.innerHTML = '<li class="muted small">Saved puzzles appear here. Make one on the left, then press "Save to project".</li>'; return; }
    ol.innerHTML = state.puzzles.map((pz, i) => `<li class="pitem" data-id="${pz.id}" aria-current="${pz.id === state.currentId}" tabindex="0">
      <img alt="" src="${R.thumb(pz.w, pz.h, E.decodeGrid(pz.grid), pz.palette, 3)}">
      <div style="min-width:0"><div class="nm">${i + 1}. ${App.esc(pz.name)}</div><div class="mt ${pz.unique ? '' : 'warnc'}">${pz.w}×${pz.h} · ${pz.unique ? pz.difficulty : 'not unique'}</div></div>
      <div class="ops"><button data-op="up" aria-label="Move up" ${i === 0 ? 'disabled' : ''}>↑</button><button data-op="down" aria-label="Move down" ${i === n - 1 ? 'disabled' : ''}>↓</button><button data-op="del" class="del" aria-label="Delete ${App.esc(pz.name)}">×</button></div>
    </li>`).join('');
  }

  async function save(asNew, force, quiet) {
    const p = state.p;
    if (!p.g.some(v => v)) { App.toast('The grid is empty. Raise the cut-off or pick another picture.', 'err'); return false; }
    ensureChecked();
    const sc = E.score(state.s, p);
    const body = {
      project_id: projectId, id: asNew ? 0 : state.currentId || 0,
      name: $('#pname').value.trim() || 'Puzzle', w: p.W, h: p.H,
      grid: E.encodeGrid(p.g), palette: p.palette,
      difficulty: E.level(sc), score: Math.min(99, sc), unique: state.s.unique,
      hash: E.fingerprint(p.W, p.H, p.g), force: !!force,
    };
    try {
      const r = await App.post('puzzles.php?action=save', body);
      const i = state.puzzles.findIndex(x => x.id === r.puzzle.id);
      if (i >= 0) state.puzzles[i] = r.puzzle; else state.puzzles.push(r.puzzle);
      state.currentId = r.puzzle.id; state.dirty = false;
      renderList(); updateSaveButtons();
      if (!quiet) App.toast(i >= 0 ? 'Puzzle updated' : 'Saved to project');
      return true;
    } catch (e) {
      if (e.status === 409 && e.duplicate) {
        if (quiet) return 'dup';
        if (confirm(e.error + '\n\nSave it anyway?')) return save(asNew, true);
        return false;
      }
      App.fail(e); return false;
    }
  }

  $('#plist').addEventListener('click', async e => {
    const li = e.target.closest('.pitem'); if (!li) return;
    const id = +li.dataset.id, idx = state.puzzles.findIndex(x => x.id === id), pz = state.puzzles[idx];
    const op = e.target.closest('[data-op]');
    if (!op) { loadSaved(pz); return; }
    if (op.dataset.op === 'del') {
      if (!confirm(`Delete "${pz.name}" from this project?`)) return;
      try {
        await App.post('puzzles.php?action=delete', { id });
        state.puzzles.splice(idx, 1);
        if (state.currentId === id) { state.currentId = null; state.dirty = false; updateSaveButtons(); }
        renderList(); App.toast('Puzzle deleted');
      } catch (err) { App.fail(err); }
      return;
    }
    const j = op.dataset.op === 'up' ? idx - 1 : idx + 1;
    if (j < 0 || j >= state.puzzles.length) return;
    [state.puzzles[idx], state.puzzles[j]] = [state.puzzles[j], state.puzzles[idx]];
    renderList();
    try { await App.post('puzzles.php?action=reorder', { project_id: projectId, ids: state.puzzles.map(x => x.id) }); } catch (err) { App.fail(err); }
  });
  $('#plist').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.classList.contains('pitem')) e.target.click(); });

  /* ---------- Auto-fix ---------- */
  // Runs in a Web Worker so big grids do not freeze the page; falls back to the main thread if workers fail.
  let worker = null, fixSeq = 0;
  function runFix(p) {
    const maxIt = Math.max(30, Math.ceil(p.W * p.H / 40));
    const local = () => { const flips = []; E.fixGrid(p.W, p.H, p.g, p.palette, maxIt, 36, (i, v) => flips.push([i, v])); return flips; };
    if (!window.Worker) return Promise.resolve(local());
    return new Promise(resolve => {
      try { worker = worker || new Worker(`${NS.base}/assets/js/fix-worker.js?v=${encodeURIComponent(NS.version)}`); } catch (e) { resolve(local()); return; }
      const id = ++fixSeq;
      const done = flips => { worker && worker.removeEventListener('message', onMsg); worker && worker.removeEventListener('error', onErr); resolve(flips); };
      const onMsg = e => { if (e.data.id === id) done(e.data.error ? local() : e.data.flips); };
      const onErr = e => { e.preventDefault(); worker.terminate(); worker = null; done(local()); };
      worker.addEventListener('message', onMsg); worker.addEventListener('error', onErr);
      worker.postMessage({ id, W: p.W, H: p.H, g: Array.from(p.g), palette: p.palette, maxIt });
    });
  }
  /** Returns {flips} or null when the picture changed while the fix was running. */
  async function autoFixNow() {
    const p = state.p, myGen = gen;
    fixing = true; render();
    const flips = await runFix(p);
    fixing = false;
    if (gen !== myGen) { render(); return null; }
    for (const [i, v] of flips) state.edits.set(i, v);
    rebuild(); ensureChecked(); render();
    return { flips: new Set(flips.map(f => f[0])).size };
  }
  $('#fix').onclick = async () => {
    const r = await autoFixNow();
    if (!r) { App.toast('The picture changed while fixing. Press Auto-fix again.', 'err'); return; }
    markDirty();
    const ok = state.s.lineSolvable;
    App.toast(ok ? `Fixed by changing ${r.flips} cell${r.flips === 1 ? '' : 's'}` : 'Could not fully fix. Try another size or cut-off.', ok ? '' : 'err');
  };

  /* ---------- Controls ---------- */
  const onCtl = (sel, fn) => $(sel).addEventListener('input', e => { fn(e.target); markDirty(); rebuild(); });
  onCtl('#w', t => { state.W = +t.value; });
  onCtl('#h', t => { state.Hsel = t.value; });
  onCtl('#colors', t => { state.colors = +t.value; $('#inv').disabled = state.colors > 1; if (state.colors > 1) { state.invert = false; $('#inv').checked = false; } });
  onCtl('#thr', t => { state.thr = +t.value; $('#thrv').textContent = t.value; state.edits.clear(); });
  onCtl('#ct', t => { state.ct = +t.value; $('#ctv').textContent = t.value; state.edits.clear(); });
  onCtl('#br', t => { state.br = +t.value; $('#brv').textContent = t.value; state.edits.clear(); });
  onCtl('#inv', t => { state.invert = t.checked; state.edits.clear(); });
  onCtl('#clean', t => { state.clean = t.checked; state.edits.clear(); });
  $('#coll').onchange = e => { state.coll = e.target.value; renderSamples(); };

  const setTab = t => { state.tab = t; $('#t-puzzle').setAttribute('aria-selected', t === 'puzzle'); $('#t-sol').setAttribute('aria-selected', t === 'sol'); render(); };
  $('#t-puzzle').onclick = () => setTab('puzzle');
  $('#t-sol').onclick = () => setTab('sol');
  $('#cv').addEventListener('click', e => {
    if (state.tab !== 'sol') return;
    const r = e.currentTarget.getBoundingClientRect(), { gx, gy, cell } = state.hit, p = state.p;
    const c = Math.floor((e.clientX - r.left - gx) / cell), rw = Math.floor((e.clientY - r.top - gy) / cell);
    if (c < 0 || rw < 0 || c >= p.W || rw >= p.H) return;
    const i = rw * p.W + c; state.edits.set(i, (p.g[i] + 1) % (p.palette.length + 1));
    markDirty(); rebuild();
  });

  const drop = $('#drop');
  $('#file').onchange = e => { handleFiles(e.target.files); e.target.value = ''; };
  drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#file').click(); } });
  for (const el of [drop, $('#stage')]) {
    el.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
    el.addEventListener('dragleave', () => drop.classList.remove('over'));
    el.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); handleFiles(e.dataTransfer.files); });
  }

  $('#save').onclick = () => save(false);
  $('#saveas').onclick = () => save(true);
  $('#newpuzzle').onclick = () => { if (!confirmDiscard()) return; state.currentId = null; updateSaveButtons(); setPic(Object.keys(P.PICS[state.coll])[0]); };

  /* ---------- Project name + export ---------- */
  let lastName = $('#projname').value;
  $('#projname').addEventListener('change', async e => {
    const name = e.target.value.trim();
    if (!name) { e.target.value = lastName; return; }
    try { await App.post('projects.php?action=rename', { id: projectId, name }); lastName = name; document.title = name + ' · Nonogram Studio'; App.toast('Project renamed'); }
    catch (err) { App.fail(err); e.target.value = lastName; }
  });
  $('#trim').onchange = () => { render(); App.post('projects.php?action=settings', { id: projectId, settings: { page: $('#trim').value } }).catch(App.fail); };

  const pdfReady = () => { if (!window.jspdf) { App.toast('The PDF library did not load. Check your connection and reload.', 'err'); return false; } return true; };
  // Embedded font for the PDF; if it cannot load, export still works with the basic font.
  async function pdfFonts(btn) {
    btn.disabled = true;
    try { await R.loadPdfFonts(); } catch (e) { App.toast('Could not load the PDF font: names with accents may not print. Check your connection.', 'err'); }
    btn.disabled = false;
  }
  $('#export-project').onclick = async () => {
    if (!state.puzzles.length) { App.toast('Save at least one puzzle to this project first.', 'err'); return; }
    if (!pdfReady()) return;
    const page = $('#trim').value;
    const items = state.puzzles.map(pz => ({ name: pz.name, p: puzzleFromSaved(pz), score: pz.score }));
    const small = items.filter(it => R.printLayout(it.p, page).small).length;
    if (small && !confirm(`${small} of ${items.length} puzzles will print with cells smaller than ${R.MIN_CELL_MM} mm on this page size, which is hard to fill in. Export anyway?`)) return;
    await pdfFonts($('#export-project'));
    R.buildPDF(items, { page }).save(App.slug($('#projname').value) + '.pdf');
  };
  $('#pdf1').onclick = async () => {
    if (!pdfReady()) return;
    ensureChecked(); render();
    await pdfFonts($('#pdf1'));
    R.buildPDF([{ name: $('#pname').value, p: state.p, score: E.score(state.s, state.p) }], { page: $('#trim').value }).save(App.slug($('#pname').value) + '-nonogram.pdf');
  };
  $('#png').onclick = () => $('#cv').toBlob(b => App.download(b, App.slug($('#pname').value) + (state.tab === 'sol' ? '-solution' : '') + '.png'));

  window.addEventListener('beforeunload', e => { if (state.dirty && state.currentId) { e.preventDefault(); e.returnValue = ''; } });
  let rt; addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(render, 120); });

  /* ---------- Start ---------- */
  renderSamples();
  setPic('Heart'); state.dirty = false;
  if (location.hash === '#upload') { drop.classList.add('pulse'); drop.focus(); App.toast('Choose or drop your images to start.'); }
  App.get('projects.php?action=get&id=' + projectId).then(r => {
    state.puzzles = r.project.puzzles;
    if (r.project.settings && r.project.settings.page) $('#trim').value = r.project.settings.page;
    renderList();
  }).catch(App.fail);
  document.fonts && document.fonts.ready.then(render);
})();
