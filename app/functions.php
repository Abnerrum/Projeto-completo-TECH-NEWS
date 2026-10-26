<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function categories(): array
{
    return [
        'Brasil',
        'Política',
        'Economia',
        'Tecnologia',
        'Inteligência Artificial',
        'Programação',
        'Cibersegurança',
        'Negócios',
        'Mundo',
    ];
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = $_POST['_csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('Sua sessão expirou ou o formulário não é válido. Volte à página e tente novamente.');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

function is_admin(): bool
{
    if (!isset($_SESSION['admin_id'], $_SESSION['admin_last_seen'])) {
        return false;
    }

    if (!is_numeric($_SESSION['admin_last_seen']) || time() - (int) $_SESSION['admin_last_seen'] > 1800) {
        unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_last_seen']);
        return false;
    }

    $_SESSION['admin_last_seen'] = time();
    return true;
}

function require_admin(): void
{
    if (!is_admin()) {
        flash_set('error', 'Entre na sua conta para acessar o painel.');
        redirect('admin.php?acao=login');
    }
}

function slugify(string $value): string
{
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated)) {
            $value = $transliterated;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = substr($value, 0, 180);
    return trim($value, '-') ?: 'noticia';
}

/** Returns a normalized safe image URL, or null for an empty value. */
function safe_image_url(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > 2048) {
        throw new InvalidArgumentException('O endereço da imagem é muito longo.');
    }

    // Local images must stay under the public assets directory.
    if (str_starts_with($value, '/assets/')) {
        if (str_contains($value, '..') || str_contains($value, "\\")) {
            throw new InvalidArgumentException('O caminho da imagem não é válido.');
        }
        return $value;
    }

    $parts = parse_url($value);
    if (!filter_var($value, FILTER_VALIDATE_URL)
        || !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])) {
        throw new InvalidArgumentException('Use uma URL HTTPS válida ou um caminho dentro de /assets/.');
    }

    return $value;
}

function safe_https_url(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > 2048) {
        throw new InvalidArgumentException('O endereço da fonte é muito longo.');
    }
    $parts = parse_url($value);
    if (!filter_var($value, FILTER_VALIDATE_URL)
        || !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])) {
        throw new InvalidArgumentException('Use uma URL HTTPS válida para a fonte consultada.');
    }
    return $value;
}

function format_date(?string $value): string
{
    if (!$value) {
        return 'Rascunho';
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y · H:i');
    } catch (Throwable) {
        return 'Data indisponível';
    }
}

function body_as_paragraphs(string $body): string
{
    // Escape before adding line-break tags: article content is intentionally plain text.
    return nl2br(e($body), false);
}

function request_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash_hmac('sha256', $ip, $GLOBALS['technews_config']['app_key']);
}

function email_hash(string $email): string
{
    return hash_hmac('sha256', strtolower(trim($email)), $GLOBALS['technews_config']['app_key']);
}
