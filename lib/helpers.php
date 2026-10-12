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

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('nsid');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
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
