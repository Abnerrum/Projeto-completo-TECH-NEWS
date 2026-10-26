# TECH NEWS — Portal de notícias em PHP

Projeto completo do portal TECH NEWS, desenvolvido em PHP 8.2+ com MySQL/MariaDB, HTML, CSS e JavaScript.

## Código-fonte

Baixe o arquivo **[technews-portal.zip](./technews-portal.zip)** e extraia a pasta `technews-portal`. O arquivo contém os arquivos do projeto, incluindo:
- `app/` — configuração, banco de dados, funções e inicialização;
- `bin/create_admin.php` — criação do primeiro administrador;
- `database/schema.sql` — estrutura do banco;
- `public/` — página pública, painel administrativo e assets;
- `README.md` — instruções completas de instalação e configuração.

## Requisitos

- PHP 8.2 ou superior;
- MySQL 8 ou MariaDB com a extensão `pdo_mysql`;
- Apache/XAMPP para desenvolvimento local, ou hospedagem PHP compatível.

Python não é necessário para executar o portal.

## Instalação rápida (XAMPP)

1. Extraia o ZIP e copie a pasta `technews-portal` para seu ambiente local.
2. Crie um banco MySQL chamado `technews` usando charset `utf8mb4`.
3. Importe `database/schema.sql` no banco.
4. No terminal, dentro da pasta do projeto, execute `php bin/create_admin.php` e crie as credenciais do administrador.
5. Para iniciar um servidor local de teste: `php -S 127.0.0.1:8000 -t public`.
6. Acesse `http://127.0.0.1:8000` e o painel em `http://127.0.0.1:8000/admin.php`.

As instruções detalhadas estão no README dentro do ZIP.

## Recursos de segurança

O projeto inclui prepared statements PDO, hash de senhas, proteção CSRF, escape de saída HTML, cabeçalhos de segurança, sessões protegidas, limite de tentativas de login e validação dos campos.

## Importante antes de publicar

- Configure HTTPS e a raiz pública do servidor para apontar apenas para a pasta `public/`.
- Configure `APP_ENV=production`, uma `APP_KEY` forte e exclusiva e credenciais de banco por variáveis de ambiente.
- Não use as credenciais padrão locais em produção.
- Revise a configuração e teste a aplicação no ambiente de hospedagem antes de abrir ao público.

## Estrutura do repositório

O arquivo ZIP é o pacote completo e contém os arquivos do código-fonte. Esta primeira publicação guarda o projeto em um arquivo para download; os arquivos ainda não estão descompactados individualmente na árvore do GitHub.
