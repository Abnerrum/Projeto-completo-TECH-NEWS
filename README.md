# TECH NEWS — Portal de notícias em PHP

Portal editorial responsivo com página pública, busca, categorias, leitura de reportagens e painel administrativo para criar, editar, publicar, salvar como rascunho e excluir notícias.

## Tecnologias

- PHP 8.2 ou superior
- MySQL 8 / MariaDB com extensão `pdo_mysql`
- HTML, CSS e JavaScript sem frameworks externos
- Python não é necessário para executar o portal

## Estrutura do projeto

- `app/bootstrap.php` — inicialização, sessões e cabeçalhos de segurança
- `app/config.php` — configuração por variáveis de ambiente
- `app/database.php` — conexão PDO com MySQL/MariaDB
- `app/functions.php` — validação, proteção CSRF, escape HTML e funções auxiliares
- `bin/create_admin.php` — criação do primeiro administrador via terminal
- `database/schema.sql` — tabelas do portal
- `public/index.php` — página inicial, busca, categorias e leitura de notícias
- `public/admin.php` — login e painel editorial
- `public/assets/` — CSS, JavaScript e logotipo
- `public/.htaccess` — configurações básicas de segurança do Apache

## Recursos de segurança

- Consultas SQL com PDO prepared statements e prepares emulados desativados.
- Senhas armazenadas com `password_hash()` e verificadas com `password_verify()`.
- Proteção CSRF em formulários do painel e logout apenas por POST.
- Escape de saída HTML e texto editorial sem HTML para reduzir riscos de XSS.
- Cabeçalhos CSP, `X-Frame-Options`, `X-Content-Type-Options` e política de permissões.
- Cookies de sessão `HttpOnly`, `SameSite=Lax`, modo estrito e opção `Secure` para HTTPS.
- Renovação do ID da sessão após login e expiração após 30 minutos de inatividade.
- Limite de tentativas de login por IP/e-mail; identificadores armazenados como hashes HMAC.
- Validação de categorias, status, campos, URLs e tamanho do conteúdo.
- Sem upload de arquivos; capas podem usar URL HTTPS ou arquivo que já exista em `public/assets/`.

## Instalação local com XAMPP no Windows

1. Instale XAMPP com PHP 8.2+ e MySQL/MariaDB. Inicie Apache e MySQL.
2. Clone ou baixe este repositório e extraia os arquivos em uma pasta local.
3. No phpMyAdmin, crie um banco chamado `technews` com charset `utf8mb4` e collation `utf8mb4_unicode_ci`.
4. Importe `database/schema.sql` no banco `technews`.
5. Confira as configurações em `app/config.php`. Para um XAMPP recém-instalado, os padrões locais usam usuário `root` sem senha. Se a instalação tiver senha, configure `DB_USER` e `DB_PASSWORD` no ambiente.
6. Abra o terminal na pasta do projeto e execute:

   ```powershell
   php bin/create_admin.php
   ```

   Informe o e-mail e crie uma senha com 12 a 72 bytes.
7. Para iniciar um servidor local de teste:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

8. Acesse:
   - Portal: `http://127.0.0.1:8000`
   - Painel: `http://127.0.0.1:8000/admin.php`

## Configuração de produção

**Não use os valores locais padrão em produção.**

- Configure a raiz pública do servidor para apontar somente para a pasta `public/`.
- Ative HTTPS e defina `SESSION_COOKIE_SECURE=true`.
- Defina `APP_ENV=production`.
- Defina uma chave exclusiva e aleatória em `APP_KEY`, com pelo menos 48 caracteres.
- Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` por variáveis de ambiente; evite usar o usuário root do banco.
- Não exponha os diretórios `app/`, `bin/` e `database/` pela web.
- Mantenha PHP e MySQL atualizados, use senhas fortes, configure logs e faça backups testados.

## Publicação editorial

1. Entre em `/admin.php`.
2. Escolha **Nova reportagem**.
3. Preencha título, categoria, resumo e texto.
4. Opcionalmente informe uma capa HTTPS e uma URL da fonte consultada.
5. Salve como **Rascunho** para revisar ou **Publicada** para exibir no portal.

Antes de publicar notícias, confira as informações em fontes confiáveis, atribua as referências utilizadas e respeite os direitos autorais das imagens.

## Limitações desta versão

- Existe um único nível de administrador; não há perfis separados de repórter e revisor.
- Não há comentários públicos, upload de imagens, newsletter ou integração automática com redes sociais.
- A página pública lista até 24 notícias e o painel mostra as 100 mais recentes.
- A aplicação foi verificada por sintaxe PHP, mas o teste completo com banco MySQL depende de um ambiente com a extensão `pdo_mysql` ativa.
