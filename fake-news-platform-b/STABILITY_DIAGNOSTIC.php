<?php
/**
 * STABILITY_DIAGNOSTIC.php
 * 
 * Diagnostic complet du projet pour identifier les problèmes
 * Run: http://localhost/fake-news-platform-b/STABILITY_DIAGNOSTIC.php
 */

require_once __DIR__ . '/includes/config.php';

autoInstall();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Stabilité du Projet</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4}";
echo ".ok{color:#4ec9b0}.err{color:#f48771}.warn{color:#dcdcaa}.section{margin:20px 0;border:1px solid #3e3e3e;padding:15px}";
echo "h2{color:#569cd6}pre{background:#252526;padding:10px;overflow-x:auto}";
echo "</style></head><body>";

$tests = [];

// ===== TEST 1: DATABASE CONNECTIVITY =====
echo "<div class='section'><h2>1️⃣ Connexion Base de Données</h2>";
try {
    $db = getDB();
    $stmt = $db->query("SELECT DATABASE() as db, USER() as user");
    $info = $stmt->fetch();
    echo "<p class='ok'>✓ Connecté à: <strong>{$info['db']}</strong> (user: {$info['user']})</p>";
    $tests['db_connection'] = true;
} catch (Exception $e) {
    echo "<p class='err'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    $tests['db_connection'] = false;
}
echo "</div>";

// ===== TEST 2: TABLES EXISTENCE =====
echo "<div class='section'><h2>2️⃣ Tables Existantes</h2>";
$tables = ['users', 'facebook_accounts', 'facebook_posts', 'ai_analysis', 'notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "<p><strong>$table</strong>: <span class='ok'>$count enregistrements</span></p>";
    } catch (Exception $e) {
        echo "<p><strong>$table</strong>: <span class='err'>Erreur - " . htmlspecialchars($e->getMessage()) . "</span></p>";
    }
}
echo "</div>";

// ===== TEST 3: USER ACCOUNT =====
echo "<div class='section'><h2>3️⃣ Compte Admin</h2>";
try {
    $stmt = $db->query("SELECT id, username, email, role FROM users WHERE role='admin' LIMIT 1");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "<p class='ok'>✓ Admin trouvé: <strong>{$admin['username']}</strong> ({$admin['email']})</p>";
        echo "<pre>" . json_encode($admin, JSON_PRETTY_PRINT) . "</pre>";
        $tests['admin'] = true;
    } else {
        echo "<p class='warn'>⚠️  Aucun admin - créer manuellement</p>";
        $tests['admin'] = false;
    }
} catch (Exception $e) {
    echo "<p class='err'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    $tests['admin'] = false;
}
echo "</div>";

