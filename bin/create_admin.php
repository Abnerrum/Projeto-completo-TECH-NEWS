<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

fwrite(STDOUT, "TECH NEWS — criar conta administrativa\n");
fwrite(STDOUT, "E-mail do administrador: ");
$email = strtolower(trim((string) fgets(STDIN)));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}

fwrite(STDOUT, "Senha (mínimo de 12 caracteres): ");
$canHide = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec') && function_exists('exec');
if ($canHide) {
    @shell_exec('stty -echo 2>/dev/null');
}
$password = trim((string) fgets(STDIN));
if ($canHide) {
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "A senha deve ter entre 12 e 72 bytes de comprimento.\n");
    exit(1);
}

try {
    $check = db()->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        fwrite(STDERR, "Já existe uma conta com esse e-mail.\n");
        exit(1);
    }

    $insert = db()->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Administrador criado. Acesse /admin.php para entrar.\n");
} catch (Throwable $exception) {
    error_log('TECH NEWS admin creation failed: ' . $exception->getMessage());
    fwrite(STDERR, "Não foi possível criar a conta. Verifique a conexão com o banco e o log do PHP.\n");
    exit(1);
} finally {
    if (isset($password)) {
        $password = str_repeat("\0", strlen($password));
    }
}
