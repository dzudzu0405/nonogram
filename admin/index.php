<?php
// Admin overview: account totals, who owns what, recent product changes, newest accounts, setup warnings.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/admin.php';
require_admin();

$weekAgo = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
$stats = [
    ['Accounts', (int) qv('SELECT COUNT(*) FROM users'), (int) qv('SELECT COUNT(*) FROM users WHERE created_at > ?', [$weekAgo]) . ' new this week'],
    ['Licensed', (int) qv("SELECT COUNT(DISTINCT user_id) FROM entitlements WHERE product = 'fe' AND active = 1"), 'own Nonogram Studio'],
    ['Link unlocks', (int) qv("SELECT COUNT(*) FROM product_events WHERE source = 'link' AND action = 'grant'"), (int) qv("SELECT COUNT(*) FROM product_events WHERE source = 'link' AND action = 'grant' AND created_at > ?", [$weekAgo]) . ' this week'],
    ['Projects', (int) qv('SELECT COUNT(*) FROM projects'), (int) qv('SELECT COUNT(*) FROM puzzles') . ' puzzles'],
    ['Disabled', (int) qv("SELECT COUNT(*) FROM users WHERE status <> 'active'"), 'cannot log in'],
];
$owners = [];
foreach (q('SELECT product, COUNT(DISTINCT user_id) AS n FROM entitlements WHERE active = 1 GROUP BY product')->fetchAll() as $r) $owners[$r['product']] = (int) $r['n'];
$events = q('SELECT * FROM product_events ORDER BY id DESC LIMIT 8')->fetchAll();
$newest = q('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC LIMIT 6')->fetchAll();
$warnings = [];
if (!config('app_url')) $warnings[] = 'Set app_url in config.php (for example https://studio.yourdomain.com) so access links and reset emails always point to your site.';
if (!mail_ready()) $warnings[] = 'Email is not set up (mail block in config.php), so password reset and welcome emails cannot be sent.';
if (!(int) qv('SELECT COUNT(*) FROM access_links WHERE is_active = 1')) $warnings[] = 'No active access links yet. Create one per WarriorPlus product under Access links.';

admin_begin('Administration', 'Overview');
?>
  <?php foreach ($warnings as $w): ?><p class="notice"><?= e($w) ?></p><?php endforeach; ?>
  <section class="summary five">
    <?php foreach ($stats as [$label, $n, $note]): ?>
      <div class="stat"><span class="stat-label"><?= e($label) ?></span><span class="stat-value"><b><?= $n ?></b></span><span class="stat-note"><?= e($note) ?></span></div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <div class="cardhead"><h2>Who owns what</h2><a class="seeall" href="<?= e(url('admin/users.php')) ?>">Manage users <?= icon('arrow', 14) ?></a></div>
    <div class="ownergrid">
      <?php foreach (PRODUCTS as $code => $p): ?>
        <a class="owner" href="<?= e(url('admin/users.php?product=' . $code)) ?>"><b><?= $owners[$code] ?? 0 ?></b><span><?= e($p['name']) ?></span><small><?= e($p['step']) ?></small></a>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="two">
    <section class="card">
      <div class="cardhead"><h2>Recent product changes</h2><a class="seeall" href="<?= e(url('admin/activity.php')) ?>">Full log <?= icon('arrow', 14) ?></a></div>
      <?php if (!$events): ?><p class="muted">Nothing yet. Changes appear here the moment a buyer opens an access link or you change someone's products.</p><?php endif; ?>
      <ul class="eventlist"><?php foreach ($events as $ev): ?><li><?= event_line($ev) ?></li><?php endforeach; ?></ul>
    </section>
    <section class="card">
      <div class="cardhead"><h2>Newest accounts</h2><a class="seeall" href="<?= e(url('admin/users.php')) ?>">All users <?= icon('arrow', 14) ?></a></div>
      <ul class="userlist">
        <?php foreach ($newest as $u): ?>
          <li><span class="avatar"><?= e(initials((string) $u['name'], $u['email'])) ?></span><a href="<?= e(url('admin/user.php?id=' . (int) $u['id'])) ?>"><b><?= e($u['name'] ?: $u['email']) ?></b><span class="muted small"><?= e($u['email']) ?></span></a><span class="muted small"><?= e(time_ago($u['created_at'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
<?php admin_end(); ?>
