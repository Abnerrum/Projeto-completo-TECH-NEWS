<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');

$setupKey = (string) ($GLOBALS['technews_config']['setup_key'] ?? '');
if ($setupKey === '' || str_starts_with($setupKey, 'GENERATE_')) {
    http_response_code(404);
    exit('Página não encontrada.');
}

try {
    $count = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (Throwable $exception) {
    error_log('TECH NEWS setup check failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Não foi possível verificar a configuração. Confira o banco de dados.');
}

if ($count > 0) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $providedKey = (string) ($_POST['setup_key'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!hash_equals($setupKey, $providedKey)) {
        $errors[] = 'Chave de configuração incorreta.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Informe um endereço de e-mail válido.';
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        $errors[] = 'A senha precisa ter entre 12 e 72 bytes.';
    }
    if (!hash_equals($password, $confirmPassword)) {
        $errors[] = 'As senhas não são iguais.';
    }

    if (!$errors) {
        try {
            // Recheck immediately before insert. The secret setup key must be unique and private.
            $count = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($count > 0) {
                http_response_code(404);
                exit('Página não encontrada.');
            }

            $statement = db()->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
            $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $success = true;
        } catch (PDOException $exception) {
            error_log('TECH NEWS setup insert failed: ' . $exception->getMessage());
            $errors[] = 'Não foi possível criar a conta. Confira o banco e tente novamente.';
        } finally {
            $password = str_repeat("\0", strlen($password));
            $confirmPassword = str_repeat("\0", strlen($confirmPassword));
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Configuração inicial | TECH NEWS</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="admin-body">
<main class="admin-auth-wrap">
    <a class="brand auth-brand" href="/"><img src="/assets/logo.svg" alt="" width="48" height="48"><span>TECH <b>NEWS</b><small>CONFIGURAÇÃO INICIAL</small></span></a>
    <section class="auth-card">
        <p class="section-kicker">PRIMEIRO ACESSO</p>
        <h1>Criar administrador</h1>
        <?php if ($success): ?>
            <div class="notice success" role="status">Administrador criado com sucesso.</div>
            <p class="muted">Agora entre no painel editorial. Por segurança, remova <code>public/setup-admin.php</code> do servidor e apague a opção <code>setup_key</code> de <code>config/local.php</code>.</p>
            <a class="button button-primary button-full" href="/admin.php">Ir para o painel</a>
        <?php else: ?>
            <p class="muted">Use esta página apenas uma vez, com a chave privada configurada no arquivo local de configuração.</p>
            <?php if ($errors): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/setup-admin.php" class="stack-form">
                <?= csrf_field() ?>
                <label for="setup_key">Chave de configuração</label>
                <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>
                <label for="email">E-mail do administrador</label>
                <input id="email" name="email" type="email" autocomplete="username" maxlength="190" required value="<?= e($_POST['email'] ?? '') ?>">
                <label for="password">Senha (12 a 72 caracteres)</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                <label for="confirm_password">Confirme a senha</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                <button class="button button-primary button-full" type="submit">Criar administrador</button>
            </form>
        <?php endif; ?>
    </section>
    <a class="back-link auth-back" href="/">← Voltar ao portal</a>
</main>
</body>
</html>
