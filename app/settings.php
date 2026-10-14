<?php
// Account settings: name and email, password, and a summary of the account.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$uid = (int) $user['id'];
$back = 'app/settings.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($back);
    $row = q1('SELECT * FROM users WHERE id = ?', [$uid]);
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = str_limit((string) ($_POST['name'] ?? ''), 120);
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        if ($name === '') flash('error', 'Enter your name.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('error', 'Enter a valid email address.');
        elseif ($email !== $row['email'] && !password_verify((string) ($_POST['current_password'] ?? ''), (string) $row['password_hash'])) flash('error', 'To change your email, enter your current password.');
        elseif ($email !== $row['email'] && qv('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid])) flash('error', 'That email already belongs to another account.');
        else {
            q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $uid]);
            flash('success', $email !== $row['email'] ? 'Saved. Log in with ' . $email . ' from now on.' : 'Your details are saved.');
        }
    } elseif ($action === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $row['password_hash'])) flash('error', 'Your current password is not right.');
        elseif ($p = password_problem($new, (string) ($_POST['password_confirm'] ?? ''))) flash('error', $p);
        else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            q("UPDATE tokens SET used_at = ? WHERE user_id = ? AND purpose = 'reset' AND used_at IS NULL", [now(), $uid]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
        }
    }
    redirect($back);
}

$f = features($user);
$row = q1('SELECT * FROM users WHERE id = ?', [$uid]);
$nums = [
    'Projects' => (int) qv('SELECT COUNT(*) FROM projects WHERE user_id = ?', [$uid]),
    'Puzzles' => (int) qv('SELECT COUNT(*) FROM puzzles WHERE user_id = ?', [$uid]),
    'Member since' => gmdate('j M Y', strtotime($row['created_at'] . ' UTC')),
];
page_head('Settings', $user, 'settings');
?>
<main class="page">
  <section class="pagehead"><div><h1>Settings</h1><p class="muted">Your account details and password.</p></div></section>
  <div class="settings-grid">
    <div class="stack">
      <section class="card">
        <div class="cardhead"><h2>Account details</h2></div>
        <div class="who-row"><span class="avatar lg"><?= e(initials((string) $row['name'], $row['email'])) ?></span><div><b><?= e($row['name']) ?></b><span class="muted small"><?= e($row['email']) ?><?= is_admin($user) ? ' · Administrator' : '' ?></span></div></div>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile">
          <div class="row2">
            <label>Name<input name="name" value="<?= e($row['name']) ?>" maxlength="120" required autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="<?= e($row['email']) ?>" required autocomplete="email"></label>
          </div>
          <label>Current password <span class="muted small">(only needed to change your email)</span><input name="current_password" type="password" autocomplete="current-password"></label>
          <div><button class="btn primary"><?= icon('check', 16) ?>Save details</button></div>
        </form>
      </section>
      <section class="card">
        <div class="cardhead"><h2>Change password</h2></div>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
          <div class="row2">
            <label>New password (<?= PASSWORD_MIN ?>+ characters)<input name="password" type="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password"></label>
            <label>Confirm new password<input name="password_confirm" type="password" required autocomplete="new-password"></label>
          </div>
          <div><button class="btn primary">Change password</button></div>
        </form>
      </section>
    </div>
    <aside class="stack">
      <section class="card">
        <div class="cardhead"><h2>Your products</h2></div>
        <div class="owned"><?php foreach (is_admin($user) ? ['Admin · all features'] : array_map(fn($p) => PRODUCTS[$p]['name'], $f['products']) as $n): ?><span class="ownchip"><?= icon('check', 14) ?><?= e($n) ?></span><?php endforeach; ?>
        <?php if (!$f['products'] && !is_admin($user)): ?><p class="muted small">No products yet. Open the access link from your WarriorPlus receipt to unlock them.</p><?php endif; ?></div>
      </section>
      <section class="card">
        <div class="cardhead"><h2>Your numbers</h2></div>
        <dl class="kv"><?php foreach ($nums as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e((string) $v) ?></dd><?php endforeach; ?></dl>
      </section>
      <section class="card">
        <dl class="kv"><dt>Version</dt><dd><?= e(config('app_name')) ?> <?= e(APP_VERSION) ?></dd></dl>
        <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn block"><?= icon('logout', 16) ?>Log out</button></form>
      </section>
    </aside>
  </div>
</main>
<?php page_foot(); ?>
