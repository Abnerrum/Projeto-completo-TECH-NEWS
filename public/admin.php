<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$action = (string) ($_GET['acao'] ?? (is_admin() ? 'dashboard' : 'login'));
$flash = flash_get();
$categories = categories();

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        redirect('admin.php?acao=login');
    }
    redirect('admin.php');
}

if ($action === 'login') {
    if (is_admin()) {
        redirect('admin.php?acao=dashboard');
    }
    $loginError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($email) > 190) {
            $email = substr($email, 0, 190);
        }
        $ipHash = request_ip_hash();
        $emailHash = email_hash($email);
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
        $rateStatement = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (ip_hash = ? OR email_hash = ?) AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)');
        $rateStatement->execute([$ipHash, $emailHash]);
        $attemptCount = (int) $rateStatement->fetchColumn();

        if ($attemptCount >= 5) {
            $loginError = 'Não foi possível entrar. Aguarde 15 minutos e tente novamente.';
        } else {
            $admin = null;
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $statement = db()->prepare('SELECT id, email, password_hash FROM admins WHERE email = ? LIMIT 1');
                $statement->execute([$email]);
                $admin = $statement->fetch() ?: null;
            }
            if ($admin && strlen($password) <= 72 && password_verify($password, $admin['password_hash'])) {
                if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                    $updateHash = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
                    $updateHash->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
                }
                $deleteAttempts = db()->prepare('DELETE FROM login_attempts WHERE ip_hash = ? OR email_hash = ?');
                $deleteAttempts->execute([$ipHash, $emailHash]);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_last_seen'] = time();
                redirect('admin.php?acao=dashboard');
            }

            $insertAttempt = db()->prepare('INSERT INTO login_attempts (ip_hash, email_hash) VALUES (?, ?)');
            $insertAttempt->execute([$ipHash, $emailHash]);
            usleep(250000);
            $loginError = 'E-mail ou senha inválidos.';
        }
    }
    ?>
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entrar | TECH NEWS</title><link rel="stylesheet" href="/assets/style.css"></head><body class="admin-body">
    <main class="admin-auth-wrap">
        <a class="brand auth-brand" href="/"><img src="/assets/logo.svg" alt="" width="48" height="48"><span>TECH <b>NEWS</b><small>PAINEL EDITORIAL</small></span></a>
        <section class="auth-card"><p class="section-kicker">ACESSO RESTRITO</p><h1>Entrar no painel</h1><p class="muted">Faça login para criar, editar ou publicar reportagens.</p>
            <?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
            <?php if ($loginError !== ''): ?><div class="notice error" role="alert"><?= e($loginError) ?></div><?php endif; ?>
            <form method="post" action="admin.php?acao=login" class="stack-form">
                <?= csrf_field() ?>
                <label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" maxlength="190" required value="<?= e($_POST['email'] ?? '') ?>">
                <label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required maxlength="72">
                <button class="button button-primary button-full" type="submit">Entrar com segurança</button>
            </form>
            <p class="auth-footnote">Apenas administradores cadastrados podem acessar este painel.</p>
        </section><a class="back-link auth-back" href="/">← Voltar ao portal</a>
    </main><script src="/assets/app.js" defer></script></body></html>
    <?php
    exit;
}

require_admin();

