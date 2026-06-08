<?php
/**
 * database/fix_fk_constraint.php
 * ================================================================
 * Fix foreign key constraint issues for account_id
 * ================================================================
 */

require_once __DIR__ . '/../includes/config.php';

$db = getDB();

echo "====================================================================\n";
echo "Fix: Foreign Key Constraint on account_id\n";
echo "====================================================================\n\n";

try {
    // Drop the foreign key constraint
    echo "1. Suppression de la contrainte FK sur account_id...\n";
    try {
        $db->exec("ALTER TABLE facebook_posts DROP FOREIGN KEY facebook_posts_ibfk_1");
        echo "   ✓ Contrainte FK supprimée\n";
    } catch (Exception $e) {
        echo "   ⚠️ Constraint n'existait peut-être pas: {$e->getMessage()}\n";
    }
    
    // Make account_id nullable (if not already)
    echo "\n2. Configuration de account_id comme NULLABLE...\n";
    try {
        $db->exec("ALTER TABLE facebook_posts MODIFY account_id INT NULL");
        echo "   ✓ account_id est maintenant NULLABLE\n";
    } catch (Exception $e) {
        echo "   ⚠️ Erreur modification: {$e->getMessage()}\n";
    }
    
    // Set all existing account_id values to NULL (since we don't have them in OSINT system)
    echo "\n3. Nettoyage des valeurs account_id...\n";
    try {
        $result = $db->exec("UPDATE facebook_posts SET account_id = NULL");
        echo "   ✓ Valeurs account_id réinitialisées à NULL\n";
    } catch (Exception $e) {
        echo "   ⚠️ Erreur update: {$e->getMessage()}\n";
    }
    
    echo "\n====================================================================\n";
    echo "✓ FIX COMPLÉTÉ!\n";
    echo "\nLa table facebook_posts est maintenant compatible avec le système OSINT\n";
    echo "sans dépendre de la table facebook_accounts.\n";
    echo "====================================================================\n";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
?>
