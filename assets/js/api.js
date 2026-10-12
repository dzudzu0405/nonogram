/* Small helpers shared by the app pages: API calls, toasts, escaping, downloads. */
(function () {
  'use strict';

  async function call(path, method = 'GET', body) {
    let res;
    try {
      res = await fetch(NS.base + '/api/' + path, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': NS.csrf },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (e) {
      throw { status: 0, error: 'Could not reach the server. Check your connection.' };
    }
    let data;
    try { data = await res.json(); } catch (e) { throw { status: res.status, error: 'The server sent an unexpected reply (' + res.status + ').' }; }
    if (res.status === 401) { location.href = NS.base + '/login.php'; throw data; }
    if (!res.ok || data.ok === false) throw Object.assign({ status: res.status }, data);
    return data;
  }

  function toast(msg, kind) {
    document.querySelectorAll('.toast').forEach(t => t.remove());
    const t = document.createElement('div');
    t.className = 'toast' + (kind === 'err' ? ' err' : '');
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), kind === 'err' ? 5000 : 2800);
  }

  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const slug = s => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'puzzle';

  function download(blob, name) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = name;
    document.body.appendChild(a); a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
  }

  function fmtDate(s) {
    const d = new Date(String(s).replace(' ', 'T') + 'Z');
    return isNaN(d) ? '' : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
  }

  window.App = {
    get: p => call(p, 'GET'),
    post: (p, b) => call(p, 'POST', b || {}),
    toast, esc, slug, download, fmtDate,
    fail: e => toast((e && e.error) || 'Something went wrong.', 'err'),
  };
})();
