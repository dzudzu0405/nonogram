<?php
// Sign in with Google, straight against the OAuth 2.0 endpoints with cURL (no SDK, no Composer).
// Flow: google.php sends the visitor to Google with a one-time state -> Google returns to google.php with a code
// -> the code is swapped for an access token -> the profile (id, email, name, verified) is read.
// Setup: console.cloud.google.com/apis/credentials > OAuth client ID > Web application, with the authorised
// redirect URI exactly google_redirect_uri() (https only), then the 'google' block in config.php.
// Ported from GameCraft Studio's GoogleAuth.

const GOOGLE_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
const GOOGLE_USERINFO = 'https://openidconnect.googleapis.com/v1/userinfo';

function google_enabled(): bool
{
    return trim((string) config('google.client_id', '')) !== '' && trim((string) config('google.client_secret', '')) !== '';
}

/** Endpoints; tests point them at a fake server, which is only honoured on a debug (local) site. */
function google_endpoint(string $which): string
{
    $test = config('debug') ? (string) config('google.test_endpoints.' . $which, '') : '';
    if ($test !== '') return $test;
    return ['auth' => GOOGLE_AUTH, 'token' => GOOGLE_TOKEN, 'userinfo' => GOOGLE_USERINFO][$which];
}

function google_redirect_uri(): string
{
    return url_full('google.php');
}

function google_auth_url(string $state): string
{
    return google_endpoint('auth') . '?' . http_build_query([
        'client_id' => trim((string) config('google.client_id')),
        'redirect_uri' => google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
        'access_type' => 'online',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Swaps the one-time code for an access token. Throws RuntimeException with a message for the site owner. */
function google_exchange_code(string $code): string
{
    $r = google_http(google_endpoint('token'), [
        'code' => $code,
        'client_id' => trim((string) config('google.client_id')),
        'client_secret' => trim((string) config('google.client_secret')),
        'redirect_uri' => google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
    if (isset($r['error'])) {
        $hint = match ((string) $r['error']) {
            'redirect_uri_mismatch' => 'The redirect address does not match the one registered with Google. Add exactly this URI to your OAuth client: ' . google_redirect_uri(),
            'invalid_client' => 'Google rejected the client id or secret. Check the google block in config.php.',
            'invalid_grant' => 'That sign-in code was already used or has expired. Please try again.',
            default => 'Google refused the sign-in (' . $r['error'] . ').',
        };
        throw new RuntimeException($hint . (!empty($r['error_description']) ? ' Google said: ' . $r['error_description'] : ''));
    }
    if (empty($r['access_token'])) throw new RuntimeException('Google did not return an access token.');
    return (string) $r['access_token'];
}

/** @return array{id:string,email:string,name:string,verified:bool} */
function google_profile(string $token): array
{
    $d = google_http(google_endpoint('userinfo'), null, $token);
    $id = (string) ($d['sub'] ?? '');
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    if ($id === '' || $email === '') throw new RuntimeException('Google did not return an email address for that account.');
    return [
        'id' => $id, 'email' => $email,
        'name' => trim((string) ($d['name'] ?? '')) ?: (string) strstr($email, '@', true),
        'verified' => filter_var($d['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
}

function google_http(string $url, ?array $post, ?string $bearer = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('The cURL extension is off, so Google sign-in cannot work. Turn it on in cPanel > Select PHP Version > Extensions.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => (string) config('app_name', 'Nonogram Studio'),
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($bearer !== null) curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $bearer]);
    $body = curl_exec($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') throw new RuntimeException('Could not reach Google: ' . $err);
    $data = json_decode((string) $body, true);
    if (!is_array($data)) throw new RuntimeException('Google sent back something unreadable (HTTP ' . $status . ').');
    return $data;
}

/** The "Continue with Google" button (nothing when Google sign-in is not set up). */
function google_button(string $label = 'Continue with Google'): string
{
    if (!google_enabled()) return '';
    return '<a class="btn google" href="' . e(url('google.php')) . '">'
        . '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'
        . e($label) . '</a><div class="ordivider"><span>or</span></div>';
}
