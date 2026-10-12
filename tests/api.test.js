// Local API smoke test. Start the dev server and run tests/seed-users.php first, then:
//   node tests/api.test.js
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const E = require('../assets/js/engine.js');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';

const creds = Object.fromEntries(JSON.parse(fs.readFileSync(path.join(__dirname, 'dev-accounts.json'), 'utf8')).accounts.map(a => [a.email, a.password]));

class Client {
  constructor() { this.cookies = {}; this.csrf = ''; }
  async req(url, opts = {}) {
    const headers = Object.assign({ cookie: Object.entries(this.cookies).map(([k, v]) => k + '=' + v).join('; ') }, opts.headers || {});
    const r = await fetch(BASE + url, Object.assign({}, opts, { headers, redirect: 'manual' }));
    for (const c of r.headers.getSetCookie ? r.headers.getSetCookie() : []) { const [kv] = c.split(';'); const [k, v] = kv.split('='); this.cookies[k] = v; }
    return r;
  }
  async login(email) {
    const page = await (await this.req('/login.php')).text();
    const csrf = page.match(/name="csrf" value="([^"]+)"/)[1];
    const body = new URLSearchParams({ csrf, email, password: creds[email] });
    const r = await this.req('/login.php', { method: 'POST', body, headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    assert.strictEqual(r.status, 302, 'login should redirect');
    const app = await (await this.req('/app/')).text();
    this.csrf = (app.match(/"csrf":"([0-9a-f]+)"/) || [])[1] || '';
    return app;
  }
  async api(p, body, csrf = true) {
    const r = await this.req('/api/' + p, body === undefined ? {} : {
      method: 'POST', body: JSON.stringify(body),
      headers: Object.assign({ 'content-type': 'application/json' }, csrf ? { 'x-csrf-token': this.csrf } : {}),
    });
    return { status: r.status, json: await r.json() };
  }
}

function puzzleBody(projectId, W, H, g, palette, extra = {}) {
  const p = E.makePuzzle(W, H, g, palette), s = E.solve(p), sc = E.score(s, p);
  return Object.assign({ project_id: projectId, name: 'Test', w: W, h: H, grid: E.encodeGrid(g), palette, difficulty: E.level(sc), score: Math.min(99, sc), unique: s.unique, hash: E.fingerprint(W, H, g) }, extra);
}

let passed = 0;
const t = async (name, fn) => { await fn(); passed++; console.log('ok -', name); };

(async () => {
  const admin = new Client(), fe = new Client(), none = new Client();
  const heart = Uint8Array.from([0,1,0,1,0, 1,1,1,1,1, 1,1,1,1,1, 0,1,1,1,0, 0,0,1,0,0]);
  const heartMirror = Uint8Array.from(heart); // symmetric, so use a lopsided shape for mirror test
  const L = Uint8Array.from([1,0,0,0,0, 1,0,0,0,0, 1,0,0,0,0, 1,0,0,0,0, 1,1,1,1,0]);
  const Lmirror = Uint8Array.from([0,0,0,0,1, 0,0,0,0,1, 0,0,0,0,1, 0,0,0,0,1, 0,1,1,1,1]);
  let pid;

  await t('wrong password is rejected', async () => {
    const c = new Client(); const page = await (await c.req('/login.php')).text();
    const csrf = page.match(/name="csrf" value="([^"]+)"/)[1];
    const r = await c.req('/login.php', { method: 'POST', body: new URLSearchParams({ csrf, email: 'admin@nonogram.test', password: 'nope' }), headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    assert.strictEqual(r.status, 200); assert.ok((await r.text()).includes('do not match'));
  });

  await t('api needs login', async () => { const r = await new Client().api('projects.php?action=list'); assert.strictEqual(r.status, 401); });

  await t('admin logs in and creates a project', async () => {
    await admin.login('admin@nonogram.test');
    assert.ok(admin.csrf.length === 64);
    const r = await admin.api('projects.php?action=create', { name: 'API test book' });
    assert.strictEqual(r.status, 200); pid = r.json.id;
  });

  await t('POST without CSRF header is refused', async () => {
    const r = await admin.api('projects.php?action=create', { name: 'x' }, false); assert.strictEqual(r.status, 419);
  });

  await t('save puzzle, server builds the clues', async () => {
    const r = await admin.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, heart, ['#16191d'], { name: 'Heart' }));
    assert.strictEqual(r.status, 200, JSON.stringify(r.json));
    const g = await admin.api('projects.php?action=get&id=' + pid);
    assert.strictEqual(g.json.project.puzzles.length, 1);
    assert.strictEqual(g.json.project.puzzles[0].grid, E.encodeGrid(heart));
  });

  await t('exact duplicate is caught', async () => {
    const r = await admin.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, heartMirror, ['#16191d']));
    assert.strictEqual(r.status, 409); assert.strictEqual(r.json.duplicate.name, 'Heart');
  });

  await t('mirrored duplicate is caught, force saves anyway', async () => {
    assert.strictEqual((await admin.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, L, ['#16191d'], { name: 'L' }))).status, 200);
    assert.strictEqual((await admin.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, Lmirror, ['#16191d']))).status, 409);
    assert.strictEqual((await admin.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, Lmirror, ['#16191d'], { force: true }))).status, 200);
  });

  await t('bad grid data is rejected', async () => {
    const r = await admin.api('puzzles.php?action=save', Object.assign(puzzleBody(pid, 5, 5, heart, ['#16191d']), { grid: '0101' }));
    assert.strictEqual(r.status, 400);
  });

  await t('list, reorder, duplicate and delete project', async () => {
    const list = await admin.api('projects.php?action=list');
    const pr = list.json.projects.find(p => p.id === pid);
    assert.strictEqual(pr.count, 3); assert.ok(pr.cover);
    const ids = (await admin.api('projects.php?action=get&id=' + pid)).json.project.puzzles.map(p => p.id).reverse();
    assert.strictEqual((await admin.api('puzzles.php?action=reorder', { project_id: pid, ids })).status, 200);
    assert.deepStrictEqual((await admin.api('projects.php?action=get&id=' + pid)).json.project.puzzles.map(p => p.id), ids);
    const d = await admin.api('projects.php?action=duplicate', { id: pid });
    assert.strictEqual((await admin.api('projects.php?action=get&id=' + d.json.id)).json.project.puzzles.length, 3);
    assert.strictEqual((await admin.api('projects.php?action=delete', { id: d.json.id })).status, 200);
  });

  await t('FE buyer: no color, no grids over 30, cannot see admin project', async () => {
    const page = await fe.login('fe@nonogram.test');
    assert.ok(page.includes('"color":false'));
    const own = (await fe.api('projects.php?action=create', { name: 'FE book' })).json.id;
    const color = puzzleBody(own, 5, 5, Uint8Array.from([1,2,0,0,0, 1,2,0,0,0, 0,0,0,0,0, 0,0,0,1,1, 0,0,0,2,2]), ['#c81d25', '#1d3d6b']);
    assert.strictEqual((await fe.api('puzzles.php?action=save', color)).status, 403);
    const big = new Uint8Array(35 * 35); big[0] = 1;
    assert.strictEqual((await fe.api('puzzles.php?action=save', puzzleBody(own, 35, 35, big, ['#16191d']))).status, 403);
    assert.strictEqual((await fe.api('projects.php?action=get&id=' + pid)).status, 404);
    assert.strictEqual((await fe.api('puzzles.php?action=save', puzzleBody(pid, 5, 5, L, ['#16191d']))).status, 404);
  });

  await t('user without a license is blocked from the API', async () => {
    await none.login('nolicense@nonogram.test');
    assert.strictEqual((await none.api('projects.php?action=list')).status, 403);
  });

  await t('admin cleans up test project', async () => {
    assert.strictEqual((await admin.api('projects.php?action=delete', { id: pid })).status, 200);
  });

  console.log(`\n${passed} API tests passed`);
})().catch(e => { console.error('FAIL', e); process.exit(1); });
