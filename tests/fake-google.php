<?php
// A stand-in for Google's OAuth endpoints, for tests only: php -S 127.0.0.1:8092 tests/fake-google.php
// tests/account.test.js starts it and writes the "signed-in Google user" to storage/fake-google.json before each step.
// Like Google it checks the client id and secret, that the redirect URI matches, and that a code works once.
// The app only talks to it when config.php has debug on and google.test_endpoints set (see README).
const CLIENT_ID = 'test-client';
const CLIENT_SECRET = 'test-secret';
$dir = dirname(__DIR__) . '/storage';
$profileFile = $dir . '/fake-google.json';
$codesFile = $dir . '/fake-google-codes.json';
$codes = is_file($codesFile) ? (json_decode((string) file_get_contents($codesFile), true) ?: []) : [];
$json = function ($data, int $code = 200) { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; };
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/auth') {
    $profile = json_decode((string) @file_get_contents($profileFile), true) ?: [];
    $back = (string) ($_GET['redirect_uri'] ?? '');
    $sep = str_contains($back, '?') ? '&' : '?';
    if (($_GET['client_id'] ?? '') !== CLIENT_ID) { http_response_code(400); exit('invalid_client'); }
    if (!empty($profile['cancel'])) { header('Location: ' . $back . $sep . http_build_query(['error' => 'access_denied', 'state' => $_GET['state'] ?? ''])); exit; }
    $code = bin2hex(random_bytes(10));
    $codes[$code] = ['profile' => $profile, 'redirect_uri' => $back];
    file_put_contents($codesFile, json_encode($codes));
    header('Location: ' . $back . $sep . http_build_query(['code' => $code, 'state' => $_GET['state'] ?? '']));
    exit;
}
if ($path === '/token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['client_id'] ?? '') !== CLIENT_ID || ($_POST['client_secret'] ?? '') !== CLIENT_SECRET) $json(['error' => 'invalid_client'], 401);
    $c = $codes[$_POST['code'] ?? ''] ?? null;
    if (!$c) $json(['error' => 'invalid_grant'], 400);
    if ($c['redirect_uri'] !== ($_POST['redirect_uri'] ?? '')) $json(['error' => 'redirect_uri_mismatch'], 400);
    unset($codes[$_POST['code']]);
    $token = bin2hex(random_bytes(10));
    $codes['token:' . $token] = $c['profile'];
    file_put_contents($codesFile, json_encode($codes));
    $json(['access_token' => $token, 'token_type' => 'Bearer']);
}
if ($path === '/userinfo') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $p = $codes['token:' . preg_replace('/^Bearer\s+/', '', $auth)] ?? null;
    if (!$p) $json(['error' => 'invalid_token'], 401);
    $json(['sub' => $p['sub'], 'email' => $p['email'], 'email_verified' => $p['verified'] ?? true, 'name' => $p['name'] ?? '']);
}
http_response_code(404);
echo 'not found';
