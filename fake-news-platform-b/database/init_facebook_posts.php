<?php
/**
 * database/init_facebook_posts.php
 * ================================================================
 * Script d'initialisation des tables pour le système OSINT
 * ================================================================
 */

require_once __DIR__ . '/../includes/config.php';

$db = getDB();
$success = true;

echo "====================================================================\n";
echo "Initialisation des tables OSINT Facebook\n";
echo "====================================================================\n\n";

// ────────────────────────────────────────────────────────────────
// TABLE: facebook_posts
// ────────────────────────────────────────────────────────────────

echo "1. Création/vérification de 'facebook_posts'...\n";

$sql_posts = "
CREATE TABLE IF NOT EXISTS facebook_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_post_url VARCHAR(1000) NOT NULL UNIQUE,
    author_name VARCHAR(255),
    content LONGTEXT NOT NULL,
    image_url VARCHAR(1000),
    published_at DATETIME,
    extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_analyzed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_url (fb_post_url),
    INDEX idx_author (author_name),
    INDEX idx_analyzed (is_analyzed),
    INDEX idx_extracted (extracted_at)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql_posts);
    echo "   ✓ Table 'facebook_posts' prête\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    $success = false;
}

// ────────────────────────────────────────────────────────────────
// TABLE: ai_analysis
// ────────────────────────────────────────────────────────────────

echo "\n2. Création/vérification de 'ai_analysis'...\n";

$sql_analysis = "
CREATE TABLE IF NOT EXISTS ai_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    category VARCHAR(50),
    confidence_score FLOAT DEFAULT 0,
    risk_level VARCHAR(20) DEFAULT 'low',
    model_used VARCHAR(100),
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_post_analysis (post_id),
    INDEX idx_category (category),
    INDEX idx_risk (risk_level)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql_analysis);
    echo "   ✓ Table 'ai_analysis' prête\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    $success = false;
}

// ────────────────────────────────────────────────────────────────
// TABLE: osint_reports (optionnel)
// ────────────────────────────────────────────────────────────────

echo "\n3. Création/vérification de 'osint_reports'...\n";

$sql_reports = "
CREATE TABLE IF NOT EXISTS osint_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active',
    INDEX idx_created (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql_reports);
    echo "   ✓ Table 'osint_reports' prête\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    $success = false;
}

// ────────────────────────────────────────────────────────────────
// TABLE: report_posts (liaison)
// ────────────────────────────────────────────────────────────────

echo "\n4. Création/vérification de 'report_posts'...\n";

$sql_report_posts = "
CREATE TABLE IF NOT EXISTS report_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    post_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES osint_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_report_post (report_id, post_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql_report_posts);
    echo "   ✓ Table 'report_posts' prête\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    $success = false;
}

// ────────────────────────────────────────────────────────────────
// RÉSUMÉ
// ────────────────────────────────────────────────────────────────

echo "\n====================================================================\n";

if ($success) {
    echo "✓ Initialisation complètée avec succès!\n\n";
    echo "Tables créées/vérifiées:\n";
    echo "  • facebook_posts       — Publications Facebook extraites\n";
    echo "  • ai_analysis          — Analyses IA par catégorie\n";
    echo "  • osint_reports        — Rapports OSINT\n";
    echo "  • report_posts         — Liaison posts-rapports\n";
    echo "\n";
    echo "Vous pouvez maintenant utiliser le système OSINT!\n";
} else {
    echo "✗ Certaines tables n'ont pas pu être créées.\n";
    echo "Vérifiez les permissions et essayez à nouveau.\n";
}

echo "====================================================================\n";

exit($success ? 0 : 1);
?>