if ($action === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $title = trim((string) ($_POST['title'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'draft');
    $coverRaw = trim((string) ($_POST['cover_url'] ?? ''));
    $sourceRaw = trim((string) ($_POST['source_url'] ?? ''));
    $errors = [];

    if (mb_strlen_safe($title) < 8 || mb_strlen_safe($title) > 180) {
        $errors[] = 'O título precisa ter entre 8 e 180 caracteres.';
    }
    if (!in_array($category, $categories, true)) {
        $errors[] = 'Selecione uma categoria válida.';
    }
    if (mb_strlen_safe($excerpt) < 20 || mb_strlen_safe($excerpt) > 320) {
        $errors[] = 'O resumo precisa ter entre 20 e 320 caracteres.';
    }
    if (mb_strlen_safe($body) < 50 || mb_strlen_safe($body) > 100000) {
        $errors[] = 'O texto da reportagem precisa ter entre 50 e 100.000 caracteres.';
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'O status selecionado é inválido.';
    }
    try {
        $coverUrl = safe_image_url($coverRaw);
    } catch (InvalidArgumentException $exception) {
        $coverUrl = null;
        $errors[] = $exception->getMessage();
    }

    try {
        $sourceUrl = safe_https_url($sourceRaw);
    } catch (InvalidArgumentException $exception) {
        $sourceUrl = null;
        $errors[] = $exception->getMessage();
    }

    if ($id) {
        $existingStatement = db()->prepare('SELECT id, title, slug, published_at FROM news WHERE id = ? LIMIT 1');
        $existingStatement->execute([$id]);
        $existing = $existingStatement->fetch() ?: null;
        if (!$existing) {
            $errors[] = 'A notícia que você tentou editar não foi encontrada.';
        }
    } else {
        $existing = null;
    }

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect('admin.php?acao=' . ($id ? 'editar&id=' . $id : 'nova'));
    }

    $slug = ($existing && $existing['title'] === $title) ? $existing['slug'] : slugify($title) . '-' . bin2hex(random_bytes(3));
    $publishedAt = $status === 'published' ? (($existing['published_at'] ?? null) ?: gmdate('Y-m-d H:i:s')) : null;

    if ($existing) {
        $statement = db()->prepare('UPDATE news SET title = ?, slug = ?, category = ?, excerpt = ?, body = ?, cover_url = ?, source_url = ?, status = ?, published_at = ? WHERE id = ?');
        $statement->execute([$title, $slug, $category, $excerpt, $body, $coverUrl, $sourceUrl, $status, $publishedAt, $id]);
        flash_set('success', 'Reportagem atualizada com sucesso.');
    } else {
        $statement = db()->prepare('INSERT INTO news (title, slug, category, excerpt, body, cover_url, source_url, status, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$title, $slug, $category, $excerpt, $body, $coverUrl, $sourceUrl, $status, (int) $_SESSION['admin_id'], $publishedAt]);
        flash_set('success', $status === 'published' ? 'Reportagem publicada com sucesso.' : 'Rascunho salvo com sucesso.');
    }
    redirect('admin.php?acao=dashboard');
}

if ($action === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id) {
        $statement = db()->prepare('DELETE FROM news WHERE id = ?');
        $statement->execute([$id]);
        flash_set('success', $statement->rowCount() ? 'Reportagem excluída.' : 'A reportagem não foi encontrada.');
    } else {
        flash_set('error', 'Identificador de notícia inválido.');
    }
    redirect('admin.php?acao=dashboard');
}

$formArticle = ['id' => '', 'title' => '', 'category' => 'Tecnologia', 'excerpt' => '', 'body' => '', 'cover_url' => '', 'source_url' => '', 'status' => 'draft'];
$formError = null;
if ($action === 'editar') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        flash_set('error', 'Identificador de notícia inválido.');
        redirect('admin.php?acao=dashboard');
    }
    $statement = db()->prepare('SELECT id, title, category, excerpt, body, cover_url, source_url, status FROM news WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $found = $statement->fetch();
    if (!$found) {
        flash_set('error', 'Notícia não encontrada.');
        redirect('admin.php?acao=dashboard');
    }
    $formArticle = $found;
    $action = 'editar_form';
}

