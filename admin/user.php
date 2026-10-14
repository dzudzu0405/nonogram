<?php
// Admin: one account. Change products (refunds), admin rights, disable/enable, send a reset link, delete.
// Three things are refused because each could leave nobody able to get back in: changing your own admin rights,
// disabling or deleting your own account, and removing the last active administrator.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/admin.php';

$me = require_admin();
$id = (int) ($_GET['id'] ?? 0);
$u = q1('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { flash('error', 'That account does not exist (it may have been deleted).'); redirect('admin/users.php'); }
$back = 'admin/user.php?id=' . $id;
$self = $id === (int) $me['id'];
$lastAdmin = $u['role'] === 'admin' && $u['status'] === 'active' && admin_count() <= 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $ctx = ['actor_id' => (int) $me['id'], 'note' => trim((string) ($_POST['note'] ?? '')), 'ip' => client_ip()];
    switch ($action) {
        case 'products':
            $want = array_values(array_intersect(array_keys(PRODUCTS), (array) ($_POST['products'] ?? [])));
            $have = user_products($id);
            $added = $removed = [];
            foreach (array_diff($want, $have) as $p) if (grant_product($id, $p, 'admin', null, $ctx)) $added[] = $p;
            foreach (array_diff($have, $want) as $p) if (revoke_product($id, $p, 'admin', $ctx)) $removed[] = $p;
            if (!$added && !$removed) flash('info', 'No changes: the products were already like that.');
            else flash('success', trim(($added ? 'Added ' . product_names($added) . '. ' : '') . ($removed ? 'Removed ' . product_names($removed) . '. ' : '') . 'Their projects are untouched.'));
            break;
        case 'role':
            $toAdmin = ($_POST['role'] ?? '') === 'admin';
            if ($self) flash('error', 'You cannot change your own administrator rights. Ask another administrator.');
            elseif (!$toAdmin && $lastAdmin) flash('error', 'That is the only administrator left. Make someone else an administrator first.');
            else { q('UPDATE users SET role = ? WHERE id = ?', [$toAdmin ? 'admin' : 'user', $id]); flash('success', $toAdmin ? 'Now an administrator.' : 'No longer an administrator.'); }
            break;
        case 'status':
            $enable = ($_POST['status'] ?? '') === 'active';
            if ($self) flash('error', 'You cannot disable your own account.');
            elseif (!$enable && $lastAdmin) flash('error', 'That is the only administrator left, so the account has to stay active.');
            else { q('UPDATE users SET status = ? WHERE id = ?', [$enable ? 'active' : 'disabled', $id]); flash('success', $enable ? 'The account can log in again.' : 'The account is disabled: it cannot log in. Nothing was deleted.'); }
            break;
        case 'reset':
            if (!mail_usable()) flash('error', 'Email is not set up in config.php, so a reset link cannot be sent.');
            elseif ($u['status'] !== 'active') flash('error', 'Enable the account first.');
            elseif (send_reset_link($u)) flash('success', 'A reset link was emailed to ' . $u['email'] . '. It works for ' . RESET_MINUTES . ' minutes.');
            else flash('error', 'The email could not be sent: ' . mail_last_error());
            break;
        case 'delete':
            if ($self) flash('error', 'You cannot delete your own account.');
            elseif ($lastAdmin) flash('error', 'That is the only administrator left. Make someone else an administrator first.');
            elseif (normalize_email((string) ($_POST['confirm_email'] ?? '')) !== $u['email']) flash('error', 'The email did not match, so nothing was deleted.');
            else {
                db()->beginTransaction();
                q('DELETE FROM puzzles WHERE user_id = ?', [$id]);
                q('DELETE FROM projects WHERE user_id = ?', [$id]);
                q('DELETE FROM entitlements WHERE user_id = ?', [$id]);
                q('DELETE FROM tokens WHERE user_id = ?', [$id]);
                q('DELETE FROM users WHERE id = ?', [$id]);
                db()->commit();
                flash('success', $u['email'] . ' and all of its projects have been deleted. The product log keeps its history.');
                redirect('admin/users.php');
            }
            break;
    }
    redirect($back);
}

