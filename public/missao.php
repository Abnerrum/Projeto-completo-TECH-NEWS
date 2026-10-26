<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$siteUrl = rtrim((string) ($GLOBALS['technews_config']['app_url'] ?? ''), '/');
$siteUrlParts = $siteUrl !== '' ? parse_url($siteUrl) : false;
if (!is_array($siteUrlParts)
    || strtolower((string) ($siteUrlParts['scheme'] ?? '')) !== 'https'
    || empty($siteUrlParts['host'])
    || isset($siteUrlParts['user'])
    || isset($siteUrlParts['pass'])) {
    $siteUrl = '';
}
$canonicalUrl = $siteUrl !== '' ? $siteUrl . '/missao.php' : '';
$logoUrl = 'https://static.poder360.com.br/2023/11/logo-missao-2.png';
$heroImageUrl = 'https://www.jornaldocomercio.com/_midias/jpg/2026/07/20/760x541/1_hniilqlxaaakbhy-11952119.jpg';
$heroPhotoSource = 'https://www.jornaldocomercio.com/politica/2026/07/1256786-missao-antecipa-convencao-e-oficializa-candidatura-de-renan-santos-a-presidencia.html';

$internalArticles = [];
try {
    $sql = "SELECT n.id, n.title, n.slug, n.category, n.excerpt, n.cover_url, n.source_url, n.published_at
            FROM news n
            WHERE n.status = 'published'
              AND n.published_at IS NOT NULL
              AND n.published_at <= UTC_TIMESTAMP()
              AND (
                  LOWER(CONCAT(COALESCE(n.title, ''), ' ', COALESCE(n.excerpt, ''), ' ', COALESCE(n.body, ''))) LIKE ?
                  OR LOWER(CONCAT(COALESCE(n.title, ''), ' ', COALESCE(n.excerpt, ''), ' ', COALESCE(n.body, ''))) LIKE ?
                  OR (
                      n.category IN ('Política', 'Brasil')
                      AND (
                          LOWER(CONCAT(COALESCE(n.title, ''), ' ', COALESCE(n.excerpt, ''), ' ', COALESCE(n.body, ''))) LIKE ?
                          OR LOWER(CONCAT(COALESCE(n.title, ''), ' ', COALESCE(n.excerpt, ''), ' ', COALESCE(n.body, ''))) LIKE ?
                          OR LOWER(CONCAT(COALESCE(n.title, ''), ' ', COALESCE(n.excerpt, ''), ' ', COALESCE(n.body, ''))) LIKE ?
                      )
                  )
              )
            ORDER BY n.published_at DESC, n.id DESC
            LIMIT 12";
    $statement = db()->prepare($sql);
    $statement->execute(['%partido missão%', '%partido missao%', '%renan santos%', '%missão%', '%missao%']);
    $internalArticles = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('TECH NEWS: não foi possível carregar as notícias da editoria Missão.');
}

$pressStories = [
    [
        'date' => '2026-10-26',
        'date_label' => '26 out. 2026',
        'source' => 'UOL',
        'title' => 'Renan Santos pede que a direita fique fora de atos pró-Flávio',
        'summary' => 'A reportagem registra a manifestação de Renan Santos sobre protestos após o resultado eleitoral e sua declaração de que não houve fraude.',
        'url' => 'https://noticias.uol.com.br/eleicoes/2026/10/26/renan-santos-pede-a-direita-que-fique-fora-de-atos-pro-flavio.ghtm',
    ],
    [
        'date' => '2026-10-22',
        'date_label' => '22 out. 2026',
        'source' => 'CNN Brasil',
        'title' => 'Partido Missão busca se consolidar como oposição a Flávio',
        'summary' => 'A análise da CNN aborda o posicionamento que integrantes do partido dizem pretender adotar diante do segundo turno presidencial.',
        'url' => 'https://www.cnnbrasil.com.br/eleicoes/partido-missao-de-renan-santos-quer-se-consolidar-como-oposicao-a-flavio/',
    ],
    [
        'date' => '2026-10-05',
        'date_label' => '5 out. 2026',
        'source' => 'Folha de S.Paulo',
        'title' => 'Missão aposta em expansão nacional, mas elege dois deputados',
        'summary' => 'A matéria analisa o desempenho eleitoral da legenda na primeira eleição após o registro oficial do partido.',
        'url' => 'https://www1.folha.uol.com.br/poder/2026/10/missao-aposta-em-expansao-nacional-mas-elege-so-dois-deputados.shtml',
    ],
];

