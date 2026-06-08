<?php
// ============================================================
// Configuration base de données / application
// ============================================================

/**
 * Charger un fichier .env simple (KEY=VALUE), si présent.
 * (Sans dépendances, compatible WAMP)
 */
function loadDotEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $val = trim($val, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

loadDotEnv(__DIR__ . '/../.env');

/** Lit une clé depuis .env (prioritaire sur getenv pour éviter chemins sandbox invalides). */
function dotEnvValue(string $key, ?string $default = null): ?string {
    $path = __DIR__ . '/../.env';
    if (!is_file($path)) {
        return getenv($key) ?: $default;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        if (trim(substr($line, 0, $pos)) !== $key) continue;
        $val = trim(substr($line, $pos + 1));
        return trim($val, "\"'") ?: $default;
    }
    return getenv($key) ?: $default;
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'fake_news_platform');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('APP_NAME', getenv('APP_NAME') ?: 'Détection du Fake News');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/fake-news-platform-b');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');

// Logos - Chemin depuis la racine du projet
define('APP_LOGO_PATH', getenv('APP_LOGO_PATH') ?: 'images/logo-fake-news.png');

// ============================================================
// Facebook Graph API (optionnel) — valeurs via .env ou api_settings
// ============================================================
define('FACEBOOK_APP_ID', getenv('FACEBOOK_APP_ID') ?: '');
define('FACEBOOK_APP_SECRET', getenv('FACEBOOK_APP_SECRET') ?: '');
define('FACEBOOK_ACCESS_TOKEN', getenv('FACEBOOK_ACCESS_TOKEN') ?: '');
define('FACEBOOK_GRAPH_VERSION', getenv('FACEBOOK_GRAPH_VERSION') ?: 'v25.0');

// Python / IA (GBERT Hassaniya)
define('PYTHON_EXECUTABLE', getenv('PYTHON_EXECUTABLE') ?: (PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3'));
define('AI_MODEL', getenv('AI_MODEL') ?: 'cardiffnlp/twitter-xlm-roberta-base-sentiment');
define('GBERT_MODEL_PATH', getenv('GBERT_MODEL_PATH') ?: '');
define('AI_SERVER_URL', getenv('AI_SERVER_URL') ?: 'http://127.0.0.1:8765');
define('AI_AUTO_ANALYZE', getenv('AI_AUTO_ANALYZE') !== 'false');
define('PLAYWRIGHT_BROWSERS_PATH', dotEnvValue('PLAYWRIGHT_BROWSERS_PATH', '/home/kali/gbert_project/playwright-browsers'));

/** Variables d'environnement pour les scripts Python (extracteur, analyse). */
function getPythonProcessEnv(): array {
    $env = [];
    foreach ($_SERVER as $k => $v) {
        if (is_string($k) && is_string($v) && !str_starts_with($k, 'HTTP_')) {
            $env[$k] = $v;
        }
    }
    $forced = [
        'PLAYWRIGHT_BROWSERS_PATH' => PLAYWRIGHT_BROWSERS_PATH,
        'DB_HOST' => DB_HOST,
        'DB_USER' => DB_USER,
        'DB_PASS' => DB_PASS,
        'DB_NAME' => DB_NAME,
        'PYTHONIOENCODING' => 'utf-8',
    ];
    foreach ($forced as $k => $v) {
        $env[$k] = $v;
        putenv($k . '=' . $v);
    }
    return $env;
}

/** Chemin vers l'interpréteur Python (venv recommandé pour torch/transformers). */
function getPythonExecutable(): string {
    $bin = PYTHON_EXECUTABLE;
    if ($bin !== 'python3' && $bin !== 'py' && $bin !== 'python' && !is_file($bin)) {
        return PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
    }
    return $bin;
}

// ============================================================
// Connexion PDO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensurePostAuthorColumn($pdo);
            ensurePostMetadataColumns($pdo);
            ensurePostCommentsTable($pdo);
            ensureHumanReviewColumns($pdo);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connexion DB échouée: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/** Ajoute facebook_posts.author_name si la base a été créée avant cette colonne. */
function ensurePostAuthorColumn(?PDO $pdo = null): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = $pdo ?? getDB();
    try {
        $pdo->exec("ALTER TABLE facebook_posts ADD COLUMN author_name VARCHAR(255) NULL AFTER account_id");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false && strpos($e->getMessage(), '1060') === false) {
            // ignore silently for production
        }
    }
    try {
        $pdo->exec("CREATE INDEX idx_author_name ON facebook_posts (author_name)");
    } catch (PDOException $e) {
        // index may already exist
    }
}

/** Table post_comments — texte des commentaires + analyse GBERT. */
function ensurePostCommentsTable(?PDO $pdo = null): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = $pdo ?? getDB();
    try {
        $pdo->exec("
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
                INDEX idx_post_id (post_id),
                INDEX idx_is_analyzed (is_analyzed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        try { $pdo->exec("ALTER TABLE post_comments ADD COLUMN analyzed_at DATETIME NULL"); } catch (PDOException $e) { /* exists */ }
    } catch (PDOException $e) { /* ignore */ }
}

/** Colonnes link_status et content_type pour le cahier des charges. */
function ensurePostMetadataColumns(?PDO $pdo = null): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = $pdo ?? getDB();
    try {
        $pdo->exec("ALTER TABLE facebook_posts ADD COLUMN content_type ENUM('text','image','text_image') DEFAULT 'text' AFTER image_url");
    } catch (PDOException $e) { /* exists */ }
    try {
        $pdo->exec("ALTER TABLE facebook_posts ADD COLUMN link_status ENUM('active','inaccessible','deleted','unknown') DEFAULT 'unknown' AFTER fb_post_url");
    } catch (PDOException $e) { /* exists */ }
}

/** Colonnes validation humaine (cahier des charges — aide à l'analyse). */
function ensureHumanReviewColumns(?PDO $pdo = null): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = $pdo ?? getDB();
    $alters = [
        "ALTER TABLE ai_analysis ADD COLUMN human_category VARCHAR(50) NULL AFTER category",
        "ALTER TABLE ai_analysis ADD COLUMN ai_category_original VARCHAR(50) NULL AFTER human_category",
    ];
    foreach ($alters as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // column exists
        }
    }
}

// ============================================================
// Auto-installation de la base de données
// ============================================================
function autoInstall(): void {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Vérifier si les tables existent
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            $pdo->exec($sql);
        }
    } catch (PDOException $e) {
        // Silencieux en production
    }
}
