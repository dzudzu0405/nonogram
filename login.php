<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

if ($u = current_user()) { access_apply_parked($u); redirect('app/'); }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) $error = 'Security check failed. Reload the page and try again.';
    else $error = attempt_login($email, (string) ($_POST['password'] ?? '')) ?? '';
    if ($error === '') { access_apply_parked(current_user(true)); redirect('app/'); }
}
$parked = access_parked() ? access_find(access_parked()) : null;
page_head('Log in');
?>
<main class="auth">
  <form class="authcard form" method="post" novalidate>
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <?php if ($parked): ?>
      <p class="note-box">Log in to unlock <b><?= e(product_names(access_products($parked))) ?></b> on your account.</p>
    <?php else: ?>
      <p class="muted">Turn any picture into a print-ready Nonogram puzzle book.</p>
    <?php endif; ?>
    <?php if ($error): ?><p class="err-msg" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email<input id="email" name="email" type="email" required autocomplete="email" value="<?= e($email) ?>" autofocus></label>
    <label><span class="labelrow">Password <a href="<?= e(url('forgot.php')) ?>">Forgot password?</a></span><input id="password" name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn primary">Log in</button>
    <?php if ($parked || config('open_registration', false)): ?>
      <p class="muted small center">New here? <a href="<?= e(url('register.php')) ?>">Create your account</a></p>
    <?php else: ?>
      <p class="muted small">Bought on WarriorPlus? Open the access link from your purchase receipt to create your account.</p>
    <?php endif; ?>
  </form>
</main>
<?php page_foot(); ?>
