<?php
declare(strict_types=1);

/*
 * Local/production configuration:
 * - Local development may use the safe defaults below.
 * - On shared hosting, copy config/local.example.php to config/local.php and
 *   enter your database credentials and a unique APP_KEY in that ignored file.
 * - Environment variables take precedence over local.php when provided.
 */

$local = [];
$localFile = dirname(__DIR__) . '/config/local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}
$localDb = is_array($local['db'] ?? null) ? $local['db'] : [];

$environment = getenv('APP_ENV');
$applicationKey = getenv('APP_KEY');
$applicationUrl = getenv('APP_URL');
$secureCookieEnvironment = getenv('SESSION_COOKIE_SECURE');
$dbHostEnvironment = getenv('DB_HOST');
$dbPortEnvironment = getenv('DB_PORT');
$dbNameEnvironment = getenv('DB_NAME');
$dbUserEnvironment = getenv('DB_USER');
$dbPasswordEnvironment = getenv('DB_PASSWORD');

return [
    'app_url' => $applicationUrl !== false && $applicationUrl !== ''
        ? $applicationUrl
        : (string) ($local['app_url'] ?? ''),
    'app_env' => $environment !== false && $environment !== ''
        ? $environment
        : (string) ($local['app_env'] ?? 'development'),
    'app_key' => $applicationKey !== false && $applicationKey !== ''
        ? $applicationKey
        : (string) ($local['app_key'] ?? 'local-development-only-change-this-key-before-production-94f6a3d8c2b17e05'),
    'session_cookie_secure' => filter_var(
        $secureCookieEnvironment !== false
            ? $secureCookieEnvironment
            : ($local['session_cookie_secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')),
        FILTER_VALIDATE_BOOLEAN
    ),
    'db' => [
        'host' => $dbHostEnvironment !== false && $dbHostEnvironment !== ''
            ? $dbHostEnvironment
            : (string) ($localDb['host'] ?? '127.0.0.1'),
        'port' => $dbPortEnvironment !== false && $dbPortEnvironment !== ''
            ? $dbPortEnvironment
            : (string) ($localDb['port'] ?? '3306'),
        'name' => $dbNameEnvironment !== false && $dbNameEnvironment !== ''
            ? $dbNameEnvironment
            : (string) ($localDb['name'] ?? 'technews'),
        'user' => $dbUserEnvironment !== false && $dbUserEnvironment !== ''
            ? $dbUserEnvironment
            : (string) ($localDb['user'] ?? 'root'),
        'password' => $dbPasswordEnvironment !== false
            ? (string) $dbPasswordEnvironment
            : (string) ($localDb['password'] ?? ''),
        'charset' => (string) ($localDb['charset'] ?? 'utf8mb4'),
    ],
];
