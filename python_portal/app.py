"""TECH NEWS — versão Python/FastAPI, usando o banco MySQL já existente."""
import hashlib
import os
import re
import secrets
import unicodedata
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

import bcrypt
from dotenv import load_dotenv
from fastapi import FastAPI, Form, HTTPException, Request
from fastapi.responses import RedirectResponse, Response
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from sqlalchemy import create_engine, text
from sqlalchemy.engine import URL
from starlette.middleware.sessions import SessionMiddleware

ROOT = Path(__file__).resolve().parent
load_dotenv(ROOT / ".env")
SECRET = os.getenv("SECRET_KEY", "")
if len(SECRET) < 48:
    raise RuntimeError("Defina SECRET_KEY aleatória com pelo menos 48 caracteres no .env.")
APP_URL = os.getenv("APP_URL", "").rstrip("/")
DB_URL = URL.create(
    "mysql+pymysql",
    username=os.getenv("DB_USER", "root"),
    password=os.getenv("DB_PASSWORD", ""),
    host=os.getenv("DB_HOST", "127.0.0.1"),
    port=int(os.getenv("DB_PORT", "3306")),
    database=os.getenv("DB_NAME", "technews"),
)
engine = create_engine(DB_URL, pool_pre_ping=True, pool_recycle=1800)
app = FastAPI(title="TECH NEWS", docs_url=None, redoc_url=None, openapi_url=None)
app.add_middleware(
    SessionMiddleware, secret_key=SECRET, same_site="lax", https_only=os.getenv("APP_ENV") == "production",
    max_age=1800,
)
app.mount("/assets", StaticFiles(directory=ROOT.parent / "public" / "assets"), name="assets")
tpl = Jinja2Templates(directory=str(ROOT / "templates"))
CATEGORIES = ["Brasil", "Política", "Economia", "Tecnologia", "Inteligência Artificial",
              "Programação", "Cibersegurança", "Negócios", "Mundo"]
PER_PAGE = 12


@contextmanager
def db():
    with engine.begin() as conn:
        yield conn


def ctx(request, **kwargs):
    return {"request": request, "categories": CATEGORIES, "app_url": APP_URL, **kwargs}


def csrf(request):
    if not request.session.get("csrf"):
        request.session["csrf"] = secrets.token_hex(32)
    return request.session["csrf"]


def verify_csrf(request, token):
    if not isinstance(token, str) or not secrets.compare_digest(csrf(request), token):
        raise HTTPException(403, "Formulário inválido. Recarregue a página.")


def authorized(request):
    uid = request.session.get("admin_id")
    last = request.session.get("last_seen", 0)
    if not uid or datetime.now(timezone.utc).timestamp() - last > 1800:
        request.session.clear()
        return False
    request.session["last_seen"] = datetime.now(timezone.utc).timestamp()
    return True


def require_admin(request):
    if not authorized(request):
        raise HTTPException(403, "Entre no painel administrativo.")


def redirect(to):
    return RedirectResponse(to, status_code=303)


def slugify(value):
    ascii_title = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", ascii_title.lower()).strip("-")[:170] or "noticia"


def cover(value):
    value = (value or "").strip()
    if not value:
        return None
    parsed = urlparse(value)
    if value.startswith("/assets/") and not value.startswith("//"):
        return value
    if parsed.scheme == "https" and parsed.hostname and not parsed.username and not parsed.password:
        return value
    raise HTTPException(400, "A capa deve usar HTTPS ou /assets/.")


def published_where():
    return "n.status = 'published' AND n.published_at IS NOT NULL AND n.published_at <= UTC_TIMESTAMP()"


