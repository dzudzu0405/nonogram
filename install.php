<?php
// One-time installer: checks the server, creates the tables and the first admin account.
// After it finishes it writes storage/installed.lock and refuses to run again.
require __DIR__ . '/lib/bootstrap.php';

$lock = APP_ROOT . '/storage/installed.lock';
$checks = [];
$add = function (string $label, bool $ok, string $hint = '') use (&$checks) { $checks[] = compact('label', 'ok', 'hint'); };

$add('PHP 8.1 or newer (you have ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), 'Select PHP 8.1+ in cPanel > Select PHP Version.');
$driver = db_driver();
$add('PDO ' . $driver . ' extension', extension_loaded('pdo_' . $driver), 'Enable pdo_' . $driver . ' in cPanel > Select PHP Version > Extensions.');
$add('mbstring extension', extension_loaded('mbstring'), 'Enable mbstring in cPanel > Select PHP Version > Extensions.');
$add('storage/ folder is writable', is_writable(APP_ROOT . '/storage'), 'Set the storage folder permission to 755 in File Manager.');
$dbOk = false; $dbErr = '';
try { db()->query('SELECT 1'); $dbOk = true; } catch (Throwable $e) { $dbErr = $e->getMessage(); }
$add('Database connection', $dbOk, $dbOk ? '' : 'Check the db settings in config.php. ' . $dbErr);
$add('app_key changed in config.php', strlen((string) config('app_key')) >= 32 && strpos((string) config('app_key'), 'replace-with') === false, 'Put a long random string (at least 32 characters) in app_key.');
$allOk = !in_array(false, array_column($checks, 'ok'), true);

$done = is_file($lock);
$error = '';
if (!$done && $allOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Security check failed. Reload the page and try again.';
    } else {
        $email = normalize_email($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
        elseif (strlen($pass) < 10) $error = 'Use a password with at least 10 characters.';
        else {
            $sql = file_get_contents(APP_ROOT . '/install/schema.' . $driver . '.sql');
            foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) db()->exec($stmt);
            $existing = qv('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) q("UPDATE users SET role = 'admin', password_hash = ? WHERE id = ?", [password_hash($pass, PASSWORD_DEFAULT), $existing]);
            else create_user($email, $name, $pass, 'admin');
            file_put_contents($lock, 'Installed ' . now() . "\n");
            $done = true;
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install · <?= e(config('app_name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="auth">
<main class="authcard wide">
  <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?> setup</h1>
  <?php if ($done): ?>
    <p class="ok-msg">Installation is complete.</p>
    <p>Delete <code>install.php</code> from File Manager now, then <a href="<?= e(url('login.php')) ?>">log in</a> with your admin account.</p>
  <?php else: ?>
    <ul class="checks">
      <?php foreach ($checks as $c): ?>
        <li class="<?= $c['ok'] ? 'pass' : 'fail' ?>"><b><?= $c['ok'] ? '✓' : '✕' ?></b> <?= e($c['label']) ?><?php if (!$c['ok'] && $c['hint']): ?><span><?= e($c['hint']) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($allOk): ?>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <h2>Create your admin account</h2>
        <?php if ($error): ?><p class="err-msg"><?= e($error) ?></p><?php endif; ?>
        <label>Name<input name="name" value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name"></label>
        <label>Email<input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"></label>
        <label>Password (10+ characters)<input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
        <button class="btn primary">Install</button>
      </form>
    <?php else: ?>
      <p class="err-msg">Fix the items marked ✕, then reload this page.</p>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
