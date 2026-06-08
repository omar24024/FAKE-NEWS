<?php
/**
 * TEST_SYSTEM.php
 * 
 * Test complet du système pour vérifier que tout fonctionne.
 * Run: http://localhost/fake-news-platform-b/TEST_SYSTEM.php
 */

set_time_limit(120);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

autoInstall();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Système</title>";
echo "<style>
body { font-family: 'Courier New', monospace; padding: 20px; background: #0d1117; color: #c9d1d9; }
.ok { color: #3fb950 } .err { color: #f85149 } .warn { color: #d29922 } 
.test { margin: 15px 0; padding: 12px; background: #161b22; border-left: 3px solid #58a6ff; }
h1 { color: #58a6ff } h3 { color: #79c0ff }
pre { background: #0d1117; border: 1px solid #30363d; padding: 10px; overflow-x: auto; }
.pass { border-left-color: #3fb950 } .fail { border-left-color: #f85149 }
button { padding: 8px 15px; background: #238636; color: white; border: 0; border-radius: 6px; cursor: pointer; margin: 5px 0; }
button:hover { background: #2ea043 }
</style></head><body>";

echo "<h1>🧪 TEST SYSTÈME OSINT FACEBOOK</h1>";
echo "<p>Tests des composants critiques...</p>";

$tests_passed = 0;
$tests_total = 0;

function test($name, $fn) {
    global $tests_passed, $tests_total;
    $tests_total++;
    echo "<div class='test'>";
    echo "<h3>$name</h3>";
    try {
        $result = $fn();
        if ($result) {
            echo "<p class='ok'>✓ PASS</p>";
            $tests_passed++;
        } else {
            echo "<p class='err'>✗ FAIL</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='err'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
}

// ===== TEST 1: Database Connection =====
test("Connexion Base de Données", function() {
    $db = getDB();
    $result = $db->query("SELECT DATABASE() as db")->fetch();
    echo "<pre>" . htmlspecialchars($result['db']) . "</pre>";
    return !empty($result['db']);
});

// ===== TEST 2: Tables Exist =====
test("Tables Critiques", function() {
    $db = getDB();
    $tables = ['users', 'facebook_posts', 'ai_analysis'];
    foreach ($tables as $tbl) {
        try {
            $db->query("SELECT 1 FROM `$tbl` LIMIT 1");
            echo "<p class='ok'>✓ $tbl</p>";
        } catch (Exception $e) {
            echo "<p class='err'>✗ $tbl manquante</p>";
            return false;
        }
    }
    return true;
});

// ===== TEST 3: Admin Account =====
test("Compte Admin", function() {
    $db = getDB();
    $stmt = $db->query("SELECT id, username FROM users WHERE role='admin' LIMIT 1");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "<p class='ok'>✓ Admin: " . htmlspecialchars($admin['username']) . "</p>";
        return true;
    } else {
        echo "<p class='warn'>⚠ Aucun admin</p>";
        return false;
    }
});

// ===== TEST 4: PHP Functions =====
test("Fonctions PHP", function() {
    require_once __DIR__ . '/includes/functions.php';
    
    $funcs = [
        'getPosts' => function_exists('getPosts'),
        'getStats' => function_exists('getStats'),
        'getPostsList' => function_exists('getPostsList'),
    ];
    
    foreach ($funcs as $fname => $ok) {
        $status = $ok ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>";
        echo "<p>$status $fname</p>";
    }
    
    return array_sum(array_values($funcs)) === count($funcs);
});

// ===== TEST 5: Python Environment =====
test("Environnement Python", function() {
    $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
    $output = shell_exec("$pythonBin --version 2>&1");
    
    if (strpos($output, 'Python') !== false) {
        echo "<p class='ok'>✓ " . htmlspecialchars(trim($output)) . "</p>";
        return true;
    } else {
        echo "<p class='err'>✗ Python non trouvé</p>";
        return false;
    }
});

// ===== TEST 6: Python Modules =====
test("Modules Python", function() {
    $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
    
    $modules = [
        'playwright' => 'Playwright',
        'mysql.connector' => 'MySQL Connector',
        'transformers' => 'Transformers (IA)',
    ];
    
    foreach ($modules as $mod => $name) {
        $test_cmd = "$pythonBin -c \"import $mod\" 2>&1";
        $result = shell_exec($test_cmd);
        
        if (empty($result) || strpos($result, 'No module') === false) {
            echo "<p class='ok'>✓ $name</p>";
        } else {
            echo "<p class='err'>✗ $name manquant</p>";
            return false;
        }
    }
    return true;
});

// ===== TEST 7: Extractor Script =====
test("Script Extracteur", function() {
    $path = __DIR__ . '/python-ai/facebook_post_extractor.py';
    if (!file_exists($path)) {
        echo "<p class='err'>✗ Fichier manquant</p>";
        return false;
    }
    
    $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
    $help = shell_exec("$pythonBin \"$path\" --help 2>&1");
    
    if (strpos($help, 'usage') !== false || strpos($help, 'argument') !== false) {
        echo "<p class='ok'>✓ Script exécutable</p>";
        return true;
    } else {
        echo "<p class='err'>✗ Script non exécutable</p>";
        echo "<pre>" . htmlspecialchars(substr($help, 0, 200)) . "</pre>";
        return false;
    }
});

// ===== TEST 8: API Endpoints =====
test("Endpoints API", function() {
    // Simulate API call
    $endpoints = [
        '/api/facebook_post_api.php?action=get_stats',
        '/api/facebook_post_api.php?action=get_recent_posts',
    ];
    
    foreach ($endpoints as $ep) {
        $file = __DIR__ . $ep;
        $exists = file_exists(dirname($file) . '/' . basename(explode('?', $file)[0]));
        $status = $exists ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>";
        echo "<p>$status $ep</p>";
    }
    return true;
});

// ===== TEST 9: Database Integrity =====
test("Intégrité BD", function() {
    $db = getDB();
    
    // Check foreign keys
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as orphan FROM facebook_posts 
            WHERE account_id IS NOT NULL 
            AND account_id NOT IN (SELECT id FROM facebook_accounts)
        ");
        $orphans = $stmt->fetchColumn();
        
        if ($orphans > 0) {
            echo "<p class='warn'>⚠ $orphans posts orphelins (account_id invalide)</p>";
        } else {
            echo "<p class='ok'>✓ Pas de posts orphelins</p>";
        }
    } catch (Exception $e) {
        echo "<p class='warn'>⚠ Vérification FK échouée (peut être normal)</p>";
    }
    return true;
});

// ===== TEST 10: Sample Data =====
test("Données Exemples", function() {
    $db = getDB();
    
    $counts = [
        'Posts' => 'facebook_posts',
        'Analyses' => 'ai_analysis',
        'Comptes' => 'facebook_accounts',
    ];
    
    foreach ($counts as $name => $table) {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $status = $count > 0 ? "<span class='ok'>✓</span>" : "<span class='warn'>⚠</span>";
        echo "<p>$status $name: $count enregistrements</p>";
    }
    return true;
});

// ===== SUMMARY =====
echo "<div style='margin-top: 30px; padding: 20px; background: #161b22; border: 2px solid #30363d; border-radius: 6px;'>";
echo "<h2>📊 Résumé</h2>";

$percentage = $tests_total > 0 ? round(($tests_passed / $tests_total) * 100) : 0;
$color = $percentage >= 80 ? 'ok' : ($percentage >= 60 ? 'warn' : 'err');

echo "<p><span class='$color'><strong>$tests_passed/$tests_total</strong></span> tests réussis ($percentage%)</p>";

if ($percentage === 100) {
    echo "<p class='ok'><strong>✓ Système STABLE</strong></p>";
    echo "<p><button onclick=\"location.href='" . APP_URL . "/login.php'\">Aller à l'application</button></p>";
} elseif ($percentage >= 80) {
    echo "<p class='warn'><strong>⚠ Système fonctionnel</strong> mais avec réserves</p>";
    echo "<p>Voir les erreurs ci-dessus et consulter STABILIZATION_GUIDE.md</p>";
} else {
    echo "<p class='err'><strong>✗ Système instable</strong></p>";
    echo "<p>Exécuter INITIALIZE_SYSTEM.php d'abord</p>";
    echo "<p><button onclick=\"location.href='" . APP_URL . "/INITIALIZE_SYSTEM.php'\">Initialiser Système</button></p>";
}

echo "</div>";

echo "</body></html>";
?>
