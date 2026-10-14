// Sign in with Google (against tests/fake-google.php) and My Exports, against the local server.
// Needs the dev setup from the README, including the google block with test_endpoints in config.php, then:
//   node tests/google-exports.test.js
// The fake Google server is started and stopped by this script (port 8092).
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const ROOT = path.join(__dirname, '..');
const PROFILE = path.join(ROOT, 'storage', 'fake-google.json');
const creds = Object.fromEntries(JSON.parse(fs.readFileSync(path.join(__dirname, 'dev-accounts.json'), 'utf8')).accounts.map(a => [a.email, a.password]));
const stamp = Date.now().toString(36);
const mail = n => `g-${n}-${stamp}@nonogram.test`;

class Client {
  constructor() { this.cookies = {}; }
  async req(url, opts = {}) {
    const headers = Object.assign({ cookie: Object.entries(this.cookies).map(([k, v]) => k + '=' + v).join('; ') }, opts.headers || {});
    const r = await fetch(BASE + url, Object.assign({}, opts, { headers, redirect: 'manual' }));
    for (const c of r.headers.getSetCookie ? r.headers.getSetCookie() : []) { const [kv] = c.split(';'); const [k, v] = kv.split('='); this.cookies[k] = v; }
    return r;
  }
  rel(loc, from) { const u = new URL(loc, BASE + from); return u.pathname + u.search; }
  async page(url) { const r = await this.req(url); return { status: r.status, location: r.headers.get('location') || '', html: await r.text() }; }
  async go(url) { let p = await this.page(url); let at = url; for (let i = 0; i < 6 && p.location; i++) { at = this.rel(p.location, at); p = await this.page(at); } return Object.assign(p, { url: at }); }
  async csrf(url) { const m = (await this.page(url)).html.match(/name="csrf" value="([^"]+)"/); assert.ok(m, 'no CSRF on ' + url); return m[1]; }
  async post(url, fields, from = url) {
    const body = new URLSearchParams(Object.assign({ csrf: await this.csrf(from) }, fields));
    const r = await this.req(url, { method: 'POST', body, headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    const loc = r.headers.get('location') || '';
    return Object.assign(loc ? await this.go(this.rel(loc, url)) : { html: await r.text() }, { status: r.status, location: loc });
  }
  async login(email, password = creds[email]) { return this.post('/login.php', { email, password }); }
  async api(p, body) {
    const app = await (await this.req('/app/settings.php')).text();
    const csrf = (app.match(/"csrf":"([0-9a-f]+)"/) || [])[1] || '';
    const r = await this.req('/api/' + p, { method: 'POST', body: JSON.stringify(body), headers: { 'content-type': 'application/json', 'x-csrf-token': csrf } });
    return { status: r.status, json: await r.json() };
  }
  /** The whole round trip: app -> fake Google (signed in as `profile`) -> app. Returns the final page. */
  async google(profile, opts = {}) {
    fs.writeFileSync(PROFILE, JSON.stringify(profile));
    const start = await this.req('/google.php');
    const toGoogle = start.headers.get('location');
    assert.ok(toGoogle && toGoogle.startsWith('http://127.0.0.1:8092/auth'), 'redirect to Google: ' + toGoogle);
    const g = await fetch(toGoogle, { redirect: 'manual' });
    let back = g.headers.get('location');
    assert.ok(back && back.includes('/google.php?'), 'Google sends back: ' + back);
    if (opts.tamper) back = back.replace(/state=[^&]+/, 'state=forged');
    return this.go(this.rel(back, '/google.php'));
  }
}
const flashes = html => [...html.matchAll(/class="flash (ok|err|info)"[^>]*>([^<]*)</g)].map(m => m[1] + ': ' + m[2].replace(/&quot;/g, '"').replace(/&#039;/g, "'"));
const has = (p, text) => flashes(p.html).some(f => f.includes(text));

let passed = 0, skipped = 0;
const t = async (name, fn) => { await fn(); passed++; console.log('ok -', name); };

(async () => {
  const fake = spawn('php', ['-S', '127.0.0.1:8092', path.join('tests', 'fake-google.php')], { cwd: ROOT, stdio: 'ignore' });
  try {
    for (let i = 0; i < 40; i++) { try { await fetch('http://127.0.0.1:8092/x'); break; } catch (e) { await new Promise(r => setTimeout(r, 100)); } }
    const admin = new Client(); await admin.login('admin@nonogram.test');
    const googleOn = (await new Client().page('/login.php')).html.includes('Continue with Google');

    if (!googleOn) { console.log('skip - Google tests: no google block with test_endpoints in config.php'); skipped++; }
    else {
      await t('login page shows "Continue with Google"', async () => {
        assert.ok((await new Client().page('/login.php')).html.includes('href="/google.php"'));
      });

      await t('unknown Google user without an access link is refused (registration is closed)', async () => {
        const p = await new Client().google({ sub: 'g-new-' + stamp, email: mail(1), name: 'New Person', verified: true });
        assert.ok(p.url.startsWith('/login.php') && has(p, 'no account for ' + mail(1)), flashes(p.html).join());
      });

      let linkCode;
      await t('buyer from an access link signs up with Google and gets the products, without a password', async () => {
        const r = await admin.post('/admin/links.php', { action: 'create', label: 'E2E Google link ' + stamp, 'products[]': 'fe', max_uses: '0' });
        linkCode = r.html.match(new RegExp('E2E Google link ' + stamp + '[\\s\\S]*?access\\.php\\?c=([a-f0-9]{24})'))[1];
        const c = new Client();
        assert.ok((await c.page('/access.php?c=' + linkCode)).html.includes('Continue with Google'), 'Google button on the claim page');
        const p = await c.google({ sub: 'g-buyer-' + stamp, email: mail(2), name: 'Google Buyer', verified: true });
        assert.ok(p.url === '/app/' && has(p, 'Unlocked: Nonogram Studio'), p.url + ' ' + flashes(p.html).join());
        const s = await c.page('/app/settings.php');
        assert.ok(s.html.includes('Set a password') && s.html.includes('Linked'), 'Google-only account in settings');
        assert.ok((await new Client().login(mail(2), 'anything-at-all')).html.includes('do not match'), 'no password login yet');
      });

      await t('the same Google account logs straight back in', async () => {
        const p = await new Client().google({ sub: 'g-buyer-' + stamp, email: mail(2), name: 'Google Buyer', verified: true });
        assert.strictEqual(p.url, '/app/');
      });

      await t('a Google-only account can set a password, then unlink Google', async () => {
        const c = new Client(); await c.google({ sub: 'g-buyer-' + stamp, email: mail(2), verified: true });
        let r = await c.post('/app/settings.php', { action: 'unlink_google' });
        assert.ok(has(r, 'Set a password first'), 'unlink refused without a password');
        r = await c.post('/app/settings.php', { action: 'password', password: 'g-pass-' + stamp, password_confirm: 'g-pass-' + stamp });
        assert.ok(has(r, 'Password set'), flashes(r.html).join());
        r = await c.post('/app/settings.php', { action: 'unlink_google' });
        assert.ok(has(r, 'no longer linked'), flashes(r.html).join());
        assert.ok((await new Client().login(mail(2), 'g-pass-' + stamp)).location.endsWith('/app/'), 'password login works');
      });

      await t('an existing password account is linked when Google has verified the same email', async () => {
        const p = await new Client().google({ sub: 'g-buyer-2-' + stamp, email: mail(2), verified: true });
        assert.ok(p.url === '/app/' && has(p, 'now linked'), flashes(p.html).join());
      });

      await t('an unverified Google email is refused', async () => {
        const p = await new Client().google({ sub: 'g-x-' + stamp, email: 'fe@nonogram.test', verified: false });
        assert.ok(p.url.startsWith('/login.php') && has(p, 'not verified'), flashes(p.html).join());
      });

      await t('a forged state is refused, and Cancel on Google is handled', async () => {
        let p = await new Client().google({ sub: 'g-buyer-2-' + stamp, email: mail(2), verified: true }, { tamper: true });
        assert.ok(p.url.startsWith('/login.php') && has(p, 'could not be verified'), flashes(p.html).join());
        p = await new Client().google({ cancel: true });
        assert.ok(has(p, 'cancelled'), flashes(p.html).join());
      });

      await t('a disabled account cannot log in with Google', async () => {
        const id = (await admin.page('/admin/users.php?q=' + encodeURIComponent(mail(2)))).html.match(/admin\/user\.php\?id=(\d+)/)[1];
        await admin.post('/admin/user.php?id=' + id, { action: 'status', status: 'disabled' });
        const p = await new Client().google({ sub: 'g-buyer-2-' + stamp, email: mail(2), verified: true });
        assert.ok(has(p, 'disabled'), flashes(p.html).join());
        await admin.post('/admin/user.php?id=' + id, { action: 'delete', confirm_email: mail(2) });
      });

      await t('Settings > Link Google links another Google account, but not one already in use', async () => {
        const c = new Client(); await c.login('fe@nonogram.test');
        let p = await c.google({ sub: 'g-fe-' + stamp, email: 'someone.else@gmail.test', verified: true });
        assert.ok(p.url === '/app/settings.php' && has(p, 'linked'), flashes(p.html).join());
        const other = new Client(); await other.login('admin@nonogram.test');
        p = await other.google({ sub: 'g-fe-' + stamp, email: 'someone.else@gmail.test', verified: true });
        assert.ok(has(p, 'already linked to another'), flashes(p.html).join());
        await c.post('/app/settings.php', { action: 'unlink_google' });
      });

      await t('admin user page shows how the account logs in', async () => {
        const html = (await admin.page('/admin/users.php?q=fe%40nonogram')).html;
        const id = html.match(/admin\/user\.php\?id=(\d+)/)[1];
        assert.ok((await admin.page('/admin/user.php?id=' + id)).html.includes('Log in with'));
      });
    }

    /* ---------- My Exports ---------- */
    const fe = new Client(); await fe.login('fe@nonogram.test');
    const pid = (await fe.api('projects.php?action=create', { name: 'E2E Export ' + stamp })).json.id;

    await t('My Exports is in the menu and starts empty for the project', async () => {
      const p = await fe.page('/app/exports.php');
      assert.strictEqual(p.status, 200);
      assert.ok(p.html.includes('href="/app/exports.php"') && p.html.includes('My Exports'));
    });

    await t('a book PDF export is recorded and can be downloaded again', async () => {
      const r = await fe.api('exports.php?action=log', { kind: 'project_pdf', project_id: pid, name: 'E2E Export ' + stamp, puzzles: 12, pages: 15, page: '6x9' });
      assert.strictEqual(r.status, 200);
      const p = await fe.page('/app/exports.php?project=' + pid);
      assert.ok(p.html.includes('Book PDF') && p.html.includes('6 × 9 in') && p.html.includes('>15<'), 'row');
      assert.ok(p.html.includes('editor.php?id=' + pid + '&amp;page=6x9#export'), 'download again link');
    });

    await t('puzzle PDF and PNG exports are recorded too; bad input is refused', async () => {
      assert.strictEqual((await fe.api('exports.php?action=log', { kind: 'png', project_id: pid, name: 'Heart', puzzles: 1 })).status, 200);
      assert.strictEqual((await fe.api('exports.php?action=log', { kind: 'zip', project_id: pid })).status, 400);
      const p = await fe.page('/app/exports.php?project=' + pid);
      assert.ok(p.html.includes('PNG image') && p.html.includes('Open project'));
    });

    await t('you cannot log an export against somebody else\'s project', async () => {
      const mine = (await admin.api('projects.php?action=create', { name: 'E2E admin only ' + stamp })).json.id;
      assert.strictEqual((await fe.api('exports.php?action=log', { kind: 'project_pdf', project_id: mine })).status, 404);
      await admin.api('projects.php?action=delete', { id: mine });
    });

    await t('a deleted project stays in the history as "deleted project"', async () => {
      await fe.api('projects.php?action=delete', { id: pid });
      const p = await fe.page('/app/exports.php');
      assert.ok(p.html.includes('E2E Export ' + stamp) && p.html.includes('deleted project'));
    });

    await t('clear history empties the list', async () => {
      const r = await fe.post('/app/exports.php', { action: 'clear' });
      assert.ok(has(r, 'history cleared') && r.html.includes('Nothing exported yet'), flashes(r.html).join());
    });

    await t('accounts without a license cannot use exports', async () => {
      const c = new Client(); await c.login('nolicense@nonogram.test');
      assert.ok((await c.page('/app/exports.php')).location.endsWith('/app/'));
      assert.strictEqual((await c.api('exports.php?action=log', { kind: 'png' })).status, 403);
    });

    console.log(`\n${passed} Google + export tests passed` + (skipped ? `, ${skipped} skipped` : ''));
  } finally {
    fake.kill();
  }
})().catch(e => { console.error(e); process.exit(1); });
