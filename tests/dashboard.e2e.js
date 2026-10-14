/* Browser end-to-end test for the Dashboard (app/index.php), My Projects (app/projects.php) and the app shell.
 *
 * How to run (local dev server only, never on the live site):
 *   1. php tests/seed-users.php, then php -S 127.0.0.1:8080 dev-router.php
 *   2. Open http://127.0.0.1:8080/login.php in Chrome or Edge, open DevTools > Console.
 *   3. Paste this whole file, then run:
 *        await runDashboardE2E(<the "accounts" array from tests/dev-accounts.json>)
 *
 * The dashboard is driven inside an iframe, so page navigations (open project, create, 401) can be checked
 * without stopping the script. Every project it makes starts with "E2E " and is deleted at the end.
 * Test case IDs match tests/TEST-CASES-dashboard.md.
 */
async function runDashboardE2E(accounts) {
  'use strict';
  // Two runs at once would log each other out.
  if (window.__dashboardE2ERunning) throw new Error('A dashboard E2E run is already in progress.');
  window.__dashboardE2ERunning = true;
  try { return await runAll(accounts); } finally { window.__dashboardE2ERunning = false; }
}

async function runAll(accounts) {
  const base = location.origin;
  const pw = Object.fromEntries(accounts.map(a => [a.email, a.password]));
  const ADMIN = 'admin@nonogram.test', FE = 'fe@nonogram.test', NONE = 'nolicense@nonogram.test';
  const results = [], jsErrors = [];
  let W, D;

  /* ---------- helpers ---------- */
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const ok = (c, m) => { if (!c) throw new Error(m); };
  const eq = (a, b, m) => { if (JSON.stringify(a) !== JSON.stringify(b)) throw new Error(`${m}: expected ${JSON.stringify(b)}, got ${JSON.stringify(a)}`); };
  async function until(fn, what, ms = 4000) {
    const t0 = Date.now();
    while (Date.now() - t0 < ms) { try { const v = fn(); if (v) return v; } catch (e) { /* retry */ } await sleep(40); }
    throw new Error('Timed out waiting for ' + what);
  }
  class Skip extends Error {}
  async function T(id, name, fn) {
    try { await fn(); results.push({ id, name, result: 'PASS', note: '' }); }
    catch (e) { results.push({ id, name, result: e instanceof Skip ? 'SKIP' : 'FAIL', note: String(e.message || e) }); }
  }

  const fr = document.createElement('iframe');
  fr.style.cssText = 'position:fixed;right:8px;bottom:8px;width:1100px;height:760px;border:3px solid #e11;z-index:2147483647;background:#fff';
  document.body.appendChild(fr);
  const nextLoad = () => new Promise(r => fr.addEventListener('load', r, { once: true }));
  function attach() {
    W = fr.contentWindow; D = W.document;
    W.addEventListener('error', e => jsErrors.push(e.message));
    W.addEventListener('unhandledrejection', e => jsErrors.push('unhandled: ' + (e.reason && (e.reason.error || e.reason.message) || e.reason)));
  }
  // Most tests drive My Projects; the summary, greeting and recent cards are on the Dashboard (/app/).
  async function open(path = '/app/projects.php', width = 1100) {
    fr.style.width = width + 'px';
    const l = nextLoad(); fr.src = base + path + (path.includes('?') ? '&' : '?') + 't=' + Date.now(); await l; attach();
    if (D.querySelector('#projects')) await until(() => !D.querySelector('#projects .skeleton'), 'projects to load');
  }
  async function navAfter(action) { const l = nextLoad(); action(); await Promise.race([l, sleep(5000).then(() => { throw new Error('no navigation'); })]); attach(); return W.location; }

  // Logout is POST-only with the CSRF token (a GET does nothing).
  async function logout() {
    const html = await (await fetch(base + '/app/settings.php')).text();
    const m = html.match(/name="csrf" value="([^"]+)"/);
    if (m) await fetch(base + '/logout.php', { method: 'POST', body: new URLSearchParams({ csrf: m[1] }), redirect: 'manual' });
  }
  async function login(email) {
    await logout();
    const html = await (await fetch(base + '/login.php')).text();
    const csrf = html.match(/name="csrf" value="([^"]+)"/)[1];
    await fetch(base + '/login.php', { method: 'POST', body: new URLSearchParams({ csrf, email, password: pw[email] }), redirect: 'manual' });
  }
  // API calls with the CSRF token of the page currently in the frame.
  async function api(path, body) {
    const r = await fetch(base + '/api/' + path, body === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': W.NS.csrf }, body: JSON.stringify(body) });
    return r.json();
  }
  async function list() {
    const r = await api('projects.php?action=list');
    if (!r.ok) throw new Error('projects list failed: ' + r.error);
    return r.projects;
  }
  const hex = () => [...crypto.getRandomValues(new Uint8Array(8))].map(b => b.toString(16).padStart(2, '0')).join('');
  async function addPuzzle(pid, unique) {
    const grid = '0101011111111110111000100'; // 5x5 heart
    const r = await api('puzzles.php?action=save', { project_id: pid, name: 'E2E puzzle', w: 5, h: 5, palette: ['#16191d'], grid, difficulty: unique ? 'Easy' : 'Not unique', score: 10, unique, hash: hex(), force: true });
    ok(r.ok, 'fixture puzzle save failed: ' + r.error);
  }
  async function makeProject(name, puzzles = []) {
    const r = await api('projects.php?action=create', { name }); ok(r.ok, 'fixture create failed: ' + r.error);
    for (const u of puzzles) await addPuzzle(r.id, u);
    return r.id;
  }
  // A project made just for one test, so a failure in one test does not break the next.
  async function fresh(name, puzzles = []) { await makeProject(name, puzzles); await open(); }
  async function cleanup() { for (const p of await list()) if (p.name.startsWith('E2E ') || p.name.startsWith('<img') || p.name.startsWith('Uploaded pictures')) await api('projects.php?action=delete', { id: p.id }); }

  const cards = () => [...D.querySelectorAll('#projects .pcard:not(.newcard)')];
  const names = () => cards().map(c => c.querySelector('h3').textContent);
  const card = n => cards().find(c => c.querySelector('h3').textContent === n);
  const $ = s => D.querySelector(s);
  const key = (k, target = D.body) => target.dispatchEvent(new W.KeyboardEvent('keydown', { key: k, bubbles: true }));
  const search = v => { $('#filter').value = v; $('#filter').dispatchEvent(new W.Event('input', { bubbles: true })); };
  const sortBy = v => { $('#sort').value = v; $('#sort').dispatchEvent(new W.Event('change', { bubbles: true })); };
  const menuOf = n => card(n).querySelector('[data-act=menu]');
  const act = (n, a) => card(n).querySelector(`[data-act=${a}]`).click();
  const openMenus = () => [...D.querySelectorAll('.menu')].filter(m => !m.hidden).length;

  try {
  /* ---------- admin: fixtures ---------- */
  await login(ADMIN);
  await open();
  await cleanup();
  await makeProject('E2E Alpha', [true, true]);
  await makeProject('E2E beta', [false]);
  await makeProject('E2E Gamma', []);
  try { W.localStorage.removeItem('ns.sort'); W.localStorage.removeItem('ns.view'); } catch (e) { /* ignore */ }
  await open();

  /* ---------- A. load and summary ---------- */
  await T('TC-01', 'Cards match the API list', async () => {
    const api_ = await list();
    eq(cards().length, api_.length, 'card count');
    eq(names().slice().sort(), api_.map(p => p.name).sort(), 'card names');
  });
  await T('TC-02', 'Summary numbers are correct', async () => {
    const p = await list();
    eq(+$('#n-all').textContent, p.length, 'All tab count');
    eq(+$('#n-warn').textContent, p.filter(x => x.count && x.not_unique).length, 'Need checking tab count');
    eq(+$('#n-empty').textContent, p.filter(x => !x.count).length, 'Empty tab count');
    await open('/app/');
    eq(+$('#stat-projects').textContent, p.length, 'projects');
    eq(+$('#stat-puzzles').textContent, p.reduce((a, x) => a + x.count, 0), 'puzzles');
    eq(+$('#stat-warn').textContent, p.reduce((a, x) => a + x.not_unique, 0), 'need checking');
    ok(parseFloat($('#stat-meter').style.width) > 0, 'quota meter is empty');
  });
  await T('TC-03', 'Greeting shows time of day and name', async () => {
    ok(/^(Good morning|Good afternoon|Good evening|Working late), /.test($('#greet').textContent), 'greeting text: ' + $('#greet').textContent);
  });
  await T('TC-46', 'Dashboard shows the 4 latest projects, newest first, then a "New project" card', async () => {
    const p = (await list()).sort((a, b) => (b.updated_at > a.updated_at) - (b.updated_at < a.updated_at) || b.id - a.id);
    eq(names(), p.slice(0, 4).map(x => x.name), 'recent cards');
    ok($('#projects').lastElementChild.classList.contains('newcard'), 'new card last');
    eq($('.seeall').hidden, p.length <= 4, '"View all" only when there are more');
    $('#card-new').click(); ok($('#dlg-name').open, 'new card opens the dialog'); $('#dlg-name [data-close]').click();
    eq(D.querySelectorAll('.howsteps li').length, 4, 'how it works');
    ok(!$('.toolbar') && !$('#filter'), 'no list tools on the dashboard');
  });
  await T('TC-47', 'Shell: navigation, user menu with a POST log-out form, admin links for admins', async () => {
    eq($('.navitem[aria-current="page"]').textContent.trim(), 'Dashboard', 'active nav item');
    ok([...D.querySelectorAll('.navitem')].some(a => a.textContent.includes('Access links')), 'admin nav');
    $('[data-menu-trigger]').click(); ok(!$('.usermenu .menu').hidden, 'menu opens');
    const form = $('.usermenu form');
    ok(form && form.method === 'post' && form.action.endsWith('/logout.php') && form.querySelector('[name=csrf]'), 'log-out form');
    key('Escape'); ok($('.usermenu .menu').hidden, 'Escape closes');
    await open();
    eq($('.navitem[aria-current="page"]').textContent.trim(), 'My Projects', 'active on My Projects');
  });
  await T('TC-04', 'Card shows count, sizes, levels, status and edit time', async () => {
    const c = card('E2E Alpha');
    eq(c.querySelector('.count').textContent, '2', 'count badge');
    ok(c.querySelector('.pmeta').textContent.includes('2 puzzles') && c.querySelector('.pmeta').textContent.includes('5×5'), 'meta: ' + c.querySelector('.pmeta').textContent);
    ok(c.querySelector('.chip.ok'), 'Alpha should be "All unique"');
    ok(card('E2E beta').querySelector('.chip.warn').textContent.includes('1 to check'), 'beta chip');
    ok(card('E2E beta').classList.contains('warn'), 'beta warn border');
    ok(card('E2E Gamma').querySelector('.thumb .empty'), 'Gamma placeholder');
    eq(c.querySelector('.edited').textContent, 'Edited just now', 'relative time');
  });

  /* ---------- B. filters ---------- */
  await T('TC-05', 'Need checking filter shows only projects with non-unique puzzles', async () => {
    $('[data-show=warn]').click();
    ok(names().includes('E2E beta') && !names().includes('E2E Alpha') && !names().includes('E2E Gamma'), 'got ' + names());
    ok(cards().every(c => c.classList.contains('warn')), 'non-warn card in view');
    eq($('[data-show=warn]').getAttribute('aria-selected'), 'true', 'tab selected');
  });
  await T('TC-06', 'Empty filter shows only empty projects', async () => {
    $('[data-show=empty]').click();
    ok(names().includes('E2E Gamma') && cards().every(c => c.classList.contains('empty')), 'got ' + names());
    $('[data-show=all]').click();
    eq(cards().length, +$('#n-all').textContent, 'All restores every card');
  });
  await T('TC-07', 'Dashboard "Need checking" card opens My Projects filtered', async () => {
    await open('/app/');
    ok(!$('#stat-check').disabled, 'card should be clickable');
    const loc = await navAfter(() => $('#stat-check').click());
    ok(loc.pathname.endsWith('/app/projects.php') && loc.search.includes('show=warn'), 'went to ' + loc.href);
    await until(() => !$('#projects .skeleton'), 'list');
    eq($('[data-show=warn]').getAttribute('aria-selected'), 'true', 'warn tab');
    ok(cards().every(c => c.classList.contains('warn')), 'only projects to check');
    await open();
  });

  /* ---------- C. search ---------- */
  await T('TC-08', 'Search is case-insensitive', async () => {
    search('e2e ALPHA'); eq(names(), ['E2E Alpha'], 'result'); search('');
  });
  await T('TC-09', 'Search + filter with no result shows message and reset button', async () => {
    $('[data-show=empty]').click(); search('alpha');
    ok($('.nomatch') && $('.nomatch').textContent.includes('alpha'), 'no-match message');
    $('#clear-filter').click();
    eq($('#filter').value, '', 'search cleared'); eq($('[data-show=all]').getAttribute('aria-selected'), 'true', 'filter reset');
    eq(cards().length, +$('#n-all').textContent, 'all cards back');
  });
  await T('TC-10', 'Search text with HTML is shown as text', async () => {
    search('<b>x</b>'); ok(!$('.nomatch b'), 'HTML was injected'); search('');
  });
  await T('TC-11', 'Escape in the search box clears it', async () => {
    search('alpha'); $('#filter').focus(); key('Escape', $('#filter'));
    eq($('#filter').value, '', 'value'); eq(cards().length, +$('#n-all').textContent, 'cards');
  });
  await T('TC-12', '"/" focuses search; "N" opens New project; "N" while typing does not', async () => {
    D.activeElement.blur(); key('/'); eq(D.activeElement, $('#filter'), 'focus');
    key('n', $('#filter')); ok(!$('#dlg-name').open, 'dialog opened while typing');
    $('#filter').blur(); key('n'); ok($('#dlg-name').open, 'N did not open dialog');
    eq($('#dlg-name-title').textContent, 'New project', 'title');
    $('#dlg-name [data-close]').click(); ok(!$('#dlg-name').open, 'Cancel did not close');
  });

  /* ---------- D. sort and layout ---------- */
  await T('TC-13', 'Sort by name (natural, case-insensitive)', async () => {
    sortBy('name'); const n = names();
    eq(n, n.slice().sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })), 'order');
  });
  await T('TC-14', 'Sort by most puzzles', async () => {
    sortBy('count'); const c = cards().map(x => +(x.querySelector('.count')?.textContent || 0));
    ok(c.every((v, i) => !i || c[i - 1] >= v), 'counts ' + c);
  });
  await T('TC-15', 'Sort by last edited and newest first', async () => {
    const p = await list();
    sortBy('updated'); eq(names()[0], p.slice().sort((a, b) => (b.updated_at > a.updated_at) - (b.updated_at < a.updated_at) || b.id - a.id)[0].name, 'last edited first');
    sortBy('created'); eq(names()[0], p.slice().sort((a, b) => (b.created_at > a.created_at) - (b.created_at < a.created_at) || b.id - a.id)[0].name, 'newest first');
  });
  await T('TC-16', 'Sort and layout are remembered after reload', async () => {
    sortBy('name'); D.querySelector('[data-view=list]').click();
    ok($('#projects').classList.contains('list'), 'list class');
    await open();
    eq($('#sort').value, 'name', 'sort'); ok($('#projects').classList.contains('list'), 'layout');
    eq($('[data-view=list]').getAttribute('aria-pressed'), 'true', 'toggle state');
    D.querySelector('[data-view=grid]').click(); sortBy('updated');
  });
  await T('TC-17', 'Page still works when browser storage is blocked', async () => {
    Object.defineProperty(W, 'localStorage', { get() { throw new Error('blocked'); } });
    const before = jsErrors.length;
    sortBy('name'); D.querySelector('[data-view=list]').click(); D.querySelector('[data-view=grid]').click(); sortBy('updated');
    eq(jsErrors.length, before, 'errors thrown'); ok(cards().length > 0, 'cards gone');
    await open();
  });

  /* ---------- E. card menu and navigation ---------- */
  await T('TC-18', 'Only one menu open; outside click and Escape close it', async () => {
    menuOf('E2E Alpha').click(); eq(openMenus(), 1, 'first menu');
    eq(menuOf('E2E Alpha').getAttribute('aria-expanded'), 'true', 'aria-expanded');
    menuOf('E2E beta').click(); eq(openMenus(), 1, 'second menu should replace first');
    $('.pagehead h1').click(); eq(openMenus(), 0, 'outside click');
    menuOf('E2E beta').click(); key('Escape'); eq(openMenus(), 0, 'Escape');
  });
  await T('TC-19', 'Opening the menu does not open the project', async () => {
    const href = W.location.href; menuOf('E2E Alpha').click(); await sleep(300);
    eq(W.location.href, href, 'navigated'); key('Escape');
  });
  await T('TC-45', 'Keyboard: Tab out of a menu closes it; Escape returns focus to the ⋯ button', async () => {
    menuOf('E2E Alpha').click();
    eq(D.activeElement.textContent, 'Rename', 'focus on first item');
    // A background iframe gets no real focus events, so send the focusout that pressing Tab would cause.
    const next = card('E2E beta').querySelector('h3 a');
    D.activeElement.dispatchEvent(new W.FocusEvent('focusout', { bubbles: true, relatedTarget: next })); next.focus();
    eq(openMenus(), 0, 'menu still open after focus left');
    menuOf('E2E Alpha').click(); key('Escape', D.activeElement);
    eq(openMenus(), 0, 'Escape'); eq(D.activeElement, menuOf('E2E Alpha'), 'focus back on ⋯');
  });
  await T('TC-20', 'Clicking the card body opens the editor', async () => {
    const id = card('E2E Alpha').dataset.id;
    const loc = await navAfter(() => card('E2E Alpha').querySelector('.pfoot').click());
    ok(loc.pathname.endsWith('/app/editor.php') && loc.search.includes('id=' + id), 'went to ' + loc.href);
    await open();
  });
  await T('TC-21', 'Title link points to the editor', async () => {
    const c = card('E2E beta'); ok(c.querySelector('h3 a').href.endsWith('/app/editor.php?id=' + c.dataset.id), c.querySelector('h3 a').href);
  });

  /* ---------- F. create ---------- */
  await T('TC-22', 'New project dialog: empty name is not submitted', async () => {
    const before = (await list()).length;
    $('#qa-new').click(); $('#dlg-name-input').value = '   '; $('#dlg-name-ok').click(); await sleep(400);
    ok($('#dlg-name').open, 'dialog closed on empty name'); eq((await list()).length, before, 'project created');
    $('#dlg-name [data-close]').click();
  });
  await T('TC-23', 'Suggestion chip fills the name; Cancel and backdrop create nothing', async () => {
    const before = (await list()).length;
    $('#qa-new').click(); $('#dlg-suggest .chip').click();
    eq($('#dlg-name-input').value, $('#dlg-suggest .chip').textContent, 'chip text');
    $('#dlg-name [data-close]').click();
    $('#qa-new').click(); $('#dlg-name').dispatchEvent(new W.MouseEvent('click', { bubbles: true })); // click on the dialog box itself = backdrop
    ok(!$('#dlg-name').open, 'backdrop did not close');
    await sleep(300); eq((await list()).length, before, 'project created');
  });
  await T('TC-24', 'Create project opens it in the editor', async () => {
    $('#qa-new').click(); $('#dlg-name-input').value = '  E2E Created  ';
    const loc = await navAfter(() => $('#dlg-name-ok').click());
    ok(loc.pathname.endsWith('/app/editor.php'), 'went to ' + loc.href);
    const id = +new URLSearchParams(loc.search).get('id');
    const p = (await list()).find(x => x.id === id); ok(p && p.name === 'E2E Created', 'saved name: ' + (p && p.name));
    await open();
  });
  await T('TC-25', 'Upload images creates a project and opens the upload area', async () => {
    const loc = await navAfter(() => $('#qa-upload').click());
    eq(loc.hash, '#upload', 'hash');
    const p = (await list()).find(x => x.id === +new URLSearchParams(loc.search).get('id'));
    ok(p && p.name.startsWith('Uploaded pictures'), 'name ' + (p && p.name));
    await open();
  });

  /* ---------- G. rename ---------- */
  await T('TC-26', 'Rename: dialog is prefilled and the new name is saved', async () => {
    act('E2E Gamma', 'rename');
    eq($('#dlg-name-title').textContent, 'Rename project', 'title'); eq($('#dlg-name-input').value, 'E2E Gamma', 'prefill');
    ok($('#dlg-suggest').hidden, 'ideas shown on rename');
    $('#dlg-name-input').value = 'E2E Gamma 2'; $('#dlg-name-ok').click();
    await until(() => card('E2E Gamma 2'), 'renamed card');
    ok((await list()).some(p => p.name === 'E2E Gamma 2'), 'not saved on server');
  });
  await T('TC-27', 'Rename with the same name sends no request', async () => {
    let calls = 0; const f = W.fetch; W.fetch = (...a) => { calls++; return f.apply(W, a); };
    act('E2E Gamma 2', 'rename'); $('#dlg-name-ok').click(); await sleep(400);
    W.fetch = f; eq(calls, 0, 'requests');
  });
  await T('TC-28', 'Name with HTML is shown as text everywhere (XSS)', async () => {
    const evil = '<img src=x onerror="window.__xss=1">';
    await fresh('E2E XSS');
    W.__xss = 0;
    act('E2E XSS', 'rename'); $('#dlg-name-input').value = evil; $('#dlg-name-ok').click();
    await until(() => card(evil), 'evil card'); await sleep(300);
    ok(!card(evil).querySelector('h3 img'), 'img element injected');
    act(evil, 'delete'); ok($('#dlg-confirm-title').textContent.includes(evil) && !$('#dlg-confirm-title img'), 'confirm title');
    $('#dlg-confirm [data-close]').click();
    search('<img'); ok(!$('.pcard h3 img'), 'search view'); search('');
    eq(W.__xss, 0, 'script ran');
    await open(); W.__xss = 0; await sleep(300); // reload renders the list again from the server
    ok(!$('.pcard h3 img'), 'after reload'); eq(W.__xss, 0, 'script ran after reload');
  });
  await T('TC-29', 'Very long name is cut to 160 characters by the server', async () => {
    await fresh('E2E Long');
    eq(+$('#dlg-name-input').getAttribute('maxlength'), 160, 'input maxlength');
    act('E2E Long', 'rename'); $('#dlg-name-input').removeAttribute('maxlength'); $('#dlg-name-input').value = 'E2E ' + 'x'.repeat(250); $('#dlg-name-ok').click();
    await sleep(700);
    const p = (await list()).find(x => x.name.startsWith('E2E xxx')); ok(p, 'project missing'); eq(p.name.length, 160, 'stored length');
  });

  /* ---------- H. duplicate and delete ---------- */
  await T('TC-30', 'Duplicate makes a "(copy)" with the same puzzles', async () => {
    act('E2E Alpha', 'duplicate');
    await until(() => card('E2E Alpha (copy)'), 'copy card');
    eq(card('E2E Alpha (copy)').querySelector('.count').textContent, '2', 'copied puzzles');
  });
  await T('TC-31', 'Delete: Cancel keeps the project; message names the puzzle count', async () => {
    if (!card('E2E Alpha (copy)')) await fresh('E2E Alpha (copy)', [true, true]);
    act('E2E Alpha (copy)', 'delete');
    ok($('#dlg-confirm').open, 'dialog'); ok($('#dlg-confirm-text').textContent.includes('2 puzzles'), $('#dlg-confirm-text').textContent);
    $('#dlg-confirm [data-close]').click(); await sleep(300);
    ok(card('E2E Alpha (copy)') && (await list()).some(p => p.name === 'E2E Alpha (copy)'), 'deleted on cancel');
  });
  await T('TC-32', 'Delete: confirm removes it now and after reload', async () => {
    await fresh('E2E Doomed', [true]);
    act('E2E Doomed', 'delete'); D.querySelector('#dlg-confirm .danger-solid').click();
    await until(() => !card('E2E Doomed'), 'card removed');
    await open(); ok(!card('E2E Doomed'), 'back after reload');
  });
  await T('TC-33', 'Delete message for an empty project', async () => {
    await fresh('E2E Empty');
    act('E2E Empty', 'delete'); ok($('#dlg-confirm-text').textContent.includes('empty'), $('#dlg-confirm-text').textContent);
    $('#dlg-confirm [data-close]').click();
  });

  /* ---------- I. errors ---------- */
  await T('TC-34', 'List load error shows message and "Try again" works', async () => {
    await fresh('E2E Err');
    const f = W.fetch;
    W.fetch = (u, o) => String(u).includes('action=list') ? Promise.resolve(new W.Response('{"ok":false,"error":"E2E boom"}', { status: 500 })) : f.call(W, u, o);
    try {
      act('E2E Err', 'duplicate');
      await until(() => $('.nomatch .err-msg'), 'error message');
      ok($('.nomatch').textContent.includes('E2E boom'), 'message text');
    } finally { W.fetch = f; }
    $('#retry').click();
    await until(() => card('E2E Err (copy)'), 'cards after retry');
  });
  await T('TC-35', 'Server error on an action shows a red toast', async () => {
    await fresh('E2E Err2');
    const f = W.fetch;
    W.fetch = (u, o) => String(u).includes('action=duplicate') ? Promise.resolve(new W.Response('{"ok":false,"error":"E2E nope"}', { status: 403 })) : f.call(W, u, o);
    try {
      act('E2E Err2', 'duplicate');
      await until(() => $('.toast.err'), 'error toast'); ok($('.toast.err').textContent.includes('E2E nope'), 'toast text');
    } finally { W.fetch = f; }
  });
  await T('TC-36', 'Expired session sends the user to the login page', async () => {
    await fresh('E2E Session');
    await logout();
    try {
      const loc = await navAfter(() => act('E2E Session', 'duplicate'));
      ok(loc.pathname.endsWith('/login.php'), 'went to ' + loc.href);
    } finally { await login(ADMIN); await open(); }
  });

  /* ---------- J. small screens ---------- */
  await T('TC-37', 'Phone width (375px): no sideways scroll in grid and list layout', async () => {
    await open('/app/projects.php', 375);
    ok(D.documentElement.scrollWidth <= 375, 'grid scrollWidth ' + D.documentElement.scrollWidth);
    D.querySelector('[data-view=list]').click();
    ok(D.documentElement.scrollWidth <= 375, 'list scrollWidth ' + D.documentElement.scrollWidth);
    D.querySelector('[data-view=grid]').click();
    const r = $('#filter').getBoundingClientRect(); ok(r.width >= 220, 'search box too narrow: ' + r.width);
    await open('/app/', 375);
    ok(D.documentElement.scrollWidth <= 375, 'dashboard scrollWidth ' + D.documentElement.scrollWidth);
    ok($('.sidebar').getBoundingClientRect().width <= 70, 'sidebar becomes an icon rail');
    await open();
  });

  await cleanup();

  /* ---------- K. other accounts ---------- */
  await login(FE); await open();
  await T('TC-38', 'FE plan: limits shown for the plan', async () => {
    await open('/app/');
    ok($('.summary').textContent.includes('/ 100'), 'project limit');
    ok($('.summary').textContent.includes('30×30') && $('.summary').textContent.includes('Pro adds'), 'plan card');
    ok(![...D.querySelectorAll('.navitem')].some(a => a.textContent.includes('Access links')), 'no admin nav for customers');
    await open();
  });
  await T('TC-39', 'FE user does not see admin projects', async () => {
    ok(!names().some(n => n.startsWith('E2E ')), 'admin projects leaked');
  });
  await T('TC-40', 'Empty account: 3-step guide, toolbar hidden, buttons work', async () => {
    if ((await list()).length) throw new Skip('fe@nonogram.test already has projects');
    ok($('.emptystate .steps') && $('.toolbar').hidden, 'empty state/toolbar');
    $('#empty-new').click(); ok($('#dlg-name').open, 'empty-state New project'); $('#dlg-name [data-close]').click();
    await open('/app/');
    ok($('#stat-check').disabled, '"Need checking" should be disabled at 0');
    ok($('.emptystate'), 'dashboard empty state');
    await open();
  });
  await T('TC-41', 'Project limit reached: create shows an error and stays on the page', async () => {
    const have = (await list()).length;
    for (let i = have; i < 100; i++) await api('projects.php?action=create', { name: 'E2E limit ' + i });
    await open();
    $('#qa-new').click(); $('#dlg-name-input').value = 'E2E over limit'; $('#dlg-name-ok').click();
    await until(() => $('.toast.err'), 'error toast');
    ok($('.toast.err').textContent.includes('100 projects'), 'toast: ' + $('.toast.err').textContent);
    ok(W.location.pathname.endsWith('/app/projects.php'), 'navigated away');
    await open('/app/');
    eq(+$('#stat-projects').textContent, 100, 'counter'); eq($('#stat-meter').style.width, '100%', 'meter full');
    await cleanup();
  });

  await login(NONE); await open('/app/');
  await T('TC-42', 'Account without a license sees how to unlock it; My Projects sends them back', async () => {
    ok($('.nolicense') && $('.nolicense').textContent.includes('no Nonogram Studio license') && $('.nolicense').textContent.includes('access link'), 'message');
    ok(!$('#projects') && !$('#qa-new'), 'dashboard shown');
    await open('/app/projects.php');
    ok(W.location.pathname.endsWith('/app/'), 'My Projects redirects: ' + W.location.pathname);
  });

  await logout(); await open('/app/');
  await T('TC-43', 'Logged-out visitor is sent to login', async () => { ok(W.location.pathname.endsWith('/login.php'), W.location.href); });

  await T('TC-44', 'No JavaScript errors during the run', async () => { eq(jsErrors, [], 'errors'); });
  } catch (e) {
    // A failure outside a test case (fixtures, cleanup). Keep what ran so far and tidy up.
    results.push({ id: 'CRASH', name: 'Test run stopped early', result: 'FAIL', note: String(e.stack || e) });
    for (const who of [ADMIN, FE]) { try { await login(who); await open(); await cleanup(); } catch (e2) { /* best effort */ } }
  }

  await login(ADMIN);
  fr.remove();
  const sum = results.reduce((a, r) => (a[r.result] = (a[r.result] || 0) + 1, a), {});
  console.table(results);
  console.log('Dashboard E2E:', sum);
  return { summary: sum, results };
}
