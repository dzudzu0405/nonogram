<?php
// Local testing only: php -S 127.0.0.1:8080 dev-router.php
// Mimics the .htaccess rules that the PHP built-in server ignores.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(lib|install|storage|tests)(/|$)|^/config(\.sample)?\.php$|\.(sql|sqlite|lock|md)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
