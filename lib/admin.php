<?php
// Shared bits for the admin pages (admin/*.php). Every admin page calls admin_begin(), which refuses non-admins.

function admin_begin(string $title, string $active): array
{
    $user = require_admin();
    page_head($title . ' · Admin', $user, 'admin');
    $tabs = ['' => ['grid', 'Overview'], 'users.php' => ['users', 'Users'], 'links.php' => ['link', 'Access links'], 'activity.php' => ['clock', 'Activity']];
    echo '<main class="page"><section class="pagehead"><div><h1>' . e($title) . '</h1></div><nav class="pills" aria-label="Administration">';
    foreach ($tabs as $file => [$ic, $label]) {
        echo '<a href="' . e(url('admin/' . $file)) . '"' . ($active === $label ? ' aria-current="page"' : '') . '>' . icon($ic, 15) . e($label) . '</a>';
    }
    echo '</nav></section>';
    return $user;
}

function admin_end(): void
{
    echo '</main>';
    page_foot();
}

const EVENT_SOURCES = ['link' => 'Access link', 'admin' => 'Admin', 'import' => 'Import', 'wplus' => 'WarriorPlus'];

/** "email" for a user id, or "deleted account #id". Cached per request. */
function user_email(?int $id): string
{
    static $cache = [];
    if (!$id) return '';
    if (!array_key_exists($id, $cache)) $cache[$id] = qv('SELECT email FROM users WHERE id = ?', [$id]) ?: null;
    return $cache[$id] ?? 'deleted account #' . $id;
}

/** One line of the product log, as HTML. */
function event_line(array $ev, bool $showUser = true): string
{
    $verb = $ev['action'] === 'grant' ? '<span class="chip ok">+ ' . e(PRODUCTS[$ev['product']]['name'] ?? $ev['product']) . '</span>'
        : '<span class="chip bad">− ' . e(PRODUCTS[$ev['product']]['name'] ?? $ev['product']) . '</span>';
    $who = $showUser ? '<a href="' . e(url('admin/user.php?id=' . (int) $ev['user_id'])) . '">' . e(user_email((int) $ev['user_id'])) . '</a> ' : '';
    $by = EVENT_SOURCES[$ev['source']] ?? $ev['source'];
    if ($ev['source'] === 'admin' && $ev['actor_id']) $by .= ' ' . e(user_email((int) $ev['actor_id']));
    if ($ev['source'] === 'link' && $ev['link_id']) $by = '<a href="' . e(url('admin/activity.php?link=' . (int) $ev['link_id'])) . '">' . e($by) . '</a>';
    $note = $ev['note'] ? ' <span class="muted">· ' . e($ev['note']) . '</span>' : '';
    return $who . $verb . ' <span class="muted small">by ' . $by . $note . ' · ' . e(time_ago($ev['created_at'])) . '</span>';
}

/** Page number from ?page=, and the HTML for prev/next links. */
function paginate(int $total, int $per): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
    $link = function (int $p) { $q = $_GET; $q['page'] = $p; return '?' . http_build_query($q); };
    $html = $pages > 1 ? '<nav class="pager">'
        . ($page > 1 ? '<a class="btn sm" href="' . e($link($page - 1)) . '">← Newer</a>' : '<span></span>')
        . '<span class="muted small">Page ' . $page . ' of ' . $pages . '</span>'
        . ($page < $pages ? '<a class="btn sm" href="' . e($link($page + 1)) . '">Older →</a>' : '<span></span>') . '</nav>' : '';
    return [($page - 1) * $per, $html];
}

function admin_count(): int
{
    return (int) qv("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
}
