<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$baseUrl = rtrim((string) ($GLOBALS['technews_config']['app_url'] ?? ''), '/');
$parts = $baseUrl !== '' ? parse_url($baseUrl) : false;
if (!is_array($parts)
    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
    || empty($parts['host'])
    || isset($parts['user'])
    || isset($parts['pass'])) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Configure APP_URL com o endereço HTTPS público do TECH NEWS para gerar o sitemap.');
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

$homeUrl = $baseUrl . '/';
echo "  <url><loc>", htmlspecialchars($homeUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8'), "</loc></url>\n";

$statement = db()->query("SELECT slug, published_at FROM news WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP() ORDER BY published_at DESC");
while ($row = $statement->fetch()) {
    $url = $baseUrl . '/?artigo=' . rawurlencode((string) $row['slug']);
    $lastmod = '';
    try {
        $lastmodDate = new DateTimeImmutable((string) $row['published_at'], new DateTimeZone('UTC'));
        $lastmod = '<lastmod>' . $lastmodDate->format('Y-m-d\TH:i:s\Z') . '</lastmod>';
    } catch (Throwable) {
        $lastmod = '';
    }
    echo "  <url><loc>", htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8'), "</loc>", $lastmod, "</url>\n";
}
echo "</urlset>\n";
