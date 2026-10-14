<?php
// My Projects: every project, with filters, search, sort and grid/list layout.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$f = features($user);
if (!$f['app']) redirect('app/');
page_head('My Projects', $user, 'projects');
?>
<main class="page">
  <section class="pagehead">
    <div>
      <h1>My Projects <span class="countpill" id="n-total">–</span></h1>
      <p class="muted">One project = one puzzle book or worksheet set.</p>
    </div>
    <div class="hero-actions">
      <button class="btn lg" id="qa-upload"><?= icon('upload') ?>Upload images</button>
      <button class="btn lg primary" id="qa-new" title="Shortcut: N"><?= icon('plus') ?>New project</button>
    </div>
  </section>

  <section class="section">
    <div class="toolbar">
      <div class="seg" role="tablist" aria-label="Show">
        <button role="tab" data-show="all" aria-selected="true">All <span id="n-all">0</span></button>
        <button role="tab" data-show="warn" aria-selected="false">Need checking <span id="n-warn">0</span></button>
        <button role="tab" data-show="empty" aria-selected="false">Empty <span id="n-empty">0</span></button>
      </div>
      <div class="tools">
        <label class="search">
          <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
          <input type="search" id="filter" placeholder="Search projects" aria-label="Search projects">
          <kbd>/</kbd>
        </label>
        <select id="sort" aria-label="Sort projects">
          <option value="updated">Last edited</option>
          <option value="name">Name A–Z</option>
          <option value="count">Most puzzles</option>
          <option value="created">Newest first</option>
        </select>
        <div class="viewtoggle" role="group" aria-label="Layout">
          <button data-view="grid" aria-pressed="true" title="Grid"><svg class="ic" viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg></button>
          <button data-view="list" aria-pressed="false" title="List"><svg class="ic" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        </div>
      </div>
    </div>
    <div class="projects" id="projects" data-mode="all" aria-live="polite">
      <?php for ($i = 0; $i < 4; $i++): ?><div class="pcard skeleton"><div class="thumb"></div><div class="pbody"><span></span><span></span></div></div><?php endfor; ?>
    </div>
  </section>
<?php require __DIR__ . '/_dialogs.php'; ?>
</main>
<?php page_foot(['assets/js/engine.js', 'assets/js/render.js', 'assets/js/api.js', 'assets/js/dashboard.js']); ?>
