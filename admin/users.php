<?php
// Admin: find accounts by name or email, filter by product, role and status.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/admin.php';
require_admin();

$term = trim((string) ($_GET['q'] ?? ''));
$product = isset(PRODUCTS[$_GET['product'] ?? '']) ? $_GET['product'] : '';
$role = in_array($_GET['role'] ?? '', ['admin', 'user'], true) ? $_GET['role'] : '';
$status = in_array($_GET['status'] ?? '', ['active', 'disabled'], true) ? $_GET['status'] : '';

$where = []; $args = [];
if ($term !== '') { $where[] = '(u.email LIKE ? OR u.name LIKE ?)'; $args[] = "%$term%"; $args[] = "%$term%"; }
if ($product !== '') { $where[] = 'EXISTS (SELECT 1 FROM entitlements x WHERE x.user_id = u.id AND x.product = ? AND x.active = 1)'; $args[] = $product; }
if ($role !== '') { $where[] = 'u.role = ?'; $args[] = $role; }
if ($status === 'active') $where[] = "u.status = 'active'";
if ($status === 'disabled') $where[] = "u.status <> 'active'";
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) qv('SELECT COUNT(*) FROM users u' . $sqlWhere, $args);
[$offset, $pager] = paginate($total, 50);
$rows = q('SELECT u.id, u.name, u.email, u.role, u.status, u.last_login_at, u.created_at,
    (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id) AS projects
    FROM users u' . $sqlWhere . ' ORDER BY u.id DESC LIMIT 50 OFFSET ' . (int) $offset, $args)->fetchAll();
$owned = [];
if ($rows) {
    $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
    foreach (q("SELECT user_id, product FROM entitlements WHERE active = 1 AND user_id IN ($ids)")->fetchAll() as $r) $owned[(int) $r['user_id']][] = $r['product'];
}

admin_begin('Users', 'Users');
?>
  <form class="card filters" method="get">
    <label class="search"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg><input type="search" name="q" value="<?= e($term) ?>" placeholder="Name or email" aria-label="Name or email"></label>
    <select name="product" aria-label="Product"><option value="">Any product</option><?php foreach (PRODUCTS as $c => $p): ?><option value="<?= e($c) ?>" <?= $product === $c ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
    <select name="role" aria-label="Role"><option value="">Any role</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option><option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Customers</option></select>
    <select name="status" aria-label="Status"><option value="">Any status</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
    <button class="btn primary">Search</button>
    <span class="countpill"><?= $total ?> account<?= $total === 1 ? '' : 's' ?></span>
  </form>

  <div class="card tablecard">
    <table class="table">
      <thead><tr><th>Account</th><th>Products</th><th>Status</th><th class="num">Projects</th><th>Last seen</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted center">No accounts match.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): $prods = $owned[(int) $r['id']] ?? []; ?>
          <tr>
            <td><div class="acct"><span class="avatar"><?= e(initials((string) $r['name'], $r['email'])) ?></span><div><a href="<?= e(url('admin/user.php?id=' . (int) $r['id'])) ?>"><b><?= e($r['name'] ?: '—') ?></b></a><?= $r['role'] === 'admin' ? ' <span class="chip">Admin</span>' : '' ?><span class="muted small"><?= e($r['email']) ?></span></div></div></td>
            <td><?php if (!$prods): ?><span class="muted small">none</span><?php endif; ?><?php foreach ($prods as $p): ?><span class="chip prod"><?= e(PRODUCTS[$p]['name'] ?? $p) ?></span> <?php endforeach; ?></td>
            <td><?= $r['status'] === 'active' ? '<span class="chip ok">Active</span>' : '<span class="chip bad">Disabled</span>' ?></td>
            <td class="num"><?= (int) $r['projects'] ?></td>
            <td class="muted small"><?= e(time_ago($r['last_login_at'])) ?></td>
            <td><a class="btn sm" href="<?= e(url('admin/user.php?id=' . (int) $r['id'])) ?>">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $pager ?>
<?php admin_end(); ?>