$pageTitle = 'Partido Missão: notícias e cobertura | TECH NEWS';
$description = 'Cobertura jornalística do Partido Missão: notícias publicadas pelo TECH NEWS, links de imprensa, identidade institucional e fontes oficiais.';
$flash = flash_get();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#12100a">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="TECH NEWS">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:image" content="<?= e($logoUrl) ?>">
    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
        <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="mission-page">
<header class="site-header mission-site-header">
    <div class="topline wrap">
        <a class="brand" href="/" aria-label="TECH NEWS — página inicial">
            <img src="/assets/logo.svg" alt="" width="48" height="48">
            <span>TECH <b>NEWS</b><small>TECNOLOGIA · ECONOMIA · BRASIL · MUNDO</small></span>
        </a>
        <div class="topline-tag">INFORMAÇÃO COM CONTEXTO.<br><strong>TECNOLOGIA COM PROPÓSITO.</strong></div>
        <a class="admin-link" href="/admin.php">Área editorial <span aria-hidden="true">↗</span></a>
    </div>
    <nav class="nav-wrap" aria-label="Navegação principal">
        <div class="wrap nav-inner">
            <a class="nav-item" href="/">Últimas notícias</a>
            <a class="nav-item" href="/?categoria=Pol%C3%ADtica">Política</a>
            <a class="nav-item" href="/?categoria=Brasil">Brasil</a>
            <a class="nav-item mission-nav-active" href="/missao.php" aria-current="page">Especial Missão</a>
        </div>
    </nav>
</header>

