<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$article = null;
$notFound = false;
$category = trim((string) ($_GET['categoria'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

if (isset($_GET['artigo'])) {
    $slug = (string) $_GET['artigo'];
    if (strlen($slug) > 220 || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        $notFound = true;
    } else {
        $statement = db()->prepare("SELECT n.id, n.title, n.slug, n.category, n.excerpt, n.body, n.cover_url, n.source_url, n.published_at, a.email AS author_email FROM news n JOIN admins a ON a.id = n.author_id WHERE n.slug = ? AND n.status = 'published' AND n.published_at <= UTC_TIMESTAMP() LIMIT 1");
        $statement->execute([$slug]);
        $article = $statement->fetch() ?: null;
        $notFound = $article === null;
    }
}

$categories = categories();
if (!$article && !$notFound) {
    $conditions = ["n.status = 'published'", 'n.published_at IS NOT NULL', 'n.published_at <= UTC_TIMESTAMP()'];
    $params = [];
    if (in_array($category, $categories, true)) {
        $conditions[] = 'n.category = ?';
        $params[] = $category;
    } else {
        $category = '';
    }
    if ($search !== '') {
        $conditions[] = '(n.title LIKE ? OR n.excerpt LIKE ? OR n.body LIKE ?)';
        $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        array_push($params, $term, $term, $term);
    }
    $sql = 'SELECT n.id, n.title, n.slug, n.category, n.excerpt, n.cover_url, n.published_at FROM news n WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.published_at DESC, n.id DESC LIMIT 24';
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $articles = $statement->fetchAll();
}

$pageTitle = $article ? ($article['title'] . ' | TECH NEWS') : ($notFound ? 'Notícia não encontrada | TECH NEWS' : 'TECH NEWS — Tecnologia, economia, Brasil e mundo');
$description = $article ? $article['excerpt'] : 'Notícias, análises e contexto sobre tecnologia, inteligência artificial, programação, economia, cibersegurança, Brasil e mundo.';
$flash = flash_get();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#061323">
    <link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="topline wrap">
        <a class="brand" href="/" aria-label="TECH NEWS — página inicial">
            <img src="/assets/logo.svg" alt="" width="48" height="48">
            <span>TECH <b>NEWS</b><small>TECNOLOGIA · ECONOMIA · BRASIL · MUNDO</small></span>
        </a>
        <div class="topline-tag">INFORMAÇÃO COM CONTEXTO.<br><strong>TECNOLOGIA COM PROPÓSITO.</strong></div>
        <a class="admin-link" href="/admin.php">Área editorial <span aria-hidden="true">↗</span></a>
    </div>
    <nav class="nav-wrap" aria-label="Categorias">
        <div class="wrap nav-inner">
            <a class="nav-item <?= $category === '' ? 'active' : '' ?>" href="/">Últimas notícias</a>
            <?php foreach ($categories as $navCategory): ?>
                <a class="nav-item <?= $category === $navCategory ? 'active' : '' ?>" href="/?categoria=<?= rawurlencode($navCategory) ?>"><?= e($navCategory) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="wrap main-content">
    <?php if ($flash): ?>
        <div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($article): ?>
        <article class="article-page">
            <a class="back-link" href="/">← Voltar às notícias</a>
            <div class="eyebrow"><span><?= e($article['category']) ?></span><time datetime="<?= e($article['published_at']) ?>"><?= e(format_date($article['published_at'])) ?></time></div>
            <h1 class="article-title"><?= e($article['title']) ?></h1>
            <p class="article-lead"><?= e($article['excerpt']) ?></p>
            <?php if (!empty($article['cover_url'])): ?>
                <img class="article-cover" src="<?= e($article['cover_url']) ?>" alt="Imagem ilustrativa: <?= e($article['title']) ?>" referrerpolicy="no-referrer">
            <?php else: ?>
                <div class="article-cover cover-placeholder" role="img" aria-label="Imagem não fornecida"><span>TECH NEWS</span><b><?= e($article['category']) ?></b></div>
            <?php endif; ?>
            <div class="article-body"><?= body_as_paragraphs((string) $article['body']) ?></div>
            <?php if (!empty($article['source_url'])): ?><p class="source-note">Fonte consultada: <a href="<?= e($article['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">abrir referência externa ↗</a></p><?php endif; ?>
            <div class="article-endnote">TECH NEWS · Informação com contexto. Tecnologia com propósito.</div>
        </article>
    <?php elseif ($notFound): ?>
        <section class="empty-state">
            <span class="empty-kicker">404 · CONTEÚDO NÃO ENCONTRADO</span>
            <h1>Essa notícia não está disponível.</h1>
            <p>Ela pode ter sido removida ou ainda não foi publicada.</p>
            <a class="button button-primary" href="/">Voltar para a página inicial</a>
        </section>
    <?php else: ?>
        <section class="hero-row">
            <div>
                <p class="section-kicker">O QUE ESTÁ ACONTECENDO</p>
                <h1>Notícia com contexto.<br><span>Tecnologia para entender o mundo.</span></h1>
                <p class="hero-copy">Acompanhe os principais assuntos de tecnologia, inteligência artificial, programação, economia, cibersegurança e Brasil.</p>
            </div>
            <form class="search-box" method="get" action="/" role="search">
                <label for="search-news">Buscar no portal</label>
                <div class="search-input-row">
                    <input id="search-news" name="q" type="search" maxlength="100" placeholder="Ex.: inteligência artificial" value="<?= e($search) ?>">
                    <button type="submit" aria-label="Buscar notícias">Buscar</button>
                </div>
                <?php if ($category !== ''): ?><input type="hidden" name="categoria" value="<?= e($category) ?>"><?php endif; ?>
            </form>
        </section>

        <section class="news-section" aria-labelledby="latest-heading">
            <div class="section-heading">
                <div><p class="section-kicker">ATUALIZAÇÃO CONTÍNUA</p><h2 id="latest-heading"><?= $category !== '' ? e($category) : ($search !== '' ? 'Resultados da busca' : 'Últimas notícias') ?></h2></div>
                <span class="section-count"><?= count($articles) ?> <?= count($articles) === 1 ? 'matéria' : 'matérias' ?></span>
            </div>

            <?php if ($articles): ?>
                <div class="news-grid">
                    <?php foreach ($articles as $item): ?>
                        <article class="news-card">
                            <a class="card-image" href="/?artigo=<?= rawurlencode($item['slug']) ?>" aria-label="Ler <?= e($item['title']) ?>">
                                <?php if (!empty($item['cover_url'])): ?>
                                    <img src="<?= e($item['cover_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <div class="cover-placeholder"><span>TECH</span><b>NEWS</b><small><?= e(strtoupper($item['category'])) ?></small></div>
                                <?php endif; ?>
                            </a>
                            <div class="card-content">
                                <div class="card-meta"><span><?= e($item['category']) ?></span><time datetime="<?= e($item['published_at']) ?>"><?= e(format_date($item['published_at'])) ?></time></div>
                                <h3><a href="/?artigo=<?= rawurlencode($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                                <p><?= e($item['excerpt']) ?></p>
                                <a class="read-more" href="/?artigo=<?= rawurlencode($item['slug']) ?>">Ler reportagem <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state empty-state-small">
                    <div class="empty-icon">✦</div>
                    <h3><?= $search !== '' ? 'Nenhuma notícia encontrada.' : 'As próximas notícias começam aqui.' ?></h3>
                    <p><?= $search !== '' ? 'Tente outros termos ou escolha uma categoria.' : 'O portal está pronto. Entre na área editorial para publicar a primeira reportagem.' ?></p>
                    <?php if ($search !== ''): ?><a class="button button-secondary" href="/">Limpar busca</a><?php else: ?><a class="button button-primary" href="/admin.php">Abrir área editorial</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<footer class="site-footer">
    <div class="wrap footer-top">
        <a class="brand brand-footer" href="/"><img src="/assets/logo.svg" alt="" width="40" height="40"><span>TECH <b>NEWS</b><small>INFORMAÇÃO COM CONTEXTO</small></span></a>
        <p>Tecnologia, economia, Brasil e mundo.<br><strong>Mais informação. Melhores decisões.</strong></p>
        <a class="footer-admin" href="/admin.php">Área editorial ↗</a>
    </div>
    <div class="footer-bottom"><div class="wrap"><span>© <?= date('Y') ?> TECH NEWS</span><span>Conteúdo publicado pela equipe editorial.</span></div></div>
</footer>
</body>
</html>
