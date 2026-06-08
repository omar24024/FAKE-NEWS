<?php
/**
 * fix_database.php — Réinstaller les tables manquantes
 */

require_once __DIR__ . '/includes/config.php';

try {
    $db = getDB();
    
    // Créer la table ai_detection_rules si elle n'existe pas
    $db->exec("
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
    ) ENGINE=InnoDB
    ");
    
    echo "<div style='background:#ECFDF5;border:1px solid #6EE7B7;padding:20px;border-radius:8px;color:#065F46;margin:20px;'>";
    echo "<h2>✓ Table ai_detection_rules créée !</h2>";
    echo "<p>La base de données a été mise à jour avec succès.</p>";
    echo "<p><a href='index.php' style='background:#059669;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>→ Retour au tableau de bord</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#FEF2F2;border:1px solid #FCA5A5;padding:20px;border-radius:8px;color:#991B1B;margin:20px;'>";
    echo "<strong>✗ Erreur :</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
