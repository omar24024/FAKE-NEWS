<?php
/**
 * Recopie les noms d'auteur depuis facebook_accounts vers facebook_posts.author_name
 * pour les publications déjà en base (avant la colonne author_name).
 *
 * Usage: php database/backfill_author_names.php
 */
require_once __DIR__ . '/../includes/config.php';

$db = getDB();
ensurePostAuthorColumn($db);

$updated = $db->exec("
    UPDATE facebook_posts fp
    INNER JOIN facebook_accounts acc ON fp.account_id = acc.id
    SET fp.author_name = acc.name
    WHERE (fp.author_name IS NULL OR TRIM(fp.author_name) = '')
      AND acc.name IS NOT NULL
      AND TRIM(acc.name) != ''
      AND LOWER(acc.name) NOT IN ('auteur inconnu', 'inconnu', 'auteur non identifié')
");

echo "✓ Publications mises à jour: {$updated}\n";
echo "Pour les auteurs manquants, ré-extrayez les URLs via le scraper OSINT.\n";