// ===== TEST 4: DATA SAMPLE =====
echo "<div class='section'><h2>4️⃣ Données Existantes</h2>";
try {
    $posts = $db->query("SELECT COUNT(*) as count FROM facebook_posts")->fetchColumn();
    $analyses = $db->query("SELECT COUNT(*) as count FROM ai_analysis")->fetchColumn();
    $accounts = $db->query("SELECT COUNT(*) as count FROM facebook_accounts")->fetchColumn();
    
    echo "<p><strong>Publications:</strong> <span class='" . ($posts > 0 ? 'ok' : 'warn') . "'>$posts</span></p>";
    echo "<p><strong>Analyses IA:</strong> <span class='" . ($analyses > 0 ? 'ok' : 'warn') . "'>$analyses</span></p>";
    echo "<p><strong>Comptes Facebook:</strong> <span class='" . ($accounts > 0 ? 'ok' : 'warn') . "'>$accounts</span></p>";
    
    if ($posts > 0) {
        $recent = $db->query("SELECT id, fb_post_id, author_name, content, is_analyzed FROM facebook_posts ORDER BY fetched_at DESC LIMIT 3")->fetchAll();
        echo "<h3>Dernières publications:</h3>";
        echo "<pre>" . json_encode($recent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    $tests['data'] = true;
} catch (Exception $e) {
    echo "<p class='err'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    $tests['data'] = false;
}
echo "</div>";

// ===== TEST 5: PYTHON ENVIRONMENT =====
echo "<div class='section'><h2>5️⃣ Environnement Python</h2>";
$pythonBin = PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
$output = shell_exec("$pythonBin --version 2>&1");
if ($output && strpos($output, 'Python') !== false) {
    echo "<p class='ok'>✓ Python: " . htmlspecialchars(trim($output)) . "</p>";
    
    // Test imports
    $test_imports = shell_exec("$pythonBin -c \"import playwright, mysql.connector, transformers; print('OK')\" 2>&1");
    if (strpos($test_imports, 'OK') !== false) {
        echo "<p class='ok'>✓ Modules requis trouvés (playwright, mysql, transformers)</p>";
        $tests['python'] = true;
    } else {
        echo "<p class='err'>✗ Modules manquants: " . htmlspecialchars($test_imports) . "</p>";
        $tests['python'] = false;
    }
} else {
    echo "<p class='err'>✗ Python non trouvé</p>";
    $tests['python'] = false;
}
echo "</div>";

// ===== TEST 6: EXTRACTEUR PYTHON =====
echo "<div class='section'><h2>6️⃣ Extracteur Python</h2>";
$extractor_path = __DIR__ . '/python-ai/facebook_post_extractor.py';
if (file_exists($extractor_path)) {
    echo "<p class='ok'>✓ facebook_post_extractor.py existe</p>";
    
    // Test help
    $help = shell_exec("$pythonBin \"$extractor_path\" --help 2>&1");
    if (strpos($help, 'usage') !== false || strpos($help, 'argument') !== false) {
        echo "<p class='ok'>✓ Script exécutable</p>";
        $tests['extractor'] = true;
    } else {
        echo "<p class='err'>✗ Script non exécutable: " . htmlspecialchars(substr($help, 0, 200)) . "</p>";
        $tests['extractor'] = false;
    }
} else {
    echo "<p class='err'>✗ facebook_post_extractor.py manquant</p>";
    $tests['extractor'] = false;
}
echo "</div>";

// ===== TEST 7: API ENDPOINTS =====
echo "<div class='section'><h2>7️⃣ Endpoints API</h2>";
$endpoints = [
    '/api/facebook_post_api.php?action=get_recent_posts',
    '/api/stats.php',
    '/api/ai_analyze.php'
];
foreach ($endpoints as $ep) {
    $file = __DIR__ . $ep;
    $exists = file_exists(dirname($file) . '/' . basename($file));
    $status = $exists ? "<span class='ok'>✓ Existe</span>" : "<span class='err'>✗ Manquant</span>";
    echo "<p><strong>$ep</strong>: $status</p>";
}
echo "</div>";

// ===== TEST 8: FUNCTIONS AVAILABILITY =====
echo "<div class='section'><h2>8️⃣ Fonctions PHP Critiques</h2>";
require_once __DIR__ . '/includes/functions.php';
$functions = [
    'getPosts' => function_exists('getPosts'),
    'getStats' => function_exists('getStats'),
    'getPostsList' => function_exists('getPostsList'),
    'postAuthorName' => function_exists('postAuthorName'),
];
foreach ($functions as $fname => $exists) {
    $status = $exists ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>";
    echo "<p><strong>$fname</strong>: $status</p>";
}
echo "</div>";

// ===== RESUME =====
echo "<div class='section'><h2>📊 Résumé Stabilité</h2>";
$passed = array_sum(array_values($tests));
$total = count($tests);
$health = ($passed / $total) * 100;
$health_class = $health >= 70 ? 'ok' : ($health >= 50 ? 'warn' : 'err');
echo "<p>Tests réussis: <span class='$health_class'><strong>$passed/$total</strong> (${health}%)</span></p>";

if ($health < 70) {
    echo "<p class='err'><strong>⚠️  Problèmes détectés!</strong> Voir details ci-dessus</p>";
} else {
    echo "<p class='ok'><strong>✓ Système stable</strong></p>";
}
echo "</div>";

echo "</body></html>";
?>
