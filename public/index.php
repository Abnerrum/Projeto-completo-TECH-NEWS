<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$article = null;
$notFound = false;
$relatedArticles = [];
$featuredArticle = null;
$articles = [];
$totalArticles = 0;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = is_int($page) && $page > 0 ? $page : 1;
$perPage = 12;
$totalPages = 1;
$readingMinutes = 1;
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
        if ($article) {
            $relatedStatement = db()->prepare("SELECT n.id, n.title, n.slug, n.category, n.excerpt, n.cover_url, n.published_at FROM news n WHERE n.status = 'published' AND n.published_at IS NOT NULL AND n.published_at <= UTC_TIMESTAMP() AND n.category = ? AND n.id <> ? ORDER BY n.published_at DESC, n.id DESC LIMIT 3");
            $relatedStatement->execute([$article['category'], $article['id']]);
            $relatedArticles = $relatedStatement->fetchAll();
        }
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
    $countSql = 'SELECT COUNT(*) FROM news n WHERE ' . implode(' AND ', $conditions);
    $countStatement = db()->prepare($countSql);
    $countStatement->execute($params);
    $totalArticles = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($totalArticles / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $sql = 'SELECT n.id, n.title, n.slug, n.category, n.excerpt, n.cover_url, n.published_at FROM news n WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.published_at DESC, n.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $articles = $statement->fetchAll();

    // Give the most recent story a larger cover spot on the unfiltered homepage.
    if ($category === '' && $search === '' && $page === 1 && $totalArticles > 1 && $articles) {
        $featuredArticle = array_shift($articles);
    }
}

if ($article) {
    $wordCount = preg_match_all('/\S+/u', (string) $article['body']);
    $readingMinutes = max(1, (int) ceil(max(0, (int) $wordCount) / 220));
}

$pageTitle = $article ? ($article['title'] . ' | TECH NEWS') : ($notFound ? 'Notícia não encontrada | TECH NEWS' : 'TECH NEWS — Tecnologia, economia, Brasil e mundo');
$description = $article ? $article['excerpt'] : 'Notícias, análises e contexto sobre tecnologia, inteligência artificial, programação, economia, cibersegurança, Brasil e mundo.';

$siteUrl = rtrim((string) ($GLOBALS['technews_config']['app_url'] ?? ''), '/');
$siteUrlParts = $siteUrl !== '' ? parse_url($siteUrl) : false;
if (!is_array($siteUrlParts)
    || strtolower((string) ($siteUrlParts['scheme'] ?? '')) !== 'https'
    || empty($siteUrlParts['host'])
    || isset($siteUrlParts['user'])
    || isset($siteUrlParts['pass'])) {
    $siteUrl = '';
}

$canonicalUrl = '';
if ($siteUrl !== '') {
    if ($article) {
        $canonicalUrl = $siteUrl . '/?artigo=' . rawurlencode((string) $article['slug']);
    } elseif (!$notFound) {
        $canonicalParams = [];
        if ($category !== '') {
            $canonicalParams['categoria'] = $category;
        }
        if ($search !== '') {
            $canonicalParams['q'] = $search;
        }
        if ($page > 1) {
            $canonicalParams['page'] = $page;
        }
        $canonicalUrl = $siteUrl . '/' . ($canonicalParams ? '?' . http_build_query($canonicalParams) : '');
    }
}

$socialImage = null;
if ($article && !empty($article['cover_url'])) {
    $coverUrl = (string) $article['cover_url'];
    $coverParts = parse_url($coverUrl);
    if (filter_var($coverUrl, FILTER_VALIDATE_URL)
        && is_array($coverParts)
        && strtolower((string) ($coverParts['scheme'] ?? '')) === 'https'
        && !empty($coverParts['host'])) {
        $socialImage = $coverUrl;
    } elseif ($siteUrl !== '' && str_starts_with($coverUrl, '/assets/')) {
        $socialImage = $siteUrl . $coverUrl;
    }
}

$structuredData = false;
if ($article && $canonicalUrl !== '') {
    $publishedDate = (new DateTimeImmutable((string) $article['published_at'], new DateTimeZone('UTC')))->format(DATE_W3C);
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => (string) $article['title'],
        'description' => (string) $article['excerpt'],
        'datePublished' => $publishedDate,
        'author' => ['@type' => 'Organization', 'name' => 'Redação TECH NEWS'],
        'publisher' => ['@type' => 'Organization', 'name' => 'TECH NEWS'],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
    ];
    if ($socialImage !== null) {
        $schema['image'] = [$socialImage];
    }
    $structuredData = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
