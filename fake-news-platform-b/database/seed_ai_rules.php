<?php
/**
 * ============================================================
 * Migration: Seed AI Detection Rules
 * ============================================================
 * 
 * This script populates the ai_detection_rules table with
 * the initial set of keywords for each category.
 * 
 * Run via: php database/seed_ai_rules.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// Vérifier si des règles existent déjà
$existing = $db->query("SELECT COUNT(*) as cnt FROM ai_detection_rules")->fetch();
if ($existing['cnt'] > 0) {
    echo "   Des règles existent déjà. Migration sautée.\n";
    echo "   Si vous voulez réinitialiser, exécutez d'abord:\n";
    echo "   TRUNCATE TABLE ai_detection_rules;\n";
    exit(0);
}

// Données de semence initiales
$rules = [
    'fake_news' => [
        ['urgent', 0.18, 'keyword', 1, 'Signal d\'urgence typique du sensationnalisme'],
        ['alerte', 0.18, 'keyword', 1, 'Alerte sensationnaliste'],
        ['censuré', 0.20, 'keyword', 2, 'Prétention de censure - signe courant'],
        ['partagez avant', 0.22, 'phrase', 3, 'Incitation urgente à partager'],
        ['ils ne veulent pas', 0.20, 'phrase', 2, 'Rhetorique conspirateur'],
        ['breaking', 0.15, 'keyword', 1, 'Breaking news sensationnaliste'],
        ['exclusif', 0.18, 'keyword', 1, 'Prétention d\'exclusivité'],
        ['secret', 0.20, 'keyword', 1, 'Prétention de révélation secrète'],
        ['révélation', 0.18, 'keyword', 1, 'Révélation prétendument cachée'],
        ['complot', 0.22, 'keyword', 2, 'Langage conspirationniste'],
        ['mensonge officiel', 0.20, 'phrase', 1, 'Accusation de mensonge institutionnel'],
        ['cachée', 0.19, 'keyword', 1, 'Allégation de dissimulation'],
        ['explosion', 0.16, 'keyword', 1, 'Sensationnalisme catastrophe'],
        ['attaque', 0.15, 'keyword', 1, 'Sensationnalisme attaque'],
    ],
    
    'disinformation' => [
        ['source ministère', 0.20, 'phrase', 2, 'Faux source officielle'],
        ['gouvernement a décidé', 0.18, 'phrase', 2, 'Prétention de décision officielle'],
        ['annonce officielle', 0.18, 'phrase', 2, 'Fausse annonce gouvernementale'],
        ['selon des sources', 0.15, 'phrase', 1, 'Source vague et non vérifiée'],
        ['il paraît que', 0.14, 'phrase', 1, 'Rumeur vague'],
        ['on dit que', 0.14, 'phrase', 1, 'Information non sourçée'],
        ['rumeur confirme', 0.16, 'phrase', 1, 'Amplification de rumeur'],
        ['hausse des prix', 0.10, 'phrase', 1, 'Information économique non vérifiée'],
        ['nouvelle loi', 0.12, 'phrase', 1, 'Fausse information légale'],
        ['prochainement', 0.09, 'keyword', 1, 'Promesse vague de l\'avenir'],
    ],
    
    'hate_speech' => [
        ['expulser', 0.25, 'keyword', 3, 'Appel à l\'expulsion'],
        ['dehors', 0.23, 'keyword', 2, 'Exclusion hostile'],
        ['envahisseurs', 0.26, 'keyword', 3, 'Déshumanisation d\'un groupe'],
        ['ces gens-là', 0.24, 'phrase', 2, 'Référence hostile généralisée'],
        ['étrangers volent', 0.22, 'phrase', 2, 'Accusation criminelle basée sur identité'],
        ['ennemis', 0.20, 'keyword', 1, 'Hostile framming d\'un groupe'],
        ['traîtres', 0.24, 'keyword', 2, 'Accusation trahison contre groupe'],
        ['racaille', 0.27, 'keyword', 3, 'Insulte grave vers groupe'],
        ['vermine', 0.28, 'keyword', 3, 'Déshumanisation extrême'],
        ['déportation', 0.25, 'keyword', 3, 'Appel à nettoyage ethnique'],
        ['inférieurs', 0.23, 'keyword', 2, 'Hiérarchie raciale'],
        ['sous-hommes', 0.29, 'keyword', 3, 'Déshumanisation nazie'],
    ],
    
    'misinformation' => [
        ['médicament miracle', 0.22, 'phrase', 2, 'Fausse réclamation médicale'],
        ['100% efficace', 0.23, 'phrase', 2, 'Prétention de guérison absolue'],
        ['guérit en', 0.23, 'phrase', 2, 'Fausse promesse de guérison'],
        ['cure miraculeuse', 0.24, 'phrase', 3, 'Charlatanisme médical'],
        ['les médecins cachent', 0.20, 'phrase', 2, 'Conspiration médicale'],
        ['big pharma', 0.18, 'phrase', 1, 'Théorie conspirateur pharma'],
        ['remède naturel contre', 0.17, 'phrase', 1, 'Fausse médecine alternative'],
        ['traitement secret', 0.19, 'phrase', 1, 'Médecine cachée prétendant'],
        ['sans effets secondaires', 0.19, 'phrase', 1, 'Fausse réclamation médicale'],
        ['commandez maintenant', 0.20, 'phrase', 2, 'Arnaque commerciale santé'],
    ],
    
    'propaganda' => [
        ['mission civilisatrice', 0.20, 'phrase', 2, 'Justification coloniale'],
        ['supériorité', 0.21, 'keyword', 2, 'Rhetorique de hiérarchie'],
        ['destin manifeste', 0.19, 'phrase', 1, 'Expansionnisme idéologique'],
        ['l\'ordre naturel', 0.18, 'phrase', 1, 'Justification inégalité'],
        ['pour le bien de', 0.16, 'phrase', 1, 'Justification rhétorique'],
        ['nécessité historique', 0.17, 'phrase', 1, 'Déterminisme historique'],
        ['ennemi du peuple', 0.22, 'phrase', 2, 'Désignation de bouc émissaire'],
    ],
    
    'violence' => [
        ['tuer', 0.26, 'keyword', 2, 'Appel à la violence mortelle'],
        ['assassiner', 0.27, 'keyword', 3, 'Appel au meurtre'],
        ['étrangler', 0.25, 'keyword', 2, 'Appel à violence physique'],
        ['lyncher', 0.28, 'keyword', 3, 'Violence collective'],
        ['torturer', 0.26, 'keyword', 2, 'Appel à torture'],
        ['décapiter', 0.27, 'keyword', 3, 'Violence extrême'],
        ['frapper', 0.18, 'keyword', 1, 'Appel à violence'],
        ['éliminer', 0.22, 'keyword', 2, 'Euphemisme pour meurtre'],
    ],
    
    'cyberbullying' => [
        ['débile', 0.20, 'keyword', 1, 'Insulte ableiste'],
        ['connard', 0.19, 'keyword', 1, 'Insulte générale'],
        ['c\'est horrible', 0.15, 'phrase', 1, 'Critique hostile'],
        ['tu devrais mourir', 0.27, 'phrase', 3, 'Souhaite mort'],
        ['personne ne t\'aime', 0.21, 'phrase', 2, 'Isolement social'],
        ['cringe', 0.10, 'keyword', 1, 'Ridiculisation générale'],
        ['pathétique', 0.14, 'keyword', 1, 'Insulte personnelle'],
        ['nul', 0.12, 'keyword', 1, 'Critique hostile'],
    ],
    
    'neutral_indicators' => [
        ['selon le journal', 0.05, 'phrase', 1, 'Source journalistique vérifiée'],
        ['l\'étude montre', 0.06, 'phrase', 1, 'Référence empirique'],
        ['expert affirme', 0.05, 'phrase', 1, 'Opinion d\'expert sourçée'],
        ['rapport officiel', 0.04, 'phrase', 1, 'Document institutionnel'],
        ['communiqué de presse', 0.04, 'phrase', 1, 'Source officielle'],
    ],
];

// Insérer les règles
$inserted = 0;
$errors = [];

try {
    $stmt = $db->prepare("
        INSERT INTO ai_detection_rules 
        (category, keyword, weight, rule_type, priority, description, created_by)
        VALUES (?, ?, ?, ?, ?, ?, NULL)
    ");
    
    foreach ($rules as $category => $keywords) {
        foreach ($keywords as [$keyword, $weight, $type, $priority, $description]) {
            try {
                $stmt->execute([$category, $keyword, $weight, $type, $priority, $description]);
                $inserted++;
                echo "✓ {$category}: '{$keyword}' (weight={$weight})\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    $errors[] = "Erreur pour {$category}/{$keyword}: " . $e->getMessage();
                    echo "⚠ {$category}: '{$keyword}' déjà existant\n";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "✓ Semence terminée!\n";
    echo "  Total inséré: {$inserted} règles\n";
    
    if (!empty($errors)) {
        echo "\n⚠ Erreurs rencontrées:\n";
        foreach ($errors as $err) {
            echo "  - {$err}\n";
        }
    }
    
    // Afficher les statistiques
    $stats = $db->query("
        SELECT category, COUNT(*) as cnt
        FROM ai_detection_rules
        GROUP BY category
        ORDER BY category
    ")->fetchAll();
    
    echo "\n Distribution par catégorie:\n";
    foreach ($stats as $row) {
        echo "  - {$row['category']}: {$row['cnt']} règles\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur de migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
