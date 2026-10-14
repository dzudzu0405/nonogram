<?php

const LOGIN_MAX_ATTEMPTS = 8;      // per email or per IP
const LOGIN_WINDOW_MIN = 15;

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user !== false && !$refresh) return $user;
    $id = $_SESSION['uid'] ?? null;
    $user = $id ? q1('SELECT id, email, name, role, status, created_at FROM users WHERE id = ?', [$id]) : null;
    if ($user && $user['status'] !== 'active') $user = null;
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Admins only.');
    }
    return $u;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function login_blocked(string $email): bool
{
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
    $byEmail = (int) qv('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
    $byIp = (int) qv('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
    return $byEmail >= LOGIN_MAX_ATTEMPTS || $byIp >= LOGIN_MAX_ATTEMPTS * 3;
}

/** Returns null on success, or an error message for the login form. */
function attempt_login(string $email, string $password): ?string
{
    $email = normalize_email($email);
    if ($email === '' || $password === '') return 'Enter your email and password.';
    if (login_blocked($email)) return 'Too many attempts. Wait 15 minutes and try again.';

    $u = q1('SELECT id, password_hash, status FROM users WHERE email = ?', [$email]);
    if (!$u || !$u['password_hash'] || !password_verify($password, $u['password_hash'])) {
        insert('login_attempts', ['ip' => client_ip(), 'email' => $email, 'created_at' => now()]);
        return 'That email and password do not match.';
    }
    if ($u['status'] !== 'active') return 'This account is disabled. Contact support.';

    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    login_as((int) $u['id']);
    return null;
}

/** Signs the user in on this session (new session id against fixation; parked access codes survive). */
function login_as(int $id): array
{
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $id]);
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    return current_user(true);
}

const PASSWORD_MIN = 8;

/** Returns an error message, or null when the password is acceptable. */
function password_problem(string $pass, string $confirm): ?string
{
    if (strlen($pass) < PASSWORD_MIN) return 'Use a password with at least ' . PASSWORD_MIN . ' characters.';
    if (strlen($pass) > 200) return 'That password is too long.';
    if ($pass !== $confirm) return 'The two passwords do not match.';
    return null;
}

function is_admin(?array $u): bool
{
    return $u !== null && $u['role'] === 'admin';
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function create_user(string $email, string $name, ?string $password, string $role = 'user'): int
{
    return insert('users', [
        'email' => normalize_email($email),
        'name' => str_limit($name, 120),
        'password_hash' => $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null,
        'role' => $role,
        'status' => 'active',
        'created_at' => now(),
    ]);
}

const RESET_MINUTES = 60;

/** Emails a one-hour, single-use password reset link. Only the token's SHA-256 is stored (tokens table,
    purpose 'reset'); any older link for the account stops working. Returns whether the email went out. */
function send_reset_link(array $u): bool
{
    q("UPDATE tokens SET used_at = ? WHERE user_id = ? AND purpose = 'reset' AND used_at IS NULL", [now(), $u['id']]);
    $token = bin2hex(random_bytes(32));
    insert('tokens', [
        'user_id' => $u['id'], 'token_hash' => hash('sha256', $token), 'purpose' => 'reset',
        'expires_at' => gmdate('Y-m-d H:i:s', time() + RESET_MINUTES * 60), 'used_at' => null, 'created_at' => now(),
    ]);
    $first = explode(' ', trim((string) $u['name']))[0] ?: 'there';
    $sent = mail_send($u['email'], (string) $u['name'], 'Reset your ' . config('app_name') . ' password', mail_layout('Reset your password', [
        "Hi $first,",
        'Someone (hopefully you) asked to reset the password for this account. Choose a new one with the button below. The link works once and expires in ' . RESET_MINUTES . ' minutes.',
    ], ['Choose a new password', url_full('reset.php?t=' . $token)], 'If you did not ask for this, ignore this email: your password stays the same.'));
    if (!$sent) error_log('[nonogram] reset email to ' . $u['email'] . ' failed: ' . mail_last_error());
    return $sent;
}
