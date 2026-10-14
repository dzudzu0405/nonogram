<?php
// Sign in with Google: google.php starts the round trip, Google sends the visitor back here with ?code&state.
//
// Matching an account, in order (same as GameCraft Studio):
//   1. a user already carrying this Google id       -> sign in
//   2. a user with the same email address           -> link Google to it (only when Google has verified the email,
//                                                      otherwise claiming an address would open someone's account)
//   3. nobody yet                                   -> create an account, but only for a buyer arriving from an
//                                                      access link (or with open_registration), like register.php
// Signed in already (Settings > Link Google): the Google account is linked to the current account instead.
require __DIR__ . '/lib/bootstrap.php';

if (!google_enabled()) { http_response_code(404); exit('Google sign-in is not set up on this site.'); }
$me = current_user();
$fail = function (string $msg) use ($me) { flash('error', $msg); redirect($me ? 'app/settings.php' : 'login.php'); };

// Step 1: off to Google with a one-time state value (the CSRF guard for the round trip).
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_state'] = ['v' => $state, 'at' => time(), 'link' => (bool) $me];
    header('Location: ' . google_auth_url($state));
    exit;
}

// Step 2: back from Google.
$saved = $_SESSION['google_state'] ?? null;
unset($_SESSION['google_state']);
if (isset($_GET['error'])) { flash('info', 'Google sign-in was cancelled.'); redirect($me ? 'app/settings.php' : 'login.php'); }
if (!is_array($saved) || time() - (int) $saved['at'] > 600 || !hash_equals((string) $saved['v'], (string) ($_GET['state'] ?? ''))) {
    $fail('That sign-in attempt could not be verified. Please try again.');
}
try {
    $profile = google_profile(google_exchange_code((string) $_GET['code']));
} catch (Throwable $e) {
    error_log('[nonogram google] ' . $e->getMessage());
    $fail(config('debug') ? $e->getMessage() : 'Google sign-in did not work. Please try again, or log in with your password.');
}
if (!$profile['verified']) $fail('That Google account has not verified its email address, so it cannot be used here.');

// Linking from Settings.
if ($me && !empty($saved['link'])) {
    $other = qv('SELECT id FROM users WHERE google_id = ? AND id <> ?', [$profile['id'], (int) $me['id']]);
    if ($other) $fail('That Google account is already linked to another ' . config('app_name') . ' account.');
    q('UPDATE users SET google_id = ? WHERE id = ?', [$profile['id'], (int) $me['id']]);
    flash('success', 'Google account ' . $profile['email'] . ' linked. You can log in with it from now on.');
    redirect('app/settings.php');
}

$user = q1('SELECT * FROM users WHERE google_id = ?', [$profile['id']]);
if (!$user) {
    $user = q1('SELECT * FROM users WHERE email = ?', [$profile['email']]);
    if ($user) {
        if ($user['status'] !== 'active') $fail('This account is disabled. Contact support.');
        q('UPDATE users SET google_id = ? WHERE id = ?', [$profile['id'], (int) $user['id']]);
        flash('success', 'Your Google account is now linked. You can log in either way from now on.');
    }
}
if ($user && $user['status'] !== 'active') $fail('This account is disabled. Contact support.');

if (!$user) {
    $parked = access_parked() ? access_find(access_parked()) : null;
    if ((!$parked || access_problem($parked)) && !config('open_registration', false)) {
        $fail('There is no account for ' . $profile['email'] . ' yet. Open the access link from your WarriorPlus receipt to create one.');
    }
    // No password: they log in with Google, or set one later with "Forgot password" or in Settings.
    $id = create_user($profile['email'], $profile['name'], null);
    q('UPDATE users SET google_id = ? WHERE id = ?', [$profile['id'], $id]);
    $user = ['id' => $id];
    if (mail_usable()) {
        mail_send($profile['email'], $profile['name'], 'Welcome to ' . config('app_name'), mail_layout('Welcome, ' . explode(' ', $profile['name'])[0] . '!', [
            'Your ' . config('app_name') . ' account is ready. Log in any time with the "Continue with Google" button.',
        ], ['Open ' . config('app_name'), url_full('app/')]));
    }
}

access_apply_parked(login_as((int) $user['id']));
redirect('app/');
