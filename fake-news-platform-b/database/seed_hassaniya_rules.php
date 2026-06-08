<?php
/**
 * Seed règles de détection hassaniya/arabe (si table vide).
 * Usage: php database/seed_hassaniya_rules.php
 */
require_once __DIR__ . '/../includes/config.php';

autoInstall();
$db = getDB();

$count = (int)$db->query('SELECT COUNT(*) FROM ai_detection_rules')->fetchColumn();
if ($count > 0) {
    echo "Déjà {$count} règle(s) — rien à faire.\n";
    exit(0);
}

$rules = [
    ['fake_news', 'كذبة', 0.22, 'keyword', 2, 'Terme hassaniya — mensonge'],
    ['fake_news', 'شائعة', 0.20, 'keyword', 2, 'Rumeur / fake news'],
    ['fake_news', 'كلام فارغ', 0.21, 'phrase', 2, 'Expression hassaniya'],
    ['fake_news', 'ما صح', 0.18, 'phrase', 2, 'Pas vrai'],
    ['disinformation', 'قالو بلي', 0.18, 'phrase', 2, 'On dit que…'],
    ['disinformation', 'سمعت بلي', 0.17, 'phrase', 2, 'J\'ai entendu que…'],
    ['disinformation', 'خبار ما مثبتة', 0.19, 'phrase', 2, 'Info non vérifiée'],
    ['hate_speech', 'خرج من البلاد', 0.24, 'phrase', 3, 'Discours exclusif'],
    ['hate_speech', 'موريتانيا لمرتان', 0.20, 'phrase', 2, 'Nationalisme extrême'],
    ['cyberbullying', 'سخف', 0.18, 'keyword', 2, 'Moquerie'],
    ['cyberbullying', 'اهين', 0.20, 'keyword', 2, 'Humiliation'],
    ['misinformation', 'دواء يشفي', 0.22, 'phrase', 2, 'Santé — fausse promesse'],
];

$stmt = $db->prepare("
    INSERT INTO ai_detection_rules (category, keyword, weight, rule_type, priority, description, is_active)
    VALUES (?, ?, ?, ?, ?, ?, 1)
");

foreach ($rules as $r) {
    $stmt->execute($r);
}

echo count($rules) . " règles hassaniya insérées.\n";
