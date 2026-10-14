<?php

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path where the app lives ('' at the domain root, '/studio' in a sub-folder). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $cfg = config('base_path', '');
    if ($cfg !== '') return $base = rtrim($cfg, '/');
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    $rel = ($doc !== '' && stripos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
    return $base = rtrim(str_replace('\\', '/', $rel), '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

const SESSION_LIFETIME = 60 * 60 * 24 * 30;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // The cookie lasts 30 days, so the session file must too. PHP's default is 24 minutes and shared hosts clean
    // the default folder for every site on the server, so keep our own folder and our own garbage collection.
    $dir = APP_ROOT . '/storage/sessions';
    if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1000');
    }
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    session_name('nsid');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/** Absolute address for emails and access links. Uses config app_url when set, so a forged Host header
    cannot turn a password-reset email into a link to somebody else's site. */
function url_full(string $path = ''): string
{
    $base = rtrim((string) config('app_url', ''), '/');
    if ($base === '') {
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = (is_https() ? 'https://' : 'http://') . $host . base_path();
    }
    return $base . '/' . ltrim($path, '/');
}

/* ---------- One-time messages shown on the next page ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type === 'error' ? 'err' : ($type === 'success' ? 'ok' : 'info'), $message];
}

function flash_html(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) $out .= '<p class="flash ' . e($type) . '" role="status">' . e($msg) . '</p>';
    unset($_SESSION['flash']);
    return $out ? '<div class="flashes">' . $out . '</div>' : '';
}

/** For POST forms: check the CSRF token or stop with a message. */
function require_post_csrf(string $back): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($back);
    if (!csrf_valid($_POST['csrf'] ?? null)) { flash('error', 'Security check failed. Please try again.'); redirect($back); }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Simple per-session rate limit: true when $key has been used $max times in the last hour. */
function rate_limited(string $key, int $max): bool
{
    $log = array_filter((array) ($_SESSION['rate'][$key] ?? []), fn($t) => $t > time() - 3600);
    $_SESSION['rate'][$key] = array_values($log);
    return count($log) >= $max;
}

function rate_hit(string $key): void
{
    $_SESSION['rate'][$key][] = time();
}

function initials(string $name, string $email = ''): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $p) $s .= mb_substr($p, 0, 1);
    if ($s === '') $s = mb_substr($email, 0, 1);
    return mb_strtoupper($s ?: '?');
}

function time_ago(?string $utc): string
{
    if (!$utc) return 'never';
    $s = time() - strtotime($utc . ' UTC');
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 30) return floor($s / 86400) . ' days ago';
    return gmdate('j M Y', strtotime($utc . ' UTC'));
}

/* ---------- JSON API helpers ---------- */

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void
{
    json_out(['ok' => false, 'error' => $message] + $extra, $code);
}

function json_in(): array
{
    static $body = null;
    if ($body !== null) return $body;
    $raw = file_get_contents('php://input') ?: '';
    $body = $raw === '' ? [] : (json_decode($raw, true) ?: []);
    return $body;
}

/** Start an API request: require login, and a CSRF header on anything that changes data. */
function api_begin(): array
{
    set_exception_handler(function (Throwable $e) {
        error_log('[nonogram] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        json_error(config('debug') ? $e->getMessage() : 'Something went wrong on the server. Please try again.', 500);
    });
    $user = current_user();
    if (!$user) json_error('Your session has ended. Please log in again.', 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_error('Security check failed. Reload the page and try again.', 419);
    }
    return $user;
}

function str_limit(string $s, int $max): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_substr($s, 0, $max);
}
