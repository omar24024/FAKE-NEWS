<?php
/**
 * database/migrate_to_osint.php
 * ================================================================
 * Migration: Transform old scraper schema to new OSINT schema
 * Adds missing columns and ensures compatibility
 * ================================================================
 */

require_once __DIR__ . '/../includes/config.php';

$db = getDB();
$success = true;

echo "====================================================================\n";
echo "Migration Schema: Old Scraper → New OSINT System\n";
echo "====================================================================\n\n";

// ────────────────────────────────────────────────────────────────
// STEP 1: ADD MISSING COLUMNS TO facebook_posts
// ────────────────────────────────────────────────────────────────

echo "1. Ajout des colonnes manquantes à 'facebook_posts'...\n";

$migrations = [
    "ADD COLUMN author_name VARCHAR(255) AFTER fb_post_url" => 
        "Colonne author_name",
    
    "ADD COLUMN extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER published_at" => 
        "Colonne extracted_at",
    
    "ADD INDEX idx_extracted (extracted_at)" =>
        "Index extracted_at",
];

foreach ($migrations as $alter_sql => $desc) {
    $full_sql = "ALTER TABLE facebook_posts $alter_sql";
    
    try {
        $db->exec($full_sql);
        echo "   ✓ $desc ajoutée\n";
    } catch (Exception $e) {
        // Colonne peut déjà exister
        if (strpos($e->getMessage(), '1060') !== false || 
            strpos($e->getMessage(), 'Duplicate column') !== false ||
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✓ $desc déjà présente\n";
        } else {
            echo "   ⚠️ $desc: {$e->getMessage()}\n";
        }
    }
}

// ────────────────────────────────────────────────────────────────
// STEP 2: ADD URL_HASH COLUMN FOR DEDUPLICATION (key too long for index)
// ────────────────────────────────────────────────────────────────

echo "\n2. Ajout de url_hash pour la déduplication...\n";

try {
    // Ajouter la colonne url_hash si elle n'existe pas
    $db->exec("ALTER TABLE facebook_posts ADD COLUMN url_hash VARCHAR(64) UNIQUE AFTER fb_post_url");
    echo "   ✓ Colonne url_hash ajoutée avec UNIQUE\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), '1060') !== false || 
        strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "   ✓ Colonne url_hash déjà présente\n";
    } else {
        echo "   ⚠️ Attention: {$e->getMessage()}\n";
    }
}

// Remplir les hashes existants
try {
    $db->exec("UPDATE facebook_posts SET url_hash = MD5(fb_post_url) WHERE url_hash IS NULL");
    echo "   ✓ Hashes calculés pour les URLs existantes\n";
} catch (Exception $e) {
    echo "   ⚠️ Erreur remplissage hashes: {$e->getMessage()}\n";
}

// ────────────────────────────────────────────────────────────────
// STEP 3: VERIFY ai_analysis TABLE
// ────────────────────────────────────────────────────────────────

echo "\n3. Vérification de la table 'ai_analysis'...\n";

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
    echo "   ✓ Table 'ai_analysis' vérifiée\n";
} catch (Exception $e) {
    echo "   ⚠️ Table 'ai_analysis': {$e->getMessage()}\n";
}

// ────────────────────────────────────────────────────────────────
// FINAL: SUMMARY
// ────────────────────────────────────────────────────────────────

echo "\n====================================================================\n";

if ($success) {
    echo "✓ Migration RÉUSSIE!\n";
    echo "\nVotre base de données est maintenant compatible avec le système OSINT:\n";
    echo "  • facebook_posts — Publications Facebook (ancien scraper + nouvelles colonnes)\n";
    echo "  • ai_analysis — Analyses IA par publication\n";
    echo "\nVous pouvez maintenant exécuter:\n";
    echo "  python facebook_post_extractor.py --url <facebook_url> --json\n";
} else {
    echo "⚠️ Migration PARTIELLE — Certains éléments n'ont pas pu être appliqués\n";
}

echo "====================================================================\n";
?>
