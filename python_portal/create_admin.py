"""Cria administrador TECH NEWS no banco MySQL existente."""
from getpass import getpass
from sqlalchemy import text
from app import db

def main():
    import bcrypt
    email = input("E-mail do administrador: ").strip().lower()
    password = getpass("Senha forte (mínimo 12 caracteres): ")
    if "@" not in email or len(email) > 190 or not 12 <= len(password.encode()) <= 72:
        raise SystemExit("Informe um e-mail válido e senha de 12 a 72 bytes.")
    secure_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt(rounds=12)).decode()
    with db() as conn:
        count = conn.execute(text("SELECT COUNT(*) FROM admins WHERE email=:email"), {"email": email}).scalar_one()
        if count:
            raise SystemExit("Este administrador já existe.")
        conn.execute(text("INSERT INTO admins(email,password_hash) VALUES(:email,:password_hash)"),
                     {"email": email, "password_hash": secure_hash})
    print("Administrador criado com sucesso.")

if __name__ == "__main__":
    main()
