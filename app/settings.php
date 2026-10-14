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
    $hasPassword = !empty($row['password_hash']);
    if ($action === 'profile') {
        $name = str_limit((string) ($_POST['name'] ?? ''), 120);
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        if ($name === '') flash('error', 'Enter your name.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('error', 'Enter a valid email address.');
        elseif ($email !== $row['email'] && !$hasPassword) flash('error', 'Set a password first (below), then you can change your email.');
        elseif ($email !== $row['email'] && !password_verify((string) ($_POST['current_password'] ?? ''), (string) $row['password_hash'])) flash('error', 'To change your email, enter your current password.');
        elseif ($email !== $row['email'] && qv('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid])) flash('error', 'That email already belongs to another account.');
        else {
            q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $uid]);
            flash('success', $email !== $row['email'] ? 'Saved. Log in with ' . $email . ' from now on.' : 'Your details are saved.');
        }
    } elseif ($action === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        // Accounts made with Google have no password yet: they may set one without a current password.
        if ($hasPassword && !password_verify((string) ($_POST['current_password'] ?? ''), (string) $row['password_hash'])) flash('error', 'Your current password is not right.');
        elseif ($p = password_problem($new, (string) ($_POST['password_confirm'] ?? ''))) flash('error', $p);
        else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            q("UPDATE tokens SET used_at = ? WHERE user_id = ? AND purpose = 'reset' AND used_at IS NULL", [now(), $uid]);
            session_regenerate_id(true);
            flash('success', $hasPassword ? 'Your password has been changed.' : 'Password set. You can now log in with your email and password too.');
        }
    } elseif ($action === 'unlink_google') {
        if (!$hasPassword) flash('error', 'Set a password first, or you would have no way to log in.');
        else { q('UPDATE users SET google_id = NULL WHERE id = ?', [$uid]); flash('success', 'Google is no longer linked. Log in with your email and password.'); }
    }
    redirect($back);
}

$f = features($user);
$row = q1('SELECT * FROM users WHERE id = ?', [$uid]);
$hasPassword = !empty($row['password_hash']);
$nums = [
    'Projects' => (int) qv('SELECT COUNT(*) FROM projects WHERE user_id = ?', [$uid]),
    'Puzzles' => (int) qv('SELECT COUNT(*) FROM puzzles WHERE user_id = ?', [$uid]),
    'Exports' => (int) qv('SELECT COUNT(*) FROM exports WHERE user_id = ?', [$uid]),
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
        <div class="cardhead"><h2><?= $hasPassword ? 'Change password' : 'Set a password' ?></h2></div>
        <?php if (!$hasPassword): ?><p class="muted small">You log in with Google. Set a password to log in with your email too.</p><?php endif; ?>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <?php if ($hasPassword): ?><label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label><?php endif; ?>
          <div class="row2">
            <label>New password (<?= PASSWORD_MIN ?>+ characters)<input name="password" type="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password"></label>
            <label>Confirm new password<input name="password_confirm" type="password" required autocomplete="new-password"></label>
          </div>
          <div><button class="btn primary"><?= $hasPassword ? 'Change password' : 'Set password' ?></button></div>
        </form>
      </section>
      <?php if (google_enabled() || $row['google_id']): ?>
      <section class="card">
        <div class="cardhead"><h2>Google</h2><?= $row['google_id'] ? '<span class="chip ok">Linked</span>' : '<span class="chip">Not linked</span>' ?></div>
        <?php if ($row['google_id']): ?>
          <p class="muted small">You can log in with the "Continue with Google" button.</p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unlink_google">
            <button class="btn" <?= $hasPassword ? '' : 'disabled title="Set a password first"' ?>>Unlink Google</button></form>
        <?php else: ?>
          <p class="muted small">Link a Google account to log in with one click. Its email does not have to match.</p>
          <a class="btn google" href="<?= e(url('google.php')) ?>">Link Google account</a>
        <?php endif; ?>
      </section>
      <?php endif; ?>
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