<main class="wrap main-content mission-main">
    <?php if ($flash): ?>
        <div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="mission-hero" aria-labelledby="mission-title">
        <div class="mission-hero-copy">
            <p class="mission-kicker"><span></span> EDITORIA ESPECIAL · POLÍTICA</p>
            <h1 id="mission-title">Partido <em>Missão</em></h1>
            <p class="mission-slogan">“A missão de criar um Brasil melhor.”</p>
            <p class="mission-intro">Acompanhe notícias, posicionamentos públicos e informações sobre o Partido Missão, com datas, identificação das fontes e links para consultar as reportagens originais.</p>
            <div class="mission-facts">
                <div><span>LEGENDA</span><strong>14</strong></div>
                <div><span>REGISTRO NO TSE</span><strong>04 nov. 2025</strong></div>
                <div><span>COBERTURA</span><strong>Independente</strong></div>
            </div>
            <div class="mission-hero-actions">
                <a class="mission-button mission-button-gold" href="#noticias-tech-news">Notícias no TECH NEWS ↓</a>
                <a class="mission-button mission-button-outline" href="https://missao.org.br/" target="_blank" rel="noopener noreferrer">Site oficial ↗</a>
            </div>
        </div>
        <div class="mission-hero-visual">
            <div class="mission-logo-frame">
                <img src="<?= e($logoUrl) ?>" alt="Logotipo do Partido Missão reproduzido em publicação do Poder360" referrerpolicy="no-referrer">
                <span>IDENTIDADE VISUAL DO PARTIDO</span>
            </div>
            <figure class="mission-photo">
                <a href="<?= e($heroPhotoSource) ?>" target="_blank" rel="noopener noreferrer" aria-label="Ver reportagem original do Jornal do Comércio">
                    <img src="<?= e($heroImageUrl) ?>" alt="Apresentação de bandeira do Partido Missão em evento partidário" loading="lazy" referrerpolicy="no-referrer">
                </a>
                <figcaption>Imagem de evento partidário · <a href="<?= e($heroPhotoSource) ?>" target="_blank" rel="noopener noreferrer">Jornal do Comércio, 20 jul. 2026 ↗</a></figcaption>
            </figure>
            <p class="mission-logo-credit">Logotipo: imagem publicada pelo <a href="https://www.poder360.com.br/galeria/partido-missao-logo/" target="_blank" rel="noopener noreferrer">Poder360 ↗</a>.</p>
        </div>
    </section>

    <div class="mission-editorial-note">
        <strong>Nota editorial</strong>
        <p>O TECH NEWS não é o site oficial do Partido Missão. Esta página reúne cobertura jornalística e referências externas; os links levam aos veículos e órgãos responsáveis pelo conteúdo original.</p>
    </div>

    <section class="mission-section" id="noticias-tech-news" aria-labelledby="mission-news-title">
        <div class="section-heading">
            <div><p class="mission-kicker">PUBLICAÇÕES DA REDAÇÃO</p><h2 id="mission-news-title">Notícias no TECH NEWS</h2></div>
            <span class="section-count"><?= count($internalArticles) ?> <?= count($internalArticles) === 1 ? 'matéria' : 'matérias' ?></span>
        </div>
        <?php if ($internalArticles): ?>
            <div class="news-grid">
                <?php foreach ($internalArticles as $item): ?>
                    <article class="news-card">
                        <a class="card-image" href="/?artigo=<?= rawurlencode($item['slug']) ?>" aria-label="Ler <?= e($item['title']) ?>">
                            <?php if (!empty($item['cover_url'])): ?>
                                <img src="<?= e($item['cover_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <div class="mission-card-placeholder"><span>MISSÃO</span><b>14</b></div>
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
            <div class="mission-empty">
                <span class="mission-empty-icon">M14</span>
                <div>
                    <h3>Ainda não há reportagens próprias nesta editoria.</h3>
                    <p>As matérias publicadas pela redação aparecem aqui automaticamente quando mencionam o Partido Missão ou Renan Santos. Para publicar, use a área editorial e inclua a fonte consultada.</p>
                    <a class="mission-inline-link" href="/admin.php">Abrir área editorial ↗</a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="mission-section" aria-labelledby="press-title">
        <div class="section-heading">
            <div><p class="mission-kicker">LINKS EXTERNOS · FONTES IDENTIFICADAS</p><h2 id="press-title">Na imprensa</h2></div>
            <span class="section-count">Leituras selecionadas</span>
        </div>
        <div class="mission-press-grid">
            <?php foreach ($pressStories as $story): ?>
                <article class="mission-press-card">
                    <div class="mission-press-meta"><span><?= e($story['source']) ?></span><time datetime="<?= e($story['date']) ?>"><?= e($story['date_label']) ?></time></div>
                    <h3><a href="<?= e($story['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($story['title']) ?> ↗</a></h3>
                    <p><?= e($story['summary']) ?></p>
                    <a class="mission-read-source" href="<?= e($story['url']) ?>" target="_blank" rel="noopener noreferrer">Ler no veículo original <span aria-hidden="true">→</span></a>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="mission-curation-note">Esta é uma seleção editorial inicial, identificada por veículo e data. A redação deve revisar os links e atualizar a seleção periodicamente; não reproduzimos o texto integral das reportagens externas.</p>
    </section>

    <section class="mission-resources" aria-labelledby="mission-resources-title">
        <div>
            <p class="mission-kicker">CONSULTE AS FONTES</p>
            <h2 id="mission-resources-title">Informações oficiais</h2>
            <p>Confira os dados institucionais diretamente nas páginas responsáveis.</p>
        </div>
        <a href="https://missao.org.br/" target="_blank" rel="noopener noreferrer"><span>PARTIDO</span><strong>Site oficial do Partido Missão ↗</strong><small>Informativos e comunicados do partido</small></a>
        <a href="https://www.tse.jus.br/partidos/partidos-registrados-no-tse/partido-missao" target="_blank" rel="noopener noreferrer"><span>JUSTIÇA ELEITORAL</span><strong>Perfil no TSE ↗</strong><small>Nome, sigla, número de legenda e dados oficiais</small></a>
    </section>
</main>

<footer class="site-footer">
    <div class="wrap footer-top">
        <a class="brand brand-footer" href="/"><img src="/assets/logo.svg" alt="" width="40" height="40"><span>TECH <b>NEWS</b><small>INFORMAÇÃO COM CONTEXTO</small></span></a>
        <p>Tecnologia, economia, Brasil e mundo.<br><strong>Mais informação. Melhores decisões.</strong></p>
        <a class="footer-admin" href="/admin.php">Área editorial ↗</a>
    </div>
    <div class="footer-bottom"><div class="wrap"><span>© <?= date('Y') ?> TECH NEWS</span><span>Cobertura jornalística independente.</span></div></div>
</footer>
</body>
</html>
