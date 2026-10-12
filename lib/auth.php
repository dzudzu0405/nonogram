<?php

const LOGIN_MAX_ATTEMPTS = 8;      // per email or per IP
const LOGIN_WINDOW_MIN = 15;

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
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
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    return null;
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
