<?php
/**
 * Migration finale — politiques Facebook, colonnes posts, règles hassaniya
 * Usage: php database/finish_project.php
 */
require_once __DIR__ . '/../includes/config.php';

$db = getDB();
echo "=== Finalisation du projet ===\n\n";

// Colonnes facebook_posts
foreach ([
    "ALTER TABLE facebook_posts ADD COLUMN content_type ENUM('text','image','text_image') DEFAULT 'text' AFTER image_url",
    "ALTER TABLE facebook_posts ADD COLUMN link_status ENUM('active','inaccessible','deleted','unknown') DEFAULT 'unknown' AFTER fb_post_url",
] as $sql) {
    try {
        $db->exec($sql);
        echo "OK: colonne ajoutée\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), '1060')) {
            echo "— colonne déjà présente\n";
        } else {
            throw $e;
        }
    }
}

// Table politiques Facebook (Community Standards)
$db->exec("
CREATE TABLE IF NOT EXISTS facebook_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_code VARCHAR(80) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    category ENUM('fake_news','disinformation','hate_speech','misinformation','cyberbullying','violence','propaganda','general') NOT NULL,
    source_url VARCHAR(500) DEFAULT 'https://transparency.meta.com/policies/community-standards/',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_policy (policy_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$fbPolicies = [
    ['FB-MISINFO', 'Fausses informations (Misinformation)', 'Contenus factuellement faux ou trompeurs susceptibles de nuire à la communauté.', 'fake_news'],
    ['FB-DISINFO', 'Désinformation (Disinformation)', 'Informations délibérément fausses ou manipulées pour induire en erreur.', 'disinformation'],
    ['FB-HATE', 'Discours haineux (Hate Speech)', 'Attaques directes contre des personnes ou groupes protégés.', 'hate_speech'],
    ['FB-BULLYING', 'Intimidation et harcèlement (Bullying & Harassment)', 'Comportements répétés visant à humilier, menacer ou harceler.', 'cyberbullying'],
    ['FB-VIOLENCE', 'Violence et incitation (Violence & Incitement)', 'Menaces criminelles, incitation à la violence ou glorification de la violence.', 'violence'],
    ['FB-SAFETY', 'Intégrité civique (Civic Integrity)', 'Contenus pouvant entraver le processus démocratique ou la sécurité publique.', 'misinformation'],
    ['FB-SPAM', 'Spam et contenu inauthentique', 'Comportements manipulatoires ou contenus dupliqués à des fins de tromperie.', 'propaganda'],
    ['FB-GENERAL', 'Normes communautaires Meta (général)', 'Cadre général des contenus interdits ou restreints sur Facebook.', 'general'],
];

$stmt = $db->prepare("INSERT IGNORE INTO facebook_policies (policy_code, title, description, category) VALUES (?,?,?,?)");
foreach ($fbPolicies as $p) {
    $stmt->execute($p);
}
echo "OK: politiques Facebook (" . count($fbPolicies) . ")\n";

// Textes juridiques mauritaniens supplémentaires
$legal = [
    ['CONSTIT-1991-A10', 'Constitution — Liberté d\'expression (Art. 10)', 'Garantit la liberté d\'opinion et d\'expression, sous réserve du respect de l\'ordre public.', 'general', 'Constitution de la République Islamique de Mauritanie', 1991],
    ['LOI-2016-032', 'Loi n° 2016-032 — Lutte contre la cybercriminalité', 'Sanctions relatives aux contenus illicites diffusés via les réseaux sociaux.', 'cyber', 'Journal Officiel Mauritanien', 2016],
    ['ART-293-CP', 'Article 293 — Diffamation publique', 'Peine pour diffamation par voie électronique ou publique.', 'cyber', 'Code Pénal Mauritanien', 2001],
];
$lstmt = $db->prepare("INSERT IGNORE INTO legal_references (reference_code, title, description, category, source, year) VALUES (?,?,?,?,?,?)");
foreach ($legal as $l) {
    $lstmt->execute($l);
}
echo "OK: textes juridiques Mauritanie\n";

// Règles hassaniya / arabe
$hassaniyaRules = [
    ['fake_news', 'كذبة', 0.22, 'keyword', 2, 'Hassaniya — mensonge / fake'],
    ['fake_news', 'كيدب', 0.22, 'keyword', 2, 'Darija — c\'est un mensonge'],
    ['fake_news', 'شائعة', 0.20, 'keyword', 2, 'Rumeur non vérifiée'],
    ['fake_news', 'اشاعة', 0.20, 'keyword', 2, 'Rumeur'],
    ['fake_news', 'عاجل', 0.18, 'keyword', 1, 'Urgence sensationnaliste'],
    ['fake_news', 'مش صحيح', 0.19, 'phrase', 2, 'Information fausse'],
    ['fake_news', 'ما صحيح', 0.19, 'phrase', 2, 'Non vérifié'],
    ['fake_news', 'فارغ', 0.17, 'keyword', 1, 'Contenu sans fondement'],
    ['fake_news', 'كلام فارغ', 0.21, 'phrase', 2, 'Propos infondés'],
    ['fake_news', 'واش صحيح', 0.15, 'phrase', 1, 'Question rumeur typique'],
    ['disinformation', 'قالو بلي', 0.18, 'phrase', 2, 'On dit que — source vague'],
    ['disinformation', 'سمعت بلي', 0.17, 'phrase', 2, 'J\'ai entendu que'],
    ['disinformation', 'حسب ما وصل', 0.16, 'phrase', 1, 'Selon des sources non confirmées'],
    ['hate_speech', 'خرج من البلاد', 0.24, 'phrase', 3, 'Appel à l\'exclusion'],
    ['hate_speech', 'ما ينفعش', 0.18, 'phrase', 2, 'Exclusion hostile'],
    ['hate_speech', 'الاجانب', 0.16, 'keyword', 1, 'Référence hostile étrangers'],
    ['cyberbullying', 'حمار', 0.20, 'keyword', 2, 'Insulte personnelle'],
    ['cyberbullying', 'غبي', 0.19, 'keyword', 2, 'Insulte intelligence'],
    ['misinformation', 'دواء يشفي', 0.22, 'phrase', 2, 'Fausse promesse médicale'],
    ['misinformation', 'علاج سري', 0.21, 'phrase', 2, 'Charlatanisme santé'],
    ['violence', 'قتل', 0.25, 'keyword', 3, 'Violence — tuer'],
    ['violence', 'ضرب', 0.20, 'keyword', 2, 'Appel violence physique'],
];

$rstmt = $db->prepare("
    INSERT IGNORE INTO ai_detection_rules (category, keyword, weight, rule_type, priority, description)
    VALUES (?, ?, ?, ?, ?, ?)
");
$added = 0;
foreach ($hassaniyaRules as $r) {
    $rstmt->execute($r);
    if ($rstmt->rowCount() > 0) $added++;
}
echo "OK: règles hassaniya ajoutées ($added nouvelles)\n";

// Mettre à jour posts existants
$db->exec("UPDATE facebook_posts SET content_type = IF(image_url IS NOT NULL AND image_url != '' AND content IS NOT NULL AND content != '', 'text_image', IF(image_url IS NOT NULL AND image_url != '', 'image', 'text')) WHERE content_type IS NULL OR content_type = 'text'");
$db->exec("UPDATE facebook_posts SET link_status = 'active' WHERE fb_post_url IS NOT NULL AND (link_status IS NULL OR link_status = 'unknown')");

echo "\n=== Projet finalisé avec succès ===\n";
