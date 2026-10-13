<?php
// Shared page chrome for signed-in pages.

function asset(string $path): string
{
    return e(url($path)) . '?v=' . APP_VERSION;
}

function page_head(string $title, ?array $user = null, string $active = ''): void
{
    $f = $user ? features($user) : null;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<?php if ($user): ?>
<script>window.NS = <?= json_encode([
    'base' => base_path(),
    'version' => APP_VERSION,
    'csrf' => csrf_token(),
    'user' => ['id' => (int) $user['id'], 'email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']],
    'features' => $f,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
  <a class="brand" href="<?= e(url('app/')) ?>"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></a>
  <nav class="nav">
    <a href="<?= e(url('app/')) ?>" <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>>Projects</a>
  </nav>
  <div class="who">
    <span class="plans" title="Your products">
      <?php if ($user['role'] === 'admin'): ?><span class="plan admin">Admin · all features</span>
      <?php else: foreach ($f['products'] as $p): ?><span class="plan"><?= e(PRODUCTS[$p]['name']) ?></span><?php endforeach; endif; ?>
    </span>
    <span class="email"><?= e($user['email']) ?></span>
    <a class="btn sm" href="<?= e(url('logout.php')) ?>">Log out</a>
  </div>
</header>
<?php endif;
}

function page_foot(array $scripts = []): void
{
    foreach ($scripts as $s) {
        $src = preg_match('#^https?://#', $s) ? e($s) : asset($s);
        echo '<script src="' . $src . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}
