# TECH NEWS — Python + FastAPI

Esta versão usa FastAPI, Jinja2 e o MySQL já existente. O sistema em PHP permanece preservado na branch main.

## Recursos

- Página inicial, pesquisa, categorias e paginação de notícias.
- Leitura de reportagens, artigos relacionados e capas.
- Painel editorial com login, criação, edição, rascunhos, publicação e exclusão.
- Banco existente: admins, news, login_attempts (database/schema.sql).
- CSRF, sessões assinadas, limite de login, SQL parametrizado e bcrypt compatível com hashes PHP.
- Sitemap, compartilhamento e assets atuais.

## Instalação no Windows

Requer Python 3.12+ e MySQL/MariaDB.

1. Entre na branch migracao-python-fastapi e na pasta python_portal.
2. Crie ambiente virtual: python -m venv .venv
3. Ative: .\.venv\Scripts\Activate.ps1
4. Instale: pip install -r requirements.txt
5. Copie .env.example para .env e preencha SECRET_KEY (48 caracteres aleatórios no mínimo) e DB_*.
6. Importe database/schema.sql somente se o banco ainda não existe. Faça backup antes de usar o banco do site em PHP.
7. Execute: python create_admin.py
8. Inicie: uvicorn app:app --reload --host 127.0.0.1 --port 8000

Portal: http://127.0.0.1:8000/
Painel: http://127.0.0.1:8000/admin
Health: http://127.0.0.1:8000/health

## Implantação

Exige hospedagem capaz de executar Python/ASGI. Hospedagens apenas PHP, como InfinityFree, não executam FastAPI. Em produção, configure HTTPS, APP_ENV=production, APP_URL HTTPS, SECRET_KEY exclusiva, banco com usuário limitado, backups e servidor ASGI atrás de proxy HTTPS. Nunca publique o arquivo .env.

## Pendências antes de substituir PHP

- Reavaliar a página específica missao.php: não migrada nesta etapa.
- Testar todas as rotas, persistência, segurança e responsividade.
- Completar metadados SEO/Open Graph/NewsArticle e os recursos legados.
- Fazer homologação, plano de rollback e escolher hospedagem Python.
- Adaptar a linha editorial do novo portal ao foco em Goiás.

**Esta é uma implementação inicial em branch separada, ainda não implantada nem homologada.**
