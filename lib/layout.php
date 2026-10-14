<?php
// Shared page chrome. Signed-in pages get the app shell: a sidebar (grouped navigation and the plan card)
// and a top bar with the user menu. Signed-out pages (login, reset, access links) get a centred card instead.
// Layout and organisation follow GameCraft Studio; colors, fonts and dark mode stay Nonogram's own.

function asset(string $path): string
{
    return e(url($path)) . '?v=' . APP_VERSION;
}

/** Small line icons (24px grid, stroke = currentColor). */
function icon(string $name, int $size = 18): string
{
    static $p = [
        'home' => '<path d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z"/>',
        'folder' => '<path d="M3.5 7.5a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'grid' => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'crown' => '<path d="m3 8 4.5 4L12 5l4.5 7L21 8l-2 11H5z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'copy' => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7.5a2 2 0 0 0 2 2h2.5"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'picture' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
        'check2' => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="8.5"/>',
        'book' => '<path d="M5 4.5h10a3 3 0 0 1 3 3V20H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h10"/>',
        'printer' => '<path d="M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="6" rx="1"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** Left navigation: [path, icon, label, exact-match]. Administration only for admins. */
function nav_groups(array $user): array
{
    $g = [
        ['', [['app/', 'home', 'Dashboard', true], ['app/projects.php', 'folder', 'My Projects', false]]],
        ['Account', [['app/settings.php', 'settings', 'Settings', false]]],
    ];
    if (is_admin($user)) {
        $g[] = ['Administration', [
            ['admin/', 'grid', 'Overview', true], ['admin/users.php', 'users', 'Users', false],
            ['admin/links.php', 'link', 'Access links', false], ['admin/activity.php', 'clock', 'Activity', false],
        ]];
    }
    return $g;
}

function nav_active(string $path, bool $exact): bool
{
    $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $target = url($path);
    $norm = fn($p) => preg_replace('#/index\.php$#', '/', $p);
    if ($exact) return $norm($cur) === $norm($target);
    if (str_ends_with($target, '/users.php') && str_contains($cur, '/admin/user.php')) return true; // a user's page sits under Users
    return str_starts_with($norm($cur), $norm($target));
}

/** The card's headline: the highest product owned, plus how many others. */
function plan_label(array $user, array $f): string
{
    if (is_admin($user)) return 'Admin · all features';
    if (!$f['products']) return 'No license yet';
    $top = null;
    foreach (['agency', 'agency_lite', 'pro', 'pro_lite', 'fe'] as $c) if (in_array($c, $f['products'], true)) { $top = $c; break; }
    $top = $top ?? $f['products'][0];
    $more = count($f['products']) - 1;
    return PRODUCTS[$top]['name'] . ($more > 0 ? ' + ' . $more . ' more' : '');
}

/** opts: compact (icon rail, for the editor), tagline */
function page_head(string $title, ?array $user = null, string $active = '', array $opts = []): void
{
    $f = $user ? features($user) : null;
    $GLOBALS['__shell'] = (bool) $user;
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
<body<?= !empty($opts['compact']) ? ' class="compact"' : '' ?>>
<?php if (!$user) { echo flash_html(); return; }
    $first = explode(' ', trim((string) $user['name']))[0] ?: explode('@', $user['email'])[0];
    $offers = upgrade_offers($user);
?>
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="<?= e(url('app/')) ?>" title="<?= e(config('app_name')) ?>"><span class="mark" aria-hidden="true"></span><span class="brand-name"><?= e(config('app_name')) ?></span></a>
    <nav class="sidenav" aria-label="Main navigation">
      <?php foreach (nav_groups($user) as [$label, $items]): ?>
        <div class="navgroup">
          <?php if ($label): ?><div class="navlabel"><?= e($label) ?></div><?php endif; ?>
          <?php foreach ($items as [$path, $ic, $text, $exact]): $on = nav_active($path, $exact); ?>
            <a class="navitem" href="<?= e(url($path)) ?>" title="<?= e($text) ?>" <?= $on ? 'aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($text) ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="plancard">
      <div class="plancard-head"><?= icon('crown', 16) ?><span><?= e(plan_label($user, $f)) ?></span></div>
      <div class="plancard-desc">Grids up to <?= (int) $f['max_grid'] ?>×<?= (int) $f['max_grid'] ?> · <?= $f['color'] ? 'color' : 'black &amp; white' ?></div>
      <?php if (is_admin($user)): ?>
        <a class="btn sm block" href="<?= e(url('admin/links.php')) ?>">Access links</a>
      <?php elseif ($offers): ?>
        <a class="btn sm block primary" href="<?= e($offers[0]['url']) ?>" target="_blank" rel="noopener">Upgrade to <?= e($offers[0]['name']) ?></a>
      <?php endif; ?>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <div class="tagline"><?= e($opts['tagline'] ?? 'Turn any picture into a print-ready Nonogram book') ?></div>
      <div class="usermenu" data-menu>
        <button type="button" class="userbtn" data-menu-trigger aria-haspopup="true" aria-expanded="false">
          <span class="avatar" aria-hidden="true"><?= e(initials((string) $user['name'], $user['email'])) ?></span>
          <span class="hello">Hello, <?= e($first) ?></span><?= icon('chevron', 14) ?>
        </button>
        <div class="menu" role="menu" hidden>
          <div class="menu-who"><b><?= e($user['name'] ?: $first) ?></b><span><?= e($user['email']) ?></span></div>
          <a role="menuitem" href="<?= e(url('app/settings.php')) ?>"><?= icon('user', 16) ?>Settings</a>
          <?php if (is_admin($user)): ?><a role="menuitem" href="<?= e(url('admin/')) ?>"><?= icon('crown', 16) ?>Administration</a><?php endif; ?>
          <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit" role="menuitem" class="danger"><?= icon('logout', 16) ?>Log out</button></form>
        </div>
      </div>
    </header>
    <div class="content">
<?= flash_html() ?>
<?php
}

function page_foot(array $scripts = []): void
{
    if (!empty($GLOBALS['__shell'])) {
        echo "    </div>\n  </div>\n</div>\n";
        array_unshift($scripts, 'assets/js/shell.js');
    }
    foreach ($scripts as $s) {
        $src = preg_match('#^https?://#', $s) ? e($s) : asset($s);
        echo '<script src="' . $src . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}
