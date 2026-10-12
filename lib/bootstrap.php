<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '0.1.0');

$cfgFile = APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Missing config.php. Copy config.sample.php to config.php and fill in your database details, then open install.php.');
}
$GLOBALS['CONFIG'] = require $cfgFile;

function config(string $key, $default = null)
{
    $v = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
date_default_timezone_set('UTC');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/entitlements.php';

start_session();
