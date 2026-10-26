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
- `public/index.php` — capa de destaque, busca, categorias, paginação e leitura de notícias
- `public/missao.php` — editoria especial do Partido Missão, com links de imprensa, canais oficiais, aplicativos e serviços
- `public/admin.php` — login e painel editorial
- `public/sitemap.php` — sitemap XML das reportagens publicadas (requer `APP_URL`)
- `public/assets/` — CSS, JavaScript e logotipo
- `public/.htaccess` — configurações básicas de segurança do Apache

## Recursos públicos

- Página inicial com matéria mais recente em destaque, editorias e cartões responsivos.
- Busca por título, resumo e conteúdo, com filtros por categoria e paginação.
- Reportagens com estimativa de leitura, compartilhamento por link/WhatsApp e outras matérias da mesma editoria.
- Metadados Open Graph/Twitter, dados estruturados `NewsArticle` e sitemap XML quando `APP_URL` estiver configurado.
- Página temática `/missao.php` com links para os canais de YouTube, redes sociais, app Android/iOS, filiação, plataforma Criadores, Livro Amarelo, loja, site oficial e TSE. A página identifica a cobertura independente e liga às fontes originais.

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
- Defina `APP_URL` com o endereço HTTPS público do portal, por exemplo `https://seu-dominio.com.br`; isso habilita URLs canônicas, prévias de compartilhamento e o sitemap.
- Defina uma chave exclusiva e aleatória em `APP_KEY`, com pelo menos 48 caracteres.
- Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` por variáveis de ambiente; evite usar o usuário root do banco.
- Não exponha os diretórios `app/`, `bin/` e `database/` pela web.
- Mantenha PHP e MySQL atualizados, use senhas fortes, configure logs e faça backups testados.

## Publicação no Hostinger (PHP + MySQL)

Este repositório inclui regras de raiz em `.htaccess` para encaminhar as URLs públicas para `public/` e bloquear acesso web direto a `app/`, `bin/`, `database/` e `config/`. Isso permite que a raiz do repositório seja implantada em `public_html` sem transformar os diretórios internos em páginas públicas. Use Apache/LiteSpeed com `mod_rewrite` e permita regras via `.htaccess`.

1. Contrate hospedagem PHP com banco MySQL. Para executar o comando de criação do administrador via terminal, escolha um plano com SSH.
2. No hPanel, abra **Sites → Gerenciar → Git** (o nome da seção pode variar) e crie um repositório a partir de `https://github.com/Abnerrum/Projeto-completo-TECH-NEWS.git`, branch `main`. Deixe o caminho de instalação vazio se deseja implantar na raiz `public_html`.
3. Crie um banco MySQL e um usuário pelo painel e importe `database/schema.sql` usando o phpMyAdmin.
4. Pelo Gerenciador de Arquivos, crie `config/local.php` a partir de `config/local.example.php`. Preencha `app_url`, uma `app_key` exclusiva, e os dados exatos do banco fornecidos pela hospedagem. O arquivo `config/local.php` está ignorado pelo Git e não deve ser enviado ao repositório.
5. Gere uma chave exclusiva e aleatória com pelo menos 48 caracteres para `app_key`. Nunca mantenha os marcadores `CHANGE_ME_...` nem use a chave local de desenvolvimento.
6. Ative HTTPS, confirme `session_cookie_secure => true`, configure o domínio e teste primeiro a página inicial, busca, editoria `/missao.php`, sitemap `/sitemap.php` e painel `/admin.php`.
7. Pelo terminal SSH, execute `php bin/create_admin.php` para criar o primeiro administrador. Não deixe scripts de configuração temporários expostos no site.

**Atenção:** o banco começa vazio. O portal será publicado, mas as notícias só aparecerão após criá-las pelo painel editorial. Revise os links externos da editoria Missão antes da publicação e mantenha o conteúdo jornalístico atribuído às fontes originais.

## Hospedagem gratuita no InfinityFree (PHP + MySQL)

O Netlify não executa PHP nem hospeda um banco MySQL para esta aplicação. Para testar o portal sem pagar, o InfinityFree oferece hospedagem PHP/MySQL gratuita com subdomínio e SSL. Confira os limites e as regras do serviço antes de publicar.

1. Crie uma conta em https://www.infinityfree.com/ e selecione um subdomínio gratuito.
2. No painel, crie o site e abra **File Manager**. Entre na pasta `htdocs`.
3. Baixe o repositório em https://github.com/Abnerrum/Projeto-completo-TECH-NEWS/archive/refs/heads/main.zip. Extraia o arquivo no computador e envie **o conteúdo de dentro da pasta do projeto** para `htdocs`, incluindo o arquivo raiz `.htaccess` e as pastas `app/`, `bin/`, `config/`, `database/` e `public/`. Não deixe tudo dentro de uma pasta adicional `Projeto-completo-TECH-NEWS-main/`.
4. No painel do InfinityFree, crie um banco MySQL e importe `database/schema.sql` no phpMyAdmin.
5. No Gerenciador de Arquivos, copie `config/local.example.php` para `config/local.php` e edite o segundo arquivo. Configure:
   - `app_url`: endereço HTTPS do subdomínio, depois que o SSL estiver ativo;
   - `app_env`: `production`;
   - `app_key`: chave aleatória exclusiva de no mínimo 48 caracteres;
   - `setup_key`: outra chave aleatória e secreta, diferente da `app_key`, com no mínimo 48 caracteres;
   - `session_cookie_secure`: `true`, depois de confirmar que o HTTPS funciona;
   - `db.host`, `db.name`, `db.user` e `db.password`: copie exatamente os dados do painel. O host do banco pode não ser `localhost`.
6. Abra `/setup-admin.php` no navegador, informe a `setup_key`, o e-mail do administrador e uma senha de 12 a 72 bytes. A página só permite criar o primeiro administrador e fica indisponível quando já existe uma conta.
7. Assim que a conta for criada, **apague `public/setup-admin.php` do servidor** pelo Gerenciador de Arquivos e remova `setup_key` de `config/local.php`. Não compartilhe esse token nem publique `config/local.php`.
8. Teste a página inicial, `/missao.php`, `/admin.php) e `/sitemap.php`. O banco começa vazio; crie as notícias pelo painel editorial.

**Limitações do plano gratuito:** não conte com SSH, envio de e-mail do servidor ou disponibilidade de nível empresarial. O portal serve como demonstração/portfólio; faça backups externos do banco e dos arquivos. A publicação é manual via Gerenciador de Arquivos/FTP, não um deploy automático do GitHub.

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
- A página pública mostra 12 notícias por página e oferece paginação; o painel mostra as 100 mais recentes.
- A aplicação foi verificada por sintaxe PHP, mas o teste completo com banco MySQL depende de um ambiente com a extensão `pdo_mysql` ativa.
