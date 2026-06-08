<?php
/**
 * cleanup_demo_data.php
 * Supprime les anciennes données de démonstration si elles existent.
 *
 * Usage (PowerShell):
 *   php database/cleanup_demo_data.php
 */
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

echo "====================================================================\n";
echo "Nettoyage — données de démonstration\n";
echo "====================================================================\n\n";

// Heuristiques: anciennes seeds utilisaient fb_post_id comme 'post_001' etc,
// et des URLs/images locales sous assets/images/posts/
$demoPosts = $db->prepare("SELECT id FROM facebook_posts WHERE fb_post_id LIKE 'post\\_%' ESCAPE '\\\\' OR fb_post_url LIKE '%/story/post_%' OR image_url LIKE 'assets/images/posts/%'");
$demoPosts->execute();
$postIds = array_map('intval', $demoPosts->fetchAll(PDO::FETCH_COLUMN));

if (!$postIds) {
  echo "✓ Aucun post de démonstration détecté.\n";
  exit(0);
}

echo "• Posts de démonstration détectés: " . count($postIds) . "\n";

// Delete cascades should handle ai_analysis/detected_keywords/notifications(post_id)
$in = implode(',', array_fill(0, count($postIds), '?'));
$db->prepare("DELETE FROM notifications WHERE post_id IN ($in)")->execute($postIds);
$db->prepare("DELETE FROM ai_analysis WHERE post_id IN ($in)")->execute($postIds);
$db->prepare("DELETE FROM facebook_posts WHERE id IN ($in)")->execute($postIds);

echo "✓ Suppression terminée.\n";

// Supprimer les comptes orphelins (seed démo sans publications)
$orphans = $db->query("
    SELECT fa.id FROM facebook_accounts fa
    LEFT JOIN facebook_posts fp ON fp.account_id = fa.id
    WHERE fp.id IS NULL
")->fetchAll(PDO::FETCH_COLUMN);

if ($orphans) {
    $inAcc = implode(',', array_fill(0, count($orphans), '?'));
    $db->prepare("DELETE FROM facebook_accounts WHERE id IN ($inAcc)")->execute($orphans);
    echo "✓ Comptes orphelins supprimés: " . count($orphans) . "\n";
}

// Comptes seed démo (avatars locaux fictifs)
$db->exec("DELETE FROM facebook_accounts WHERE profile_picture LIKE 'assets/images/avatars/%'");

echo "====================================================================\n";

