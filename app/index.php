<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$f = features($user);
page_head('Projects', $user, 'dashboard');
$firstName = explode(' ', trim($user['name']))[0] ?: explode('@', $user['email'])[0];
?>
<main class="page">
  <?php if (!$f['app']): ?>
    <p class="err-msg">Your account has no active Nonogram Studio license yet. If you just bought it, wait a minute and reload, or contact support.</p>
  <?php else: ?>
  <section class="hero">
    <div class="hero-text">
      <p class="eyebrow" id="greet" data-name="<?= e($firstName) ?>">Welcome back, <?= e($firstName) ?></p>
      <h1>Your puzzle projects</h1>
      <p class="muted">One project = one puzzle book or worksheet set. Open a project to add pictures, check every puzzle and export the PDF.</p>
    </div>
    <div class="hero-actions">
      <button class="btn lg" id="qa-upload">
        <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>Upload images
      </button>
      <button class="btn lg primary" id="qa-new" title="Shortcut: N">
        <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New project
      </button>
    </div>
  </section>

  <section class="summary" aria-label="Summary">
    <div class="stat">
      <span class="stat-label">Projects</span>
      <span class="stat-value"><b id="stat-projects">–</b><small>/ <?= (int) $f['max_projects'] ?></small></span>
      <span class="meter" aria-hidden="true"><i id="stat-meter"></i></span>
    </div>
    <div class="stat">
      <span class="stat-label">Puzzles</span>
      <span class="stat-value"><b id="stat-puzzles">–</b></span>
      <span class="stat-note">up to <?= (int) $f['max_book'] ?> per project</span>
    </div>
    <button class="stat stat-btn" id="stat-check" type="button" title="Show projects that need checking">
      <span class="stat-label">Need checking</span>
      <span class="stat-value"><b id="stat-warn">–</b></span>
      <span class="stat-note" id="stat-warn-note">puzzles with more than one solution</span>
    </button>
    <div class="stat">
      <span class="stat-label">Your plan</span>
      <span class="stat-value"><b><?= (int) $f['max_grid'] ?>×<?= (int) $f['max_grid'] ?></b></span>
      <span class="stat-note"><?= $f['color'] ? 'Color puzzles on' : ($f['pro'] ? 'Black &amp; white' : 'Black &amp; white · Pro adds 50×50 and color') ?></span>
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
    <div class="projects" id="projects" aria-live="polite">
      <?php for ($i = 0; $i < 4; $i++): ?><div class="pcard skeleton"><div class="thumb"></div><div class="pbody"><span></span><span></span></div></div><?php endfor; ?>
    </div>
  </section>

  <dialog class="dlg" id="dlg-name">
    <form method="dialog" class="form">
      <h2 id="dlg-name-title">New project</h2>
      <label>Project name<input id="dlg-name-input" maxlength="160" required placeholder="e.g. Animal Nonograms Vol. 1" autocomplete="off"></label>
      <div class="suggest" id="dlg-suggest">
        <span class="muted small">Ideas:</span>
        <button type="button" class="chip">Animal Nonograms Vol. 1</button>
        <button type="button" class="chip">Kids Worksheets</button>
        <button type="button" class="chip">Holiday Puzzle Book</button>
      </div>
      <div class="dlg-actions">
        <button type="button" class="btn" value="cancel" data-close>Cancel</button>
        <button class="btn primary" id="dlg-name-ok" value="ok">Create project</button>
      </div>
    </form>
  </dialog>

  <dialog class="dlg" id="dlg-confirm">
    <form method="dialog" class="form">
      <h2 id="dlg-confirm-title">Delete project?</h2>
      <p class="muted" id="dlg-confirm-text"></p>
      <div class="dlg-actions">
        <button type="button" class="btn" data-close>Cancel</button>
        <button class="btn danger-solid" value="ok">Delete</button>
      </div>
    </form>
  </dialog>
  <?php endif; ?>
</main>
<?php page_foot(['assets/js/engine.js', 'assets/js/render.js', 'assets/js/api.js', 'assets/js/dashboard.js']); ?>