$have = user_products($id);
$projects = (int) qv('SELECT COUNT(*) FROM projects WHERE user_id = ?', [$id]);
$puzzles = (int) qv('SELECT COUNT(*) FROM puzzles WHERE user_id = ?', [$id]);
$events = q('SELECT * FROM product_events WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$id])->fetchAll();

admin_begin($u['name'] ?: $u['email'], 'Users');
?>
  <p><a class="back" href="<?= e(url('admin/users.php')) ?>">← All users</a></p>
  <div class="settings-grid">
    <div class="stack">
      <section class="card">
        <div class="who-row"><span class="avatar lg"><?= e(initials((string) $u['name'], $u['email'])) ?></span><div><b><?= e($u['name'] ?: '—') ?></b><span class="muted small"><?= e($u['email']) ?></span></div>
          <span class="tags"><?= $u['role'] === 'admin' ? '<span class="chip">Admin</span>' : '' ?><?= $u['status'] === 'active' ? '<span class="chip ok">Active</span>' : '<span class="chip bad">Disabled</span>' ?><?= $self ? '<span class="chip">You</span>' : '' ?></span></div>
        <dl class="kv cols">
          <dt>Joined</dt><dd><?= e(gmdate('j M Y', strtotime($u['created_at'] . ' UTC'))) ?></dd>
          <dt>Last login</dt><dd><?= e(time_ago($u['last_login_at'])) ?></dd>
          <dt>Projects</dt><dd><?= $projects ?></dd>
          <dt>Puzzles</dt><dd><?= $puzzles ?></dd>
        </dl>
      </section>

      <section class="card">
        <div class="cardhead"><h2>Products</h2></div>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="products">
          <div class="prodgrid">
            <?php foreach (PRODUCTS as $code => $p): ?>
              <label class="check prodcheck"><input type="checkbox" name="products[]" value="<?= e($code) ?>" <?= in_array($code, $have, true) ? 'checked' : '' ?>><span><b><?= e($p['name']) ?></b><small><?= e($p['step']) ?></small></span></label>
            <?php endforeach; ?>
          </div>
          <label>Reason <span class="muted small">(kept in the log, e.g. "Refund W+ #12345")</span><input name="note" maxlength="255" placeholder="Optional"></label>
          <div><button class="btn primary">Save products</button></div>
        </form>
        <?php if ($u['role'] === 'admin'): ?><p class="muted small">Administrators can use every feature whatever is ticked here.</p><?php endif; ?>
      </section>

      <section class="card">
        <div class="cardhead"><h2>Product history</h2></div>
        <?php if (!$events): ?><p class="muted">No product changes recorded for this account.</p><?php endif; ?>
        <ul class="eventlist"><?php foreach ($events as $ev): ?><li><?= event_line($ev, false) ?></li><?php endforeach; ?></ul>
      </section>
    </div>

    <aside class="stack">
      <section class="card actions">
        <div class="cardhead"><h2>Account</h2></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset">
          <button class="btn block" <?= mail_usable() && $u['status'] === 'active' ? '' : 'disabled' ?>><?= icon('link', 16) ?>Email a password reset link</button></form>
        <form method="post" <?= $u['status'] === 'active' ? 'data-confirm="Disable this account? They will not be able to log in until you enable it again."' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>">
          <button class="btn block" <?= $self || ($u['status'] === 'active' && $lastAdmin) ? 'disabled' : '' ?>><?= $u['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
          <button class="btn block" <?= $self || ($u['role'] === 'admin' && $lastAdmin) ? 'disabled' : '' ?>><?= $u['role'] === 'admin' ? 'Remove administrator rights' : 'Make administrator' ?></button></form>
        <?php if ($self): ?><p class="muted small">This is your own account, so rights and status are locked.</p><?php endif; ?>
      </section>
      <section class="card danger-zone">
        <div class="cardhead"><h2>Delete account</h2></div>
        <p class="muted small">Deletes the account and all of its projects and puzzles for good. Disabling is usually the better choice.</p>
        <form method="post" class="form" data-confirm="Delete this account and all of its projects? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <label>Type the email to confirm<input name="confirm_email" autocomplete="off" placeholder="<?= e($u['email']) ?>"></label>
          <button class="btn danger-solid block" <?= $self || $lastAdmin ? 'disabled' : '' ?>>Delete account</button></form>
      </section>
    </aside>
  </div>
<?php admin_end(); ?>