if (in_array($action, ['nova', 'editar_form'], true)) {
    ?>
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $action === 'nova' ? 'Nova reportagem' : 'Editar reportagem' ?> | TECH NEWS</title><link rel="stylesheet" href="/assets/style.css"></head><body class="admin-body">
    <header class="admin-topbar"><div class="wrap admin-topbar-inner"><a class="brand" href="/"><img src="/assets/logo.svg" alt="" width="42" height="42"><span>TECH <b>NEWS</b><small>PAINEL EDITORIAL</small></span></a><div class="admin-top-actions"><a class="admin-top-link" href="/">Ver portal ↗</a><form method="post" action="admin.php?acao=logout"><?= csrf_field() ?><button type="submit" class="logout-button">Sair</button></form></div></div></header>
    <main class="wrap admin-main"><a class="back-link" href="admin.php?acao=dashboard">← Voltar ao painel</a><div class="section-heading admin-form-heading"><div><p class="section-kicker">EDIÇÃO DE CONTEÚDO</p><h1><?= $action === 'nova' ? 'Nova reportagem' : 'Editar reportagem' ?></h1></div><span class="status-chip <?= e($formArticle['status']) ?>"><?= $formArticle['status'] === 'published' ? 'Publicada' : 'Rascunho' ?></span></div>
        <?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
        <form method="post" action="admin.php?acao=salvar" class="editor-form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($formArticle['id']) ?>">
            <div class="form-field"><label for="title">Título da reportagem *</label><input id="title" name="title" required minlength="8" maxlength="180" value="<?= e($formArticle['title']) ?>" placeholder="Escreva um título claro e informativo"></div>
            <div class="form-grid-two"><div class="form-field"><label for="category">Categoria *</label><select id="category" name="category" required><?php foreach ($categories as $itemCategory): ?><option value="<?= e($itemCategory) ?>" <?= $formArticle['category'] === $itemCategory ? 'selected' : '' ?>><?= e($itemCategory) ?></option><?php endforeach; ?></select></div><div class="form-field"><label for="status">Status *</label><select id="status" name="status"><option value="draft" <?= $formArticle['status'] === 'draft' ? 'selected' : '' ?>>Rascunho — só a equipe vê</option><option value="published" <?= $formArticle['status'] === 'published' ? 'selected' : '' ?>>Publicada — visível para todos</option></select></div></div>
            <div class="form-field"><label for="excerpt">Resumo *</label><textarea id="excerpt" name="excerpt" required minlength="20" maxlength="320" rows="3" placeholder="Resuma a notícia em uma ou duas frases."><?= e($formArticle['excerpt']) ?></textarea><small>Entre 20 e 320 caracteres. Aparece na página inicial e nos resultados.</small></div>
            <div class="form-field"><label for="cover_url">URL da imagem de capa (opcional)</label><input id="cover_url" name="cover_url" type="text" maxlength="2048" value="<?= e($formArticle['cover_url'] ?? '') ?>" placeholder="https://... ou /assets/minha-capa.jpg"><small>Use HTTPS ou uma imagem local em /assets/. O site não faz upload de arquivos.</small></div>
            <div class="form-field"><label for="source_url">Fonte consultada (URL HTTPS, opcional)</label><input id="source_url" name="source_url" type="url" maxlength="2048" value="<?= e($formArticle['source_url'] ?? '') ?>" placeholder="https://site-da-fonte.com/reportagem"><small>Adicione uma referência pública para dar transparência à reportagem.</small></div>
            <div class="form-field"><label for="body">Texto da reportagem *</label><textarea id="body" name="body" required minlength="50" maxlength="100000" rows="18" placeholder="Desenvolva a reportagem. Separe os parágrafos com uma linha em branco."><?= e($formArticle['body']) ?></textarea><small>Texto simples, sem HTML. Isso ajuda a prevenir a execução de código malicioso.</small></div>
            <div class="editor-actions"><a class="button button-secondary" href="admin.php?acao=dashboard">Cancelar</a><button class="button button-primary" type="submit">Salvar reportagem</button></div>
        </form>
    </main><script src="/assets/app.js" defer></script></body></html>
    <?php
    exit;
}

if ($action !== 'dashboard') {
    redirect('admin.php?acao=dashboard');
}

