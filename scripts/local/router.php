<?php
// Development-only router. CRMEB business files remain unchanged.
$public = dirname(__DIR__, 2) . '/crmeb/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($public . $path)) {
    return false;
}
if ($path === '/admin' || strpos($path, '/admin/') === 0) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($public . '/admin/index.html');
    return true;
}
chdir($public);
require $public . '/index.php';
