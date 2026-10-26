<?php
declare(strict_types=1);

/*
 * HOSPEDAGEM:
 * 1. Copie este arquivo para config/local.php.
 * 2. Preencha os dados exatamente como aparecem no painel do provedor.
 * 3. Gere uma APP_KEY aleatória com no mínimo 48 caracteres.
 * 4. Não envie config/local.php ao GitHub; ele já está no .gitignore.
 */
return [
    'app_url' => 'https://SEU-DOMINIO.com.br',
    'app_env' => 'production',
    'app_key' => 'CHANGE_ME_GENERATE_A_RANDOM_KEY_WITH_AT_LEAST_48_CHARACTERS',
    'session_cookie_secure' => true,
    'db' => [
        'host' => 'HOST_MYSQL_INFORMADO_PELO_PROVEDOR',
        'port' => '3306',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'password' => 'SENHA_DO_BANCO',
        'charset' => 'utf8mb4',
    ],
];
