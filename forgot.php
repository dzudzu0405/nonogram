<?php
// Forgot password: emails a one-hour, single-use reset link.
// Only the SHA-256 of the token is stored (tokens table, purpose 'reset'), so reading the database does not give a
// working link. The reply is the same whether or not the address has an account, so the page cannot be used to
// find out who is a customer. Same approach as GameCraft Studio.
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

if (current_user()) redirect('app/');
$email = normalize_email((string) ($_POST['email'] ?? ''));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) $error = 'Security check failed. Reload the page and try again.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter the email address you log in with.';
    elseif (!mail_usable()) $error = 'Email has not been set up on this site yet, so reset links cannot be sent. Please contact support.';
    elseif (rate_limited('reset', 5)) $error = 'That is a lot of reset requests. Please wait an hour and try again.';
    else {
        rate_hit('reset');
        $u = q1("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($u) send_reset_link($u);
        flash('success', 'If that address has an account, a reset link is on its way. It expires in ' . RESET_MINUTES . ' minutes.');
        redirect('login.php');
    }
}
page_head('Reset your password');
?>
<main class="auth">
  <form class="authcard form" method="post" novalidate>
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <h2>Reset your password</h2>
    <p class="muted">Enter the email you log in with and we will send you a link to choose a new password.</p>
    <?php if (!mail_usable()): ?><p class="err-msg">Email is not set up on this site yet, so reset links cannot be sent. Please contact support.</p><?php endif; ?>
    <?php if ($error): ?><p class="err-msg" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email<input name="email" type="email" required autocomplete="email" value="<?= e($email) ?>" autofocus></label>
    <button class="btn primary">Send reset link</button>
    <p class="muted small center"><a href="<?= e(url('login.php')) ?>">Back to log in</a></p>
  </form>
</main>
<?php page_foot(); ?>
