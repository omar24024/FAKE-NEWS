-- ============================================================
-- DETECTION DU FAKE NEWS — Base de données MySQL
-- Importer via phpMyAdmin ou : mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS fake_news_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fake_news_platform;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin','analyst','viewer') DEFAULT 'analyst',
    avatar VARCHAR(500),
    last_login DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: facebook_accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS facebook_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_id VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type ENUM('page','profile','group') DEFAULT 'page',
    category VARCHAR(100),
    followers_count INT DEFAULT 0,
    profile_picture VARCHAR(500),
    fb_url VARCHAR(500),
    is_monitored TINYINT(1) DEFAULT 1,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_fb_id (fb_id),
    INDEX idx_risk_level (risk_level)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: facebook_posts
-- ============================================================
CREATE TABLE IF NOT EXISTS facebook_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_post_id VARCHAR(200) NOT NULL UNIQUE,
    account_id INT NOT NULL,
    author_name VARCHAR(255),
    content TEXT,
    image_url VARCHAR(1000),
    local_image VARCHAR(500),
    fb_post_url VARCHAR(1000),
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    published_at DATETIME,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_analyzed TINYINT(1) DEFAULT 0,
    INDEX idx_fb_post_id (fb_post_id),
    INDEX idx_author_name (author_name),
    INDEX idx_account_id (account_id),
    INDEX idx_published_at (published_at),
    INDEX idx_is_analyzed (is_analyzed),
    FOREIGN KEY (account_id) REFERENCES facebook_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: ai_analysis
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    category ENUM('fake_news','disinformation','hate_speech','misinformation','propaganda','violence','cyberbullying','reliable') NOT NULL,
    confidence_score DECIMAL(5,2) NOT NULL,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
    model_used VARCHAR(100) DEFAULT 'arabert-multilingual',
    analyzed_by INT,
    analysis_notes TEXT,
    manual_review TINYINT(1) DEFAULT 0,
    reviewed_by INT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (analyzed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_post_id (post_id),
    INDEX idx_post_id (post_id),
    INDEX idx_category (category),
    INDEX idx_confidence (confidence_score)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: post_comments (texte + analyse GBERT)
-- ============================================================
CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    author_name VARCHAR(255) NULL,
    content TEXT NOT NULL,
    category VARCHAR(50) NULL,
    confidence_score DECIMAL(5,2) NULL,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
    model_used VARCHAR(100) NULL,
    is_analyzed TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    analyzed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_is_analyzed (is_analyzed)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: ai_detection_rules
-- Configurable keywords and detection rules for AI analysis
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_detection_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('fake_news','disinformation','hate_speech','misinformation','propaganda','violence','cyberbullying','neutral_indicators') NOT NULL,
    keyword VARCHAR(500) NOT NULL,
    weight DECIMAL(4,3) DEFAULT 0.150,
    is_active TINYINT(1) DEFAULT 1,
    rule_type ENUM('keyword','phrase','regex') DEFAULT 'keyword',
    priority INT DEFAULT 1,
    description VARCHAR(500),
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_rule (category, keyword),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_rule_type (rule_type)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: detected_keywords
-- ============================================================
CREATE TABLE IF NOT EXISTS detected_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id INT NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    weight DECIMAL(4,3) DEFAULT 0.500,
    category ENUM('fake_news','disinformation','hate_speech','misinformation','propaganda','violence','cyberbullying','reliable','neutral') DEFAULT 'neutral',
    FOREIGN KEY (analysis_id) REFERENCES ai_analysis(id) ON DELETE CASCADE,
    INDEX idx_analysis_id (analysis_id),
    INDEX idx_keyword (keyword)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: legal_references
-- ============================================================
CREATE TABLE IF NOT EXISTS legal_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    article_text TEXT,
    category ENUM('fake_news','disinformation','hate_speech','cyber','general') DEFAULT 'general',
    source VARCHAR(255),
    year INT,
    country VARCHAR(100) DEFAULT 'Mauritanie',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: reports
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    type ENUM('daily','weekly','monthly','custom','alert') DEFAULT 'custom',
    post_id INT,
    account_id INT,
    generated_by INT,
    content JSON,
    file_path VARCHAR(500),
    format ENUM('pdf','csv','html') DEFAULT 'pdf',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES facebook_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('alert','info','warning','success') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    post_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: api_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS api_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: facebook_posts_scraper (Posts scrapés avec Playwright)
-- ============================================================
CREATE TABLE IF NOT EXISTS facebook_posts_scraper (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_post_id VARCHAR(64) NOT NULL UNIQUE,
    author VARCHAR(255) NOT NULL,
    text LONGTEXT NOT NULL,
    image_url VARCHAR(1000),
    post_url VARCHAR(1000),
    published_at DATETIME,
    scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_analyzed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fb_post_id (fb_post_id),
    INDEX idx_author (author),
    INDEX idx_published_at (published_at),
    INDEX idx_is_analyzed (is_analyzed),
    INDEX idx_scraped_at (scraped_at)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: scraper_sessions (Sessions de scraping)
-- ============================================================
CREATE TABLE IF NOT EXISTS scraper_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    scrolls INT DEFAULT 5,
    status ENUM('running','completed','failed') DEFAULT 'running',
    posts_found INT DEFAULT 0,
    posts_saved INT DEFAULT 0,
    errors INT DEFAULT 0,
    output LONGTEXT,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES PAR DÉFAUT
-- ============================================================

-- Admin par défaut (password: admin123)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@fakenews.mr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uivHy/Ivq', 'Administrateur Système', 'admin'),
('analyste1', 'analyste@fakenews.mr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uivHy/Ivq', 'Ahmed Ould Brahim', 'analyst');

-- Références légales mauritaniennes
INSERT INTO legal_references (reference_code, title, description, category, source, year) VALUES
('LOI-2022-17', 'Loi n° 2022-17 relative à la cybercriminalité', 'Loi portant sur les infractions liées aux technologies de l\'information et de la communication en Mauritanie', 'cyber', 'Journal Officiel Mauritanien', 2022),
('ART-48-CODE-PENAL', 'Article 48 du Code Pénal Mauritanien', 'Disposition pénale relative à la diffusion de fausses informations et à la désinformation publique', 'fake_news', 'Code Pénal Mauritanien', 2001),
('LOI-2018-25', 'Loi n° 2018-25 sur la liberté de la presse', 'Réglementation de la presse et des médias en Mauritanie, incluant les médias numériques', 'general', 'Ministère de la Communication', 2018),
('ART-306-CODE-PENAL', 'Article 306 — Incitation à la haine', 'Disposition pénale relative à l\'incitation à la haine raciale, ethnique ou religieuse', 'hate_speech', 'Code Pénal Mauritanien', 2001),
('DECRET-2020-047', 'Décret n° 2020-047 sur les contenus numériques', 'Réglementation des contenus publiés sur les réseaux sociaux et plateformes numériques', 'general', 'Présidence de la République', 2020);

-- Paramètres API
INSERT INTO api_settings (setting_key, setting_value) VALUES
('fb_app_id', ''),
('fb_app_secret', ''),
('fb_access_token', ''),
('ai_model', 'arabert-multilingual'),
('auto_sync_interval', '3600'),
('confidence_threshold', '70'),
('alert_threshold', '85');
