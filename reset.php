<?php
// Choose a new password from an emailed link (reset.php?t=<64 hex>). See forgot.php.
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

$token = (string) ($_GET['t'] ?? '');
$row = preg_match('/^[a-f0-9]{64}$/', $token)
    ? q1("SELECT * FROM tokens WHERE token_hash = ? AND purpose = 'reset' AND used_at IS NULL AND expires_at > ?", [hash('sha256', $token), now()])
    : null;
if (!$row) {
    flash('error', 'That reset link has expired or has already been used. Please ask for a new one.');
    redirect('forgot.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) $error = 'Security check failed. Reload the page and try again.';
    elseif ($p = password_problem($pass, (string) ($_POST['password_confirm'] ?? ''))) $error = $p;
    else {
        $uid = (int) $row['user_id'];
        db()->beginTransaction();
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $uid]);
        q("UPDATE tokens SET used_at = ? WHERE user_id = ? AND purpose = 'reset' AND used_at IS NULL", [now(), $uid]);
        db()->commit();
        $u = q1('SELECT status FROM users WHERE id = ?', [$uid]);
        if ($u && $u['status'] === 'active') {
            access_apply_parked(login_as($uid));
            flash('success', 'Your password has been changed. You are logged in.');
            redirect('app/');
        }
        flash('success', 'Your password has been changed.');
        redirect('login.php');
    }
}
page_head('Choose a new password');
?>
<main class="auth">
  <form class="authcard form" method="post" novalidate>
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <h2>Choose a new password</h2>
    <?php if ($error): ?><p class="err-msg" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>New password (<?= PASSWORD_MIN ?>+ characters)<input name="password" type="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" autofocus></label>
    <label>Confirm new password<input name="password_confirm" type="password" required autocomplete="new-password"></label>
    <button class="btn primary">Save new password</button>
  </form>
</main>
<?php page_foot(); ?>
