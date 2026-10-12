<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

if (current_user()) redirect('app/');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) $error = 'Security check failed. Reload the page and try again.';
    else $error = attempt_login($email, (string) ($_POST['password'] ?? '')) ?? '';
    if ($error === '') redirect('app/');
}
page_head('Log in');
?>
<main class="auth">
  <form class="authcard form" method="post" novalidate>
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <p class="muted">Turn any picture into a print-ready Nonogram puzzle book.</p>
    <?php if ($error): ?><p class="err-msg" role="alert"><?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Email<input id="email" name="email" type="email" required autocomplete="email" value="<?= e($email) ?>" autofocus></label>
    <label>Password<input id="password" name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn primary">Log in</button>
    <p class="muted small">Bought on WarriorPlus? Your login details are in your welcome email.</p>
  </form>
</main>
<?php page_foot(); ?>
