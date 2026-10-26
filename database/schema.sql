-- Create the database separately, for example: CREATE DATABASE technews CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Import this file into the technews database.

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    category VARCHAR(60) NOT NULL,
    excerpt VARCHAR(320) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    cover_url VARCHAR(2048) NULL,
    source_url VARCHAR(2048) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_status_published (status, published_at),
    KEY idx_news_category_published (category, status, published_at),
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash CHAR(64) NOT NULL,
    email_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_ip_time (ip_hash, attempted_at),
    KEY idx_login_email_time (email_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
