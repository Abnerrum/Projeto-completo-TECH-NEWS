<?php
declare(strict_types=1);

$technews_config = require __DIR__ . '/config.php';
$GLOBALS['technews_config'] = $technews_config;

date_default_timezone_set('UTC');

if ($technews_config['app_env'] === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (strlen((string) $technews_config['app_key']) < 48 || str_starts_with((string) $technews_config['app_key'], 'local-development')
        || str_starts_with((string) $technews_config['app_key'], 'CHANGE_ME_')) {
        error_log('TECH NEWS: configure a strong APP_KEY before using production.');
        http_response_code(500);
        exit('Configuração de segurança incompleta.');
    }
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name('TECHNEWSSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool) $technews_config['session_cookie_secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
