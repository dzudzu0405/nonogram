<?php
// Dashboard: a welcome, the summary numbers, how it works, the four latest projects and the plan card.
// The full list with filters lives on My Projects (app/projects.php).
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$f = features($user);
page_head('Dashboard', $user, 'dashboard');
$firstName = explode(' ', trim($user['name']))[0] ?: explode('@', $user['email'])[0];
$offers = upgrade_offers($user);
$steps = [
    ['picture', 'Pick a picture', 'Use a starter picture or upload your own PNG, JPG or WEBP. Several at once go straight into the project.'],
    ['check2', 'Check every puzzle', 'Each puzzle is solved for you. Auto-fix makes sure it has exactly one solution, and you see the difficulty.'],
    ['book', 'Build the book', 'Order the puzzles, pick the page size and keep going until the project has all the puzzles you want.'],
    ['printer', 'Export the PDF', 'Puzzles first, solutions at the back, fonts embedded: ready for KDP or Etsy.'],
];
?>
<main class="page">
  <?php if (!$f['app']): ?>
    <section class="emptystate nolicense">
      <h2>Your account has no Nonogram Studio license yet</h2>
      <p class="muted">Bought on WarriorPlus? Open the access link from your purchase receipt while you are logged in, and everything unlocks at once.</p>
      <p class="muted small">Still stuck? Contact support with your WarriorPlus receipt.</p>
    </section>
  <?php else: ?>
  <section class="hero welcome">
    <div class="hero-text">
      <p class="eyebrow" id="greet" data-name="<?= e($firstName) ?>">Welcome back, <?= e($firstName) ?></p>
      <h1>Make your next puzzle book</h1>
      <p class="muted">Turn pictures into Nonogram puzzles with exactly one solution, then export a print-ready PDF.</p>
      <div class="hero-actions">
        <button class="btn lg primary" id="qa-new" title="Shortcut: N"><?= icon('plus') ?>New project</button>
        <button class="btn lg" id="qa-upload"><?= icon('upload') ?>Upload images</button>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true"><canvas id="hero-art" width="220" height="220"></canvas></div>
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

  <section class="card howto">
    <div class="cardhead"><h2>How it works</h2><span class="countpill">4 steps</span></div>
    <ol class="howsteps">
      <?php foreach ($steps as $i => [$ic, $title, $text]): ?>
        <li><span class="howicon"><?= icon($ic, 20) ?></span><div><b><span class="howno"><?= $i + 1 ?></span><?= e($title) ?></b><span><?= e($text) ?></span></div></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="section">
    <div class="sectionhead">
      <h2>Recent projects <span class="countpill" id="n-total">–</span></h2>
      <a class="seeall" href="<?= e(url('app/projects.php')) ?>">View all <?= icon('arrow', 14) ?></a>
    </div>
    <div class="projects" id="projects" data-mode="recent" aria-live="polite">
      <?php for ($i = 0; $i < 4; $i++): ?><div class="pcard skeleton"><div class="thumb"></div><div class="pbody"><span></span><span></span></div></div><?php endfor; ?>
    </div>
  </section>

  <section class="card plan">
    <div class="cardhead"><h2>Your plan</h2><?php if (is_admin($user)): ?><a class="seeall" href="<?= e(url('admin/links.php')) ?>">Access links <?= icon('arrow', 14) ?></a><?php endif; ?></div>
    <div class="planbody">
      <div class="owned">
        <?php foreach (is_admin($user) ? ['Admin · all features'] : array_map(fn($p) => PRODUCTS[$p]['name'], $f['products']) as $name): ?>
          <span class="ownchip"><?= icon('check', 14) ?><?= e($name) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="quota-grid">
        <div class="quota"><b><?= (int) $f['max_grid'] ?>×<?= (int) $f['max_grid'] ?></b><span>Largest grid</span></div>
        <div class="quota"><b><?= $f['color'] ? 'Yes' : 'No' ?></b><span>Color puzzles</span></div>
        <div class="quota"><b><?= (int) $f['max_book'] ?></b><span>Puzzles per project</span></div>
        <div class="quota"><b><?= $f['vault'] ? 'Yes' : 'No' ?></b><span>Picture Vault</span></div>
      </div>
      <?php if ($offers): ?>
        <div class="offers">
          <?php foreach ($offers as $o): ?><a class="btn <?= $o === $offers[0] ? 'primary' : '' ?>" href="<?= e($o['url']) ?>" target="_blank" rel="noopener">Get <?= e($o['name']) ?> <?= icon('arrow', 14) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php require __DIR__ . '/_dialogs.php'; ?>
  <?php endif; ?>
</main>
<?php page_foot($f['app'] ? ['assets/js/engine.js', 'assets/js/render.js', 'assets/js/api.js', 'assets/js/dashboard.js'] : []); ?>
