<?php
// Create an account. Open only to buyers arriving from an access link (the code waits in the session),
// unless config open_registration is true. The parked products are added as soon as the account exists.
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

if (current_user()) redirect('app/');
$parked = access_parked() ? access_find(access_parked()) : null;
if (!$parked && !config('open_registration', false)) {
    flash('info', 'Accounts are created from the access link in your WarriorPlus receipt. Open that link to get started.');
    redirect('login.php');
}

$error = '';
$name = trim((string) ($_POST['name'] ?? ''));
$email = normalize_email((string) ($_POST['email'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) $error = 'Security check failed. Reload the page and try again.';
    elseif (rate_limited('register', 5)) $error = 'Too many sign-ups from this browser. Please wait an hour.';
    elseif ($name === '') $error = 'Enter your name.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif ($p = password_problem($pass, (string) ($_POST['password_confirm'] ?? ''))) $error = $p;
    elseif (qv('SELECT id FROM users WHERE email = ?', [$email])) $error = 'That email already has an account. Log in instead, or reset your password if you forgot it.';
    else {
        rate_hit('register');
        $id = create_user($email, $name, $pass);
        $user = login_as($id);
        access_apply_parked($user);
        if (mail_usable()) {
            mail_send($email, $name, 'Welcome to ' . config('app_name'), mail_layout('Welcome, ' . explode(' ', $name)[0] . '!', [
                'Your ' . config('app_name') . ' account is ready. Log in any time with this email address.',
                'Start with a picture you like: the studio turns it into a puzzle, checks that it has exactly one solution and lays out the book for print.',
            ], ['Open ' . config('app_name'), url_full('app/')]));
        }
        redirect('app/');
    }
}
page_head('Create your account');
?>
<main class="auth">
  <form class="authcard form" method="post" novalidate>
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <?php if ($parked): ?><p class="note-box">Your account will get <b><?= e(product_names(access_products($parked))) ?></b>.</p><?php endif; ?>
    <?php if ($error): ?><p class="err-msg" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= google_button('Sign up with Google') ?>
    <?= csrf_field() ?>
    <label>Your name<input name="name" required autocomplete="name" value="<?= e($name) ?>" maxlength="120" autofocus></label>
    <label>Email<input name="email" type="email" required autocomplete="email" value="<?= e($email) ?>"></label>
    <label>Password (<?= PASSWORD_MIN ?>+ characters)<input name="password" type="password" required minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password"></label>
    <label>Confirm password<input name="password_confirm" type="password" required autocomplete="new-password"></label>
    <button class="btn primary">Create account</button>
    <p class="muted small center">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
  </form>
</main>
<?php page_foot(); ?>