$flash = flash_get();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="<?= $article ? 'article' : 'website' ?>">
    <meta property="og:site_name" content="TECH NEWS">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta name="twitter:card" content="<?= $socialImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
        <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if ($socialImage !== null): ?>
        <meta property="og:image" content="<?= e($socialImage) ?>">
        <meta name="twitter:image" content="<?= e($socialImage) ?>">
    <?php endif; ?>
    <?php if ($article && $structuredData !== false): ?>
        <script type="application/ld+json"><?= $structuredData ?></script>
    <?php endif; ?>
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
            <a class="nav-item mission-nav-link" href="/missao.php">Especial Missão <span aria-hidden="true">↗</span></a>
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
            <div class="eyebrow">
                <span><?= e($article['category']) ?></span>
                <time datetime="<?= e($article['published_at']) ?>"><?= e(format_date($article['published_at'])) ?></time>
                <span class="reading-time"><?= $readingMinutes ?> min de leitura</span>
                <span class="article-byline">Por Redação TECH NEWS</span>
            </div>
            <h1 class="article-title"><?= e($article['title']) ?></h1>
            <p class="article-lead"><?= e($article['excerpt']) ?></p>
            <?php if (!empty($article['cover_url'])): ?>
                <img class="article-cover" src="<?= e($article['cover_url']) ?>" alt="Imagem ilustrativa: <?= e($article['title']) ?>" referrerpolicy="no-referrer">
            <?php else: ?>
                <div class="article-cover cover-placeholder" role="img" aria-label="Imagem não fornecida"><span>TECH NEWS</span><b><?= e($article['category']) ?></b></div>
            <?php endif; ?>
            <div class="article-body"><?= body_as_paragraphs((string) $article['body']) ?></div>
            <?php if (!empty($article['source_url'])): ?><p class="source-note">Fonte consultada: <a href="<?= e($article['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">abrir referência externa ↗</a></p><?php endif; ?>
            <div class="share-tools">
                <div class="share-copy">
                    <strong>Compartilhe esta reportagem</strong>
                    <p>Informação de qualidade fica melhor quando chega a mais pessoas.</p>
                </div>
                <div class="share-actions">
                    <button type="button" class="share-button" data-copy-page-url>Copiar link</button>
                    <button type="button" class="share-button share-button-primary" data-share-whatsapp data-share-title="<?= e($article['title']) ?>">Compartilhar no WhatsApp ↗</button>
                </div>
                <p class="share-status" data-share-status aria-live="polite"></p>
            </div>
            <div class="article-endnote">TECH NEWS · Informação com contexto. Tecnologia com propósito.</div>
            <?php if ($relatedArticles): ?>
                <section class="related-section" aria-labelledby="related-heading">
                    <p class="section-kicker">CONTINUE INFORMADO</p>
                    <h2 id="related-heading">Mais sobre <?= e($article['category']) ?></h2>
                    <div class="related-grid">
                        <?php foreach ($relatedArticles as $related): ?>
                            <article class="related-card">
                                <a class="related-image" href="/?artigo=<?= rawurlencode($related['slug']) ?>" aria-label="Ler <?= e($related['title']) ?>">
                                    <?php if (!empty($related['cover_url'])): ?>
                                        <img src="<?= e($related['cover_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <div class="cover-placeholder"><span>TECH</span><b>NEWS</b></div>
                                    <?php endif; ?>
                                </a>
                                <div class="related-card-content">
                                    <span><?= e($related['category']) ?> · <?= e(format_date($related['published_at'])) ?></span>
                                    <h3><a href="/?artigo=<?= rawurlencode($related['slug']) ?>"><?= e($related['title']) ?></a></h3>
                                    <p><?= e($related['excerpt']) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
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

        <?php if ($featuredArticle): ?>
            <section class="featured-layout" aria-label="Reportagem mais recente em destaque">
                <a class="featured-story" href="/?artigo=<?= rawurlencode($featuredArticle['slug']) ?>">
                    <div class="featured-media">
                        <?php if (!empty($featuredArticle['cover_url'])): ?>
                            <img src="<?= e($featuredArticle['cover_url']) ?>" alt="" loading="eager" fetchpriority="high" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <div class="cover-placeholder"><span>TECH</span><b>NEWS</b><small><?= e(strtoupper($featuredArticle['category'])) ?></small></div>
                        <?php endif; ?>
                        <span class="featured-ribbon">NA CAPA</span>
                    </div>
                    <div class="featured-copy">
                        <span class="featured-label"><?= e($featuredArticle['category']) ?> · REPORTAGEM EM DESTAQUE</span>
                        <h2><?= e($featuredArticle['title']) ?></h2>
                        <p><?= e($featuredArticle['excerpt']) ?></p>
                        <span class="featured-readmore">Ler reportagem completa <span aria-hidden="true">→</span></span>
                        <time datetime="<?= e($featuredArticle['published_at']) ?>"><?= e(format_date($featuredArticle['published_at'])) ?></time>
                    </div>
                </a>
                <aside class="topic-aside">
                    <p class="section-kicker">EXPLORE O PORTAL</p>
                    <h2>Assuntos que importam</h2>
                    <p>Escolha uma editoria e encontre reportagens, análises e explicações para entender melhor o que acontece.</p>
                    <div class="topic-links">
                        <?php foreach ($categories as $topic): ?>
                            <a href="/?categoria=<?= rawurlencode($topic) ?>"><?= e($topic) ?><span aria-hidden="true">↗</span></a>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>
        <?php endif; ?>

        <section class="news-section" aria-labelledby="latest-heading">
            <div class="section-heading">
                <div><p class="section-kicker">ATUALIZAÇÃO CONTÍNUA</p><h2 id="latest-heading"><?= $category !== '' ? e($category) : ($search !== '' ? 'Resultados da busca' : 'Últimas notícias') ?></h2></div>
                <span class="section-count"><?= $totalArticles ?> <?= $totalArticles === 1 ? 'matéria' : 'matérias' ?> no total</span>
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
                <?php if ($totalPages > 1): ?>
                    <?php
                    $pageParams = [];
                    if ($category !== '') {
                        $pageParams['categoria'] = $category;
                    }
                    if ($search !== '') {
                        $pageParams['q'] = $search;
                    }
                    ?>
                    <nav class="pagination" aria-label="Paginação de notícias">
                        <?php if ($page > 1): ?>
                            <a href="/?<?= e(http_build_query($pageParams + ['page' => $page - 1])) ?>">← Anterior</a>
                        <?php else: ?>
                            <span class="pagination-disabled">← Anterior</span>
                        <?php endif; ?>
                        <span class="pagination-status">Página <?= $page ?> de <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="/?<?= e(http_build_query($pageParams + ['page' => $page + 1])) ?>">Próxima →</a>
                        <?php else: ?>
                            <span class="pagination-disabled">Próxima →</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
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
