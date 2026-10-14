// Accounts, WarriorPlus access links, password reset, settings and the admin area, against the local server.
// Needs the SQLite dev setup with debug on and the mail 'log' driver (see README), then:
//   node tests/account.test.js
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const MAIL_LOG = path.join(__dirname, '..', 'storage', 'logs', 'mail.log');
const creds = Object.fromEntries(JSON.parse(fs.readFileSync(path.join(__dirname, 'dev-accounts.json'), 'utf8')).accounts.map(a => [a.email, a.password]));
const stamp = Date.now().toString(36);
const buyerEmail = n => `buyer-${n}-${stamp}@nonogram.test`;
const PASS = 'test-pass-' + stamp;

class Client {
  constructor() { this.cookies = {}; }
  async req(url, opts = {}) {
    const headers = Object.assign({ cookie: Object.entries(this.cookies).map(([k, v]) => k + '=' + v).join('; ') }, opts.headers || {});
    const r = await fetch(BASE + url, Object.assign({}, opts, { headers, redirect: 'manual' }));
    for (const c of r.headers.getSetCookie ? r.headers.getSetCookie() : []) { const [kv] = c.split(';'); const [k, v] = kv.split('='); this.cookies[k] = v; }
    return r;
  }
  async page(url) { const r = await this.req(url); return { status: r.status, location: r.headers.get('location') || '', html: await r.text() }; }
  /** Follows redirects like a browser (keeps flash messages for the final page). */
  async go(url) { let p = await this.page(url); for (let i = 0; i < 5 && p.location; i++) p = await this.page(new URL(p.location, BASE + url).pathname + new URL(p.location, BASE + url).search); return p; }
  async csrf(url) { const m = (await this.page(url)).html.match(/name="csrf" value="([^"]+)"/); assert.ok(m, 'no CSRF field on ' + url); return m[1]; }
  /** POSTs a form and returns the redirect target and the page it lands on. */
  async post(url, fields, from = url) {
    const body = new URLSearchParams(Object.assign({ csrf: await this.csrf(from) }, fields));
    for (const [k, v] of Object.entries(fields)) if (Array.isArray(v)) { body.delete(k); v.forEach(x => body.append(k, x)); }
    const r = await this.req(url, { method: 'POST', body, headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    const location = r.headers.get('location') || '';
    const landed = location ? await this.go(new URL(location, BASE + url).pathname + new URL(location, BASE + url).search) : { html: await r.text() };
    return { status: r.status, location, html: landed.html };
  }
  async login(email, password = creds[email]) { return this.post('/login.php', { email, password }); }
}
const flashes = html => [...html.matchAll(/class="flash (ok|err|info)"[^>]*>([^<]*)</g)].map(m => m[1] + ': ' + m[2].replace(/&quot;/g, '"').replace(/&#039;/g, "'"));
const mailCount = () => (fs.existsSync(MAIL_LOG) ? fs.readFileSync(MAIL_LOG, 'utf8').split('SUBJECT:').length - 1 : 0);
const lastResetLink = () => { const m = [...fs.readFileSync(MAIL_LOG, 'utf8').replace(/=\r?\n/g, '').replace(/=3D/g, '=').matchAll(/reset\.php\?t=([a-f0-9]{64})/g)]; return m.length ? m[m.length - 1][1] : null; };

let passed = 0;
const t = async (name, fn) => { await fn(); passed++; console.log('ok -', name); };

(async () => {
  const admin = new Client();
  let code, linkId;

  await t('admin can log in and open every admin page', async () => {
    const r = await admin.login('admin@nonogram.test');
    assert.ok(r.location.endsWith('/app/'), 'login redirect ' + r.location);
    for (const p of ['/admin/', '/admin/users.php', '/admin/links.php', '/admin/activity.php', '/app/settings.php', '/app/projects.php']) assert.strictEqual((await admin.page(p)).status, 200, p);
  });

  await t('admin creates an access link for Nonogram Studio + Picture Vault', async () => {
    const r = await admin.post('/admin/links.php', { action: 'create', label: 'E2E FE link ' + stamp, 'products[]': ['fe', 'vault'], max_uses: '2', note: 'test' });
    assert.ok(flashes(r.html).some(f => f.startsWith('ok: Link created')), flashes(r.html).join());
    const m = r.html.match(new RegExp('E2E FE link ' + stamp + '[\\s\\S]*?access\\.php\\?c=([a-f0-9]{24})'));
    assert.ok(m, 'link address on the page'); code = m[1];
    linkId = +r.html.match(new RegExp('name="id" value="(\\d+)"[\\s\\S]{0,400}?E2E FE link ' + stamp + '|E2E FE link ' + stamp + '[\\s\\S]*?name="id" value="(\\d+)"')).slice(1).find(Boolean);
    assert.ok(linkId > 0);
  });

  const buyer = new Client();
  await t('guest opening the link sees what they bought and can create an account', async () => {
    const p = await buyer.page('/access.php?c=' + code);
    assert.ok(p.html.includes('Thanks for your purchase') && p.html.includes('Picture Vault'), 'claim page');
    const r = await buyer.post('/register.php', { name: 'Test Buyer', email: buyerEmail(1), password: PASS, password_confirm: PASS });
    assert.ok(r.location.endsWith('/app/'), 'register redirect ' + r.location);
    assert.ok(flashes(r.html).some(f => f.includes('Unlocked: Nonogram Studio, Picture Vault')), flashes(r.html).join());
    assert.ok(!r.html.includes('no Nonogram Studio license'), 'dashboard unlocked');
  });

  await t('re-opening the link changes nothing', async () => {
    const p = await buyer.go('/access.php?c=' + code);
    assert.ok(flashes(p.html).some(f => f.startsWith('info: Your account already has')), flashes(p.html).join());
  });

  await t('an existing account can log in through the link and gets the products', async () => {
    const fe = new Client();
    await fe.page('/access.php?c=' + code);
    const r = await fe.login('nolicense@nonogram.test');
    assert.ok(flashes(r.html).some(f => f.includes('Unlocked')), flashes(r.html).join());
    // tidy: the dev account should stay without a license
    const id = (await admin.page('/admin/users.php?q=nolicense')).html.match(/admin\/user\.php\?id=(\d+)/)[1];
    await admin.post('/admin/user.php?id=' + id, { action: 'products', note: 'test cleanup' });
  });

  await t('use limit: a third account is refused once the link is used up', async () => {
    const c = new Client();
    const p = await c.page('/access.php?c=' + code);
    assert.ok(p.html.includes('maximum number of times'), 'limit message');
    const r = await c.page('/register.php');
    assert.ok(r.location.endsWith('/login.php'), 'registration closed without a valid link');
  });

  await t('unknown link code shows a helpful error', async () => {
    const p = await new Client().page('/access.php?c=deadbeefdeadbeefdeadbeef');
    assert.ok(p.html.includes('was not recognised'));
  });

  await t('admin sees the redemptions in the activity log and on the link', async () => {
    const a = await admin.page('/admin/activity.php?link=' + linkId);
    assert.ok(a.html.includes(buyerEmail(1)) && a.html.includes('Access link'), 'activity');
    const l = await admin.page('/admin/links.php');
    assert.ok(/2 accounts unlocked/.test(l.html), 'redemption count');
  });

  await t('new address: the old link stops working', async () => {
    await admin.post('/admin/links.php', { action: 'rotate', id: String(linkId) });
    const p = await new Client().page('/access.php?c=' + code);
    assert.ok(p.html.includes('was not recognised'));
  });

  await t('used links cannot be deleted, only switched off', async () => {
    const r = await admin.post('/admin/links.php', { action: 'delete', id: String(linkId) });
    assert.ok(flashes(r.html).some(f => f.includes('Switch it off instead')), flashes(r.html).join());
    const off = await admin.post('/admin/links.php', { action: 'toggle', id: String(linkId) });
    assert.ok(flashes(off.html).some(f => f.includes('switched off')));
  });

  await t('logout: a GET does nothing, a POST with the token signs out', async () => {
    let p = await buyer.page('/logout.php');
    assert.ok((await buyer.page('/app/')).status === 200, 'still signed in after GET');
    const r = await buyer.post('/logout.php', {}, '/app/');
    assert.ok(r.location.endsWith('/login.php'));
    p = await buyer.page('/app/');
    assert.ok(p.location.endsWith('/login.php'), 'signed out');
  });

  await t('forgot password: same answer for unknown emails, and no email is sent', async () => {
    const before = mailCount();
    const r = await new Client().post('/forgot.php', { email: 'nobody-' + stamp + '@nonogram.test' });
    assert.ok(flashes(r.html).some(f => f.includes('If that address has an account')));
    assert.strictEqual(mailCount(), before);
  });

  await t('forgot password: emailed link sets a new password once', async () => {
    const c = new Client();
    await c.post('/forgot.php', { email: buyerEmail(1) });
    const token = lastResetLink(); assert.ok(token, 'reset link in mail.log');
    const r = await c.post('/reset.php?t=' + token, { password: PASS + '-new', password_confirm: PASS + '-new' });
    assert.ok(r.location.endsWith('/app/') && flashes(r.html).some(f => f.includes('password has been changed')));
    const again = await new Client().page('/reset.php?t=' + token);
    assert.ok(again.location.endsWith('/forgot.php'), 'token works only once');
    assert.ok((await new Client().login(buyerEmail(1), PASS)).html.includes('do not match'), 'old password refused');
    assert.ok((await new Client().login(buyerEmail(1), PASS + '-new')).location.endsWith('/app/'), 'new password works');
  });

  await t('settings: password and email changes need the current password', async () => {
    const c = new Client(); await c.login(buyerEmail(1), PASS + '-new');
    let r = await c.post('/app/settings.php', { action: 'password', current_password: 'wrong', password: 'whatever-123', password_confirm: 'whatever-123' });
    assert.ok(flashes(r.html).some(f => f.includes('current password is not right')));
    r = await c.post('/app/settings.php', { action: 'profile', name: 'Renamed Buyer', email: buyerEmail(2), current_password: '' });
    assert.ok(flashes(r.html).some(f => f.includes('enter your current password')));
    r = await c.post('/app/settings.php', { action: 'profile', name: 'Renamed Buyer', email: buyerEmail(2), current_password: PASS + '-new' });
    assert.ok(flashes(r.html).some(f => f.includes('Log in with ' + buyerEmail(2))));
    assert.ok(r.html.includes('Renamed Buyer'));
  });

  await t('customers cannot open admin pages or post admin actions', async () => {
    const c = new Client(); await c.login('fe@nonogram.test');
    for (const p of ['/admin/', '/admin/users.php', '/admin/links.php', '/admin/activity.php', '/admin/user.php?id=1']) assert.strictEqual((await c.page(p)).status, 403, p);
    const csrf = await c.csrf('/app/settings.php');
    const r = await c.req('/admin/links.php', { method: 'POST', body: new URLSearchParams({ csrf, action: 'create', label: 'x', 'products[]': 'fe' }), headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    assert.strictEqual(r.status, 403);
    assert.strictEqual((await new Client().page('/admin/')).location.endsWith('/login.php'), true, 'guest sent to login');
  });

  await t('admin safeguards: cannot demote, disable or delete yourself', async () => {
    const me = (await admin.page('/admin/users.php?q=admin%40nonogram')).html.match(/admin\/user\.php\?id=(\d+)/)[1];
    for (const [action, extra, msg] of [['role', { role: 'user' }, 'own administrator rights'], ['status', { status: 'disabled' }, 'disable your own'], ['delete', { confirm_email: 'admin@nonogram.test' }, 'delete your own']]) {
      const r = await admin.post('/admin/user.php?id=' + me, Object.assign({ action }, extra));
      assert.ok(flashes(r.html).some(f => f.includes(msg)), action + ': ' + flashes(r.html).join());
    }
  });

  await t('admin: refund removes a product and is logged with the reason; disable blocks login', async () => {
    const id = (await admin.page('/admin/users.php?q=' + encodeURIComponent(buyerEmail(2)))).html.match(/admin\/user\.php\?id=(\d+)/)[1];
    let r = await admin.post('/admin/user.php?id=' + id, { action: 'products', 'products[]': ['fe'], note: 'Refund W+ #' + stamp });
    assert.ok(flashes(r.html).some(f => f.includes('Removed Picture Vault')), flashes(r.html).join());
    assert.ok(r.html.includes('Refund W+ #' + stamp), 'reason in history');
    r = await admin.post('/admin/user.php?id=' + id, { action: 'status', status: 'disabled' });
    assert.ok(flashes(r.html).some(f => f.includes('disabled')));
    assert.ok((await new Client().login(buyerEmail(2), PASS + '-new')).html.includes('disabled'), 'login refused');
  });

  await t('admin: delete needs the exact email, then removes the account', async () => {
    const id = (await admin.page('/admin/users.php?q=' + encodeURIComponent(buyerEmail(2)))).html.match(/admin\/user\.php\?id=(\d+)/)[1];
    let r = await admin.post('/admin/user.php?id=' + id, { action: 'delete', confirm_email: 'wrong@x.test' });
    assert.ok(flashes(r.html).some(f => f.includes('did not match')));
    r = await admin.post('/admin/user.php?id=' + id, { action: 'delete', confirm_email: buyerEmail(2) });
    assert.ok(r.location.endsWith('/admin/users.php') && flashes(r.html).some(f => f.includes('have been deleted')));
    assert.ok((await admin.page('/admin/activity.php?link=' + linkId)).html.includes('deleted account #'), 'log keeps the history');
  });

  await t('sessions are stored in the app\'s own folder', async () => {
    assert.ok(fs.readdirSync(path.join(__dirname, '..', 'storage', 'sessions')).some(f => f.startsWith('sess_')));
  });

  console.log(`\n${passed} account tests passed`);
})().catch(e => { console.error(e); process.exit(1); });
