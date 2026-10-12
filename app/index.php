<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$f = features($user);
page_head('Projects', $user, 'dashboard');
?>
<main class="page">
  <?php if (!$f['app']): ?>
    <p class="err-msg">Your account has no active Nonogram Studio license yet. If you just bought it, wait a minute and reload, or contact support.</p>
  <?php else: ?>
  <section class="quick">
    <button class="qa" id="qa-new">
      <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
      <b>New puzzle project</b><span>Start a book or a set of worksheets</span>
    </button>
    <button class="qa" id="qa-upload">
      <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg></span>
      <b>Upload images</b><span>Turn your pictures into puzzles</span>
    </button>
    <div class="qa stat">
      <b id="stat-projects">–</b><span>projects of <?= (int) $f['max_projects'] ?></span>
    </div>
    <div class="qa stat">
      <b id="stat-puzzles">–</b><span>puzzles · grids up to <?= (int) $f['max_grid'] ?>×<?= (int) $f['max_grid'] ?></span>
    </div>
  </section>

  <section class="section">
    <div class="sectionhead">
      <h1>Your projects</h1>
      <input type="search" id="filter" placeholder="Search projects" aria-label="Search projects">
    </div>
    <form class="newproj" id="newproj" hidden>
      <input id="newname" placeholder="Project name, e.g. Animal Nonograms Vol. 1" maxlength="160" required>
      <button class="btn primary">Create</button>
      <button type="button" class="btn" id="newcancel">Cancel</button>
    </form>
    <div class="projects" id="projects"><p class="muted">Loading your projects…</p></div>
  </section>
  <?php endif; ?>
</main>
<?php page_foot(['assets/js/engine.js', 'assets/js/render.js', 'assets/js/api.js', 'assets/js/dashboard.js']); ?>