$statement = db()->query('SELECT n.id, n.title, n.category, n.status, n.published_at, n.updated_at FROM news n ORDER BY n.updated_at DESC, n.id DESC LIMIT 100');
$news = $statement->fetchAll();
$countPublished = 0;
$countDrafts = 0;
foreach ($news as $item) {
    if ($item['status'] === 'published') {
        $countPublished++;
    } else {
        $countDrafts++;
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Painel editorial | TECH NEWS</title><link rel="stylesheet" href="/assets/style.css"></head><body class="admin-body">
<header class="admin-topbar"><div class="wrap admin-topbar-inner"><a class="brand" href="/"><img src="/assets/logo.svg" alt="" width="42" height="42"><span>TECH <b>NEWS</b><small>PAINEL EDITORIAL</small></span></a><div class="admin-top-actions"><a class="admin-top-link" href="/">Ver portal ↗</a><form method="post" action="admin.php?acao=logout"><?= csrf_field() ?><button type="submit" class="logout-button">Sair</button></form></div></div></header>
<main class="wrap admin-main"><section class="dashboard-heading"><div><p class="section-kicker">CENTRAL DE PUBLICAÇÃO</p><h1>Bom trabalho, equipe.</h1><p class="muted">Gerencie as notícias do TECH NEWS em um só lugar.</p></div><a class="button button-primary" href="admin.php?acao=nova">＋ Nova reportagem</a></section>
<?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="stats-grid"><div class="stat-card"><span>Conteúdos no painel</span><strong><?= count($news) ?></strong><small>Limite da lista: 100 mais recentes</small></div><div class="stat-card"><span>Publicadas na lista</span><strong><?= $countPublished ?></strong><small>Podem aparecer no portal</small></div><div class="stat-card"><span>Rascunhos na lista</span><strong><?= $countDrafts ?></strong><small>Visíveis apenas à equipe</small></div></div>
<section class="admin-list-section"><div class="section-heading"><div><p class="section-kicker">CONTEÚDO</p><h2>Reportagens recentes</h2></div></div>
<?php if ($news): ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Reportagem</th><th>Categoria</th><th>Status</th><th>Atualizada</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($news as $item): ?><tr><td class="table-title-cell"><strong><?= e($item['title']) ?></strong><small>#<?= (int) $item['id'] ?></small></td><td><?= e($item['category']) ?></td><td><span class="status-chip <?= e($item['status']) ?>"><?= $item['status'] === 'published' ? 'Publicada' : 'Rascunho' ?></span></td><td><?= e(format_date($item['updated_at'])) ?></td><td><div class="table-actions"><a href="admin.php?acao=editar&id=<?= (int) $item['id'] ?>">Editar</a><?php if ($item['status'] === 'published'): ?><a href="/?artigo=<?= rawurlencode(slug_for_news((int) $item['id'])) ?>" target="_blank" rel="noopener noreferrer">Ver</a><?php endif; ?><form method="post" action="admin.php?acao=excluir" data-confirm="Excluir esta reportagem permanentemente?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="delete-link" type="submit">Excluir</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><div class="empty-state empty-state-small"><div class="empty-icon">✦</div><h3>Nenhuma reportagem ainda.</h3><p>Crie um rascunho, revise os dados e publique quando estiver pronto.</p><a class="button button-primary" href="admin.php?acao=nova">Criar primeira reportagem</a></div><?php endif; ?></section>
<p class="admin-security-note"><span>●</span> Sessão protegida · A sessão expira após 30 minutos de inatividade · Formulários usam proteção CSRF</p>
</main><script src="/assets/app.js" defer></script></body></html>
<?php
// Compatibility helper: use a prepared query and return the current slug for a public link.
function slug_for_news(int $id): string
{
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $statement = db()->prepare('SELECT slug FROM news WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $cache[$id] = (string) ($statement->fetchColumn() ?: '');
    return $cache[$id];
}

function mb_strlen_safe(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}
