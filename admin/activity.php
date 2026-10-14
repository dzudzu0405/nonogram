<?php
// Admin: every product grant and revoke, newest first. Filter by source, by access link or by account.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/admin.php';

require_admin();
$source = isset(EVENT_SOURCES[$_GET['source'] ?? '']) ? $_GET['source'] : '';
$linkId = (int) ($_GET['link'] ?? 0);
$userId = (int) ($_GET['user'] ?? 0);
$where = []; $args = [];
if ($source !== '') { $where[] = 'source = ?'; $args[] = $source; }
if ($linkId) { $where[] = 'link_id = ?'; $args[] = $linkId; }
if ($userId) { $where[] = 'user_id = ?'; $args[] = $userId; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) qv('SELECT COUNT(*) FROM product_events' . $sqlWhere, $args);
[$offset, $pager] = paginate($total, 100);
$events = q('SELECT * FROM product_events' . $sqlWhere . ' ORDER BY id DESC LIMIT 100 OFFSET ' . (int) $offset, $args)->fetchAll();
$link = $linkId ? q1('SELECT * FROM access_links WHERE id = ?', [$linkId]) : null;

admin_begin('Activity', 'Activity');
?>
  <form class="card filters" method="get">
    <select name="source" aria-label="Source"><option value="">Every source</option><?php foreach (EVENT_SOURCES as $k => $v): ?><option value="<?= e($k) ?>" <?= $source === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <?php if ($linkId): ?><input type="hidden" name="link" value="<?= $linkId ?>"><span class="chip">Link: <?= e($link['label'] ?? '#' . $linkId) ?></span><?php endif; ?>
    <?php if ($userId): ?><input type="hidden" name="user" value="<?= $userId ?>"><span class="chip">Account: <?= e(user_email($userId)) ?></span><?php endif; ?>
    <button class="btn primary">Filter</button>
    <?php if ($source || $linkId || $userId): ?><a class="btn" href="<?= e(url('admin/activity.php')) ?>">Clear</a><?php endif; ?>
    <span class="countpill"><?= $total ?> change<?= $total === 1 ? '' : 's' ?></span>
  </form>
  <section class="card">
    <?php if (!$events): ?><p class="muted">No product changes<?= $where ? ' match this filter' : ' yet' ?>.</p><?php endif; ?>
    <ul class="eventlist"><?php foreach ($events as $ev): ?><li><?= event_line($ev) ?><?= $ev['ip'] ? ' <span class="muted small">· ' . e($ev['ip']) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  </section>
  <?= $pager ?>
<?php admin_end(); ?>