def list_news(category="", query="", page=1):
    where = [published_where()]
    params = {}
    if category in CATEGORIES:
        where.append("n.category = :category")
        params["category"] = category
    else:
        category = ""
    if query:
        where.append("(n.title LIKE :q OR n.excerpt LIKE :q OR n.body LIKE :q)")
        escaped = query.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_")
        params["q"] = "%" + escaped + "%"
    clause = " AND ".join(where)
    with db() as conn:
        total = conn.execute(text("SELECT COUNT(*) FROM news n WHERE " + clause), params).scalar_one()
        pages = max(1, (total + PER_PAGE - 1) // PER_PAGE)
        page = min(max(1, page), pages)
        params.update(limit=PER_PAGE, offset=(page - 1) * PER_PAGE)
        rows = conn.execute(text(
            "SELECT n.id,n.title,n.slug,n.category,n.excerpt,n.cover_url,n.published_at "
            "FROM news n WHERE " + clause + " ORDER BY n.published_at DESC,n.id DESC LIMIT :limit OFFSET :offset"
        ), params).mappings().all()
    return rows, page, pages, category


@app.get("/")
def home(request: Request, categoria: str = "", q: str = "", page: int = 1, artigo: str = ""):
    if artigo:
        return story(request, artigo)
    q = q.strip()[:100]
    rows, page, pages, categoria = list_news(categoria, q, page)
    return tpl.TemplateResponse("index.html", ctx(
        request, rows=rows, page=page, pages=pages, categoria=categoria, q=q,
        title="TECH NEWS — Notícias de tecnologia em Goiás"))


@app.get("/noticia/{slug}")
def story(request: Request, slug: str):
    if not re.fullmatch(r"[a-z0-9-]{1,220}", slug):
        raise HTTPException(404, "Notícia não encontrada.")
    with db() as conn:
        item = conn.execute(text(
            "SELECT n.*,a.email AS author_email FROM news n JOIN admins a ON a.id=n.author_id "
            "WHERE n.slug=:slug AND " + published_where() + " LIMIT 1"
        ), {"slug": slug}).mappings().first()
        if not item:
            raise HTTPException(404, "Notícia não encontrada.")
        related = conn.execute(text(
            "SELECT title,slug,excerpt,cover_url FROM news n WHERE n.category=:category AND n.id<>:id AND "
            + published_where() + " ORDER BY n.published_at DESC LIMIT 3"
        ), {"category": item["category"], "id": item["id"]}).mappings().all()
    return tpl.TemplateResponse("article.html", ctx(
        request, item=item, related=related, title=item["title"] + " | TECH NEWS"))


@app.get("/admin")
def admin(request: Request, edit: int = 0):
    if not authorized(request):
        return tpl.TemplateResponse("admin.html", ctx(request, logged=False, token=csrf(request), error=""))
    with db() as conn:
        rows = conn.execute(text(
            "SELECT id,title,slug,status,category,updated_at FROM news ORDER BY updated_at DESC LIMIT 100"
        )).mappings().all()
        item = conn.execute(text("SELECT * FROM news WHERE id=:id"), {"id": edit}).mappings().first() if edit else None
    return tpl.TemplateResponse("admin.html", ctx(
        request, logged=True, token=csrf(request), rows=rows, item=item, error=""))


@app.post("/admin/login")
def login(request: Request, email: str = Form(...), password: str = Form(...), _csrf: str = Form(...)):
    verify_csrf(request, _csrf)
    email = email.strip().lower()[:190]
    ip = (request.client.host if request.client else "unknown")
    ip_hash = hashlib.sha256((SECRET + ip).encode()).hexdigest()
    email_hash = hashlib.sha256((SECRET + email).encode()).hexdigest()
    with db() as conn:
        conn.execute(text("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"))
        attempts = conn.execute(text(
            "SELECT COUNT(*) FROM login_attempts WHERE (ip_hash=:ip OR email_hash=:email) "
            "AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
        ), {"ip": ip_hash, "email": email_hash}).scalar_one()
        admin_row = None if attempts >= 5 else conn.execute(
            text("SELECT id,email,password_hash FROM admins WHERE email=:email"), {"email": email}
        ).mappings().first()
        ok = False
        if admin_row and len(password.encode()) <= 72:
            hash_value = admin_row["password_hash"].encode().replace(b"$2y$", b"$2b$", 1)
            try:
                ok = bcrypt.checkpw(password.encode(), hash_value)
            except ValueError:
                ok = False
        if ok:
            conn.execute(text("DELETE FROM login_attempts WHERE ip_hash=:ip OR email_hash=:email"),
                         {"ip": ip_hash, "email": email_hash})
            request.session.clear()
            request.session.update(admin_id=admin_row["id"], last_seen=datetime.now(timezone.utc).timestamp())
            return redirect("/admin")
        if attempts < 5:
            conn.execute(text("INSERT INTO login_attempts(ip_hash,email_hash) VALUES(:ip,:email)"),
                         {"ip": ip_hash, "email": email_hash})
    return tpl.TemplateResponse("admin.html", ctx(
        request, logged=False, token=csrf(request), error="Acesso temporariamente bloqueado ou credenciais inválidas."
    ), status_code=401)


@app.post("/admin/logout")
def logout(request: Request, _csrf: str = Form(...)):
    verify_csrf(request, _csrf)
    request.session.clear()
    return redirect("/admin")


@app.post("/admin/save")
def save(request: Request, _csrf: str = Form(...), article_id: int = Form(0), title: str = Form(...),
         category: str = Form(...), excerpt: str = Form(...), body: str = Form(...),
         cover_url: str = Form(""), source_url: str = Form(""), status: str = Form("draft")):
    require_admin(request)
    verify_csrf(request, _csrf)
    title, excerpt, body = title.strip(), excerpt.strip(), body.strip()
    if not 3 <= len(title) <= 180 or not 10 <= len(excerpt) <= 320 or not 20 <= len(body) <= 150000:
        raise HTTPException(400, "Confira os limites do título, resumo e texto.")
    if category not in CATEGORIES or status not in ("draft", "published"):
        raise HTTPException(400, "Categoria ou status inválido.")
    cover_url = cover(cover_url)
    source_url = cover(source_url)
    with db() as conn:
        old = conn.execute(text("SELECT id,slug,status,published_at FROM news WHERE id=:id"),
                           {"id": article_id}).mappings().first() if article_id else None
        if article_id and not old:
            raise HTTPException(404, "Reportagem não encontrada.")
        slug = old["slug"] if old else slugify(title) + "-" + secrets.token_hex(3)
        pub = old["published_at"] if old and old["published_at"] else None
        if status == "published" and not pub:
            pub = datetime.now(timezone.utc).replace(tzinfo=None)
        if status == "draft":
            pub = None
        data = dict(title=title, category=category, excerpt=excerpt, body=body, slug=slug,
                    cover=cover_url, source=source_url, status=status, published=pub)
        if old:
            conn.execute(text(
                "UPDATE news SET title=:title,category=:category,excerpt=:excerpt,body=:body,"
                "cover_url=:cover,source_url=:source,status=:status,published_at=:published "
                "WHERE id=:id"
            ), {**data, "id": article_id})
        else:
            conn.execute(text(
                "INSERT INTO news(title,slug,category,excerpt,body,cover_url,source_url,status,published_at,author_id) "
                "VALUES(:title,:slug,:category,:excerpt,:body,:cover,:source,:status,:published,:author)"
            ), {**data, "author": request.session["admin_id"]})
    return redirect("/admin")


@app.post("/admin/delete")
def delete(request: Request, _csrf: str = Form(...), article_id: int = Form(...)):
    require_admin(request)
    verify_csrf(request, _csrf)
    with db() as conn:
        conn.execute(text("DELETE FROM news WHERE id=:id"), {"id": article_id})
    return redirect("/admin")


@app.get("/sitemap.xml")
def sitemap():
    if not APP_URL.startswith("https://"):
        raise HTTPException(404)
    with db() as conn:
        rows = conn.execute(text("SELECT slug FROM news n WHERE " + published_where())).scalars().all()
    from xml.sax.saxutils import escape
    links = "".join("<url><loc>" + escape(APP_URL + "/noticia/" + s) + "</loc></url>" for s in rows)
    return Response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                    + "<url><loc>" + escape(APP_URL) + "</loc></url>" + links + "</urlset>",
                    media_type="application/xml")


@app.get("/health")
def health():
    with db() as conn:
        conn.execute(text("SELECT 1"))
    return {"status": "ok"}
