/* App shell: the user menu in the top bar, copy buttons, confirm-before-submit forms and fading flash messages. */
(function () {
  'use strict';

  // Menus: [data-menu] holds a [data-menu-trigger] button and a .menu list.
  const closeAll = except => document.querySelectorAll('[data-menu]').forEach(m => {
    if (m === except) return;
    const list = m.querySelector('.menu'); if (list) list.hidden = true;
    const t = m.querySelector('[data-menu-trigger]'); if (t) t.setAttribute('aria-expanded', 'false');
  });
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-menu-trigger]');
    if (t) {
      const m = t.closest('[data-menu]'), list = m.querySelector('.menu'), open = list.hidden;
      closeAll(m); list.hidden = !open; t.setAttribute('aria-expanded', String(open));
      if (open) { const first = list.querySelector('a, button'); if (first) first.focus(); }
      return;
    }
    if (!e.target.closest('[data-menu] .menu')) closeAll();
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const open = e.target.closest && e.target.closest('[data-menu]');
    closeAll();
    if (open) { const t = open.querySelector('[data-menu-trigger]'); if (t) t.focus(); }
  });
  document.addEventListener('focusout', e => {
    const m = e.target.closest && e.target.closest('[data-menu]');
    if (m && e.relatedTarget && !m.contains(e.relatedTarget)) closeAll();
  });

  // <button data-copy="text">: copies and says so for a moment.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-copy]'); if (!b) return;
    const text = b.dataset.copy, label = b.textContent;
    const done = ok => { b.textContent = ok ? 'Copied' : 'Press Ctrl+C'; setTimeout(() => { b.textContent = label; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => done(true), () => done(false));
    else done(false);
  });

  // <form data-confirm="Question?">: asks before submitting.
  document.addEventListener('submit', e => {
    const f = e.target.closest('form[data-confirm]');
    if (f && !confirm(f.dataset.confirm)) e.preventDefault();
  });

  // Flash messages fade after a while; errors stay until the next page.
  document.querySelectorAll('.flashes .flash:not(.err)').forEach((el, i) => setTimeout(() => {
    el.classList.add('gone'); setTimeout(() => el.remove(), 450);
  }, 6000 + i * 400));
})();
