<?php
declare(strict_types=1);

/*
 * Configuration via environment variables is preferred in production.
 * For local XAMPP, these development defaults work with a fresh installation
 * whose MySQL root account has no password. Never use these defaults online.
 */
return [
    'app_env' => getenv('APP_ENV') ?: 'development',
    'app_key' => getenv('APP_KEY') ?: 'local-development-only-change-this-key-before-production-94f6a3d8c2b17e05',
    'session_cookie_secure' => filter_var(
        getenv('SESSION_COOKIE_SECURE') !== false
            ? getenv('SESSION_COOKIE_SECURE')
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'technews',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '',
        'charset' => 'utf8mb4',
    ],
];
