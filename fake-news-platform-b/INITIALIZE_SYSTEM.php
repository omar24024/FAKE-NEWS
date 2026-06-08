<?php
/**
 * INITIALIZE_SYSTEM.php
 * 
 * Initialise complètement le système:
 * - Crée/vérifie la base de données
 * - Crée les tables
 * - Crée les comptes de test
 * - Charge les données par défaut
 * 
 * Run: http://localhost/fake-news-platform-b/INITIALIZE_SYSTEM.php
 */

set_time_limit(60);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Initialisation Système</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4}";
echo ".ok{color:#4ec9b0}.err{color:#f48771}.warn{color:#dcdcaa}.section{margin:20px 0;border:1px solid #3e3e3e;padding:15px;background:#252526}";
echo "h2{color:#569cd6}h3{color:#ce9178}button{background:#007acc;color:white;padding:10px 20px;border:0;border-radius:4px;cursor:pointer;margin:5px}";
echo "button:hover{background:#005fa3}";
echo "</style></head><body>";

$log = [];

function log_msg($msg, $type = 'info') {
    global $log;
    $classes = ['info' => '', 'success' => 'ok', 'error' => 'err', 'warning' => 'warn'];
    $class = $classes[$type] ?? '';
    $icon = ['info' => 'ℹ', 'success' => '✓', 'error' => '✗', 'warning' => '⚠'][$ type] ?? '•';
    $html = "<p class='$class'>$icon $msg</p>";
    echo $html;
    $log[] = $msg;
}

echo "<div class='section'><h2>🔧 Initialisation du Système OSINT Facebook</h2>";

// ===== STEP 1: CREATE DATABASE =====
echo "<div class='section'><h3>Étape 1: Base de Données</h3>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    log_msg("Connecté au serveur MySQL", 'success');
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    log_msg("Base de données '" . DB_NAME . "' créée/vérifiée", 'success');
    
    $pdo->exec("USE `" . DB_NAME . "`");
} catch (Exception $e) {
    log_msg("Erreur MySQL: " . $e->getMessage(), 'error');
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// ===== STEP 2: CREATE TABLES =====
echo "<div class='section'><h3>Étape 2: Création des Tables</h3>";
try {
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    
    // Split by statements
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));
    
    $created = 0;
    foreach ($statements as $stmt) {
        if (!empty(trim($stmt))) {
            try {
                $pdo->exec($stmt);
                $created++;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    log_msg("⚠ Impossible d'exécuter une instruction: " . substr($e->getMessage(), 0, 60), 'warning');
                }
            }
        }
    }
    log_msg("$created instructions SQL exécutées", 'success');
    
} catch (Exception $e) {
    log_msg("Erreur schéma: " . $e->getMessage(), 'error');
}
echo "</div>";

// ===== STEP 3: CREATE DEFAULT ADMIN =====
echo "<div class='section'><h3>Étape 3: Compte Admin</h3>";
try {
    $db = getDB();
    
    // Check if admin exists
    $stmt = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
    if ($stmt->rowCount() === 0) {
        $admin_user = 'admin';
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
        $admin_email = 'admin@osint.local';
        
        $insert = $db->prepare("
            INSERT INTO users (username, email, password, full_name, role, is_active)
            VALUES (?, ?, ?, ?, 'admin', 1)
        ");
        $insert->execute([$admin_user, $admin_email, $admin_pass, 'Administrateur']);
        log_msg("Compte admin créé: <strong>admin / admin123</strong>", 'success');
    } else {
        log_msg("Compte admin déjà existant", 'warning');
    }
    
    // Create analyst account
    $stmt = $db->query("SELECT id FROM users WHERE role='analyst' LIMIT 1");
    if ($stmt->rowCount() === 0) {
        $analyst_pass = password_hash('analyst123', PASSWORD_BCRYPT);
        $insert = $db->prepare("
            INSERT INTO users (username, email, password, full_name, role, is_active)
            VALUES (?, ?, ?, ?, 'analyst', 1)
        ");
        $insert->execute(['analyst', 'analyst@osint.local', $analyst_pass, 'Analyste']);
        log_msg("Compte analyste créé: <strong>analyst / analyst123</strong>", 'success');
    } else {
        log_msg("Compte analyste déjà existant", 'warning');
    }
    
} catch (Exception $e) {
    log_msg("Erreur création comptes: " . $e->getMessage(), 'error');
}
echo "</div>";

// ===== STEP 4: LOAD DEFAULT DATA =====
echo "<div class='section'><h3>Étape 4: Données par Défaut</h3>";
try {
    $db = getDB();
    
    // Check Facebook accounts
    $stmt = $db->query("SELECT COUNT(*) as count FROM facebook_accounts");
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        $accounts = [
            ['BBC News', 'page', 'News', 'https://www.facebook.com/bbcnews'],
            ['Reuters', 'page', 'News', 'https://www.facebook.com/Reuters'],
            ['Al Jazeera', 'page', 'News', 'https://www.facebook.com/aljazeera'],
        ];
        
        $insert = $db->prepare("
            INSERT INTO facebook_accounts (fb_id, name, type, category, fb_url, is_monitored)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($accounts as [$name, $type, $cat, $url]) {
            $insert->execute([
                'fb_' . substr(md5($url), 0, 16),
                $name,
                $type,
                $cat,
                $url
            ]);
        }
        
        log_msg(count($accounts) . " comptes Facebook de test créés", 'success');
    } else {
        log_msg("Comptes Facebook déjà présents", 'warning');
    }
    
    // Check AI rules
    $stmt = $db->query("SELECT COUNT(*) as count FROM ai_detection_rules");
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        $rules = [
            ['fake_news', 'FAUX', 0.9, 'keyword'],
            ['fake_news', 'HOAX', 0.85, 'keyword'],
            ['disinformation', 'MENSONGE', 0.8, 'keyword'],
            ['hate_speech', 'HAINE', 0.95, 'keyword'],
            ['reliable', 'VÉRIFIABLE', 0.1, 'keyword'],
        ];
        
        $insert = $db->prepare("
            INSERT INTO ai_detection_rules (category, keyword, weight, rule_type)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($rules as [$cat, $kw, $weight, $type]) {
            $insert->execute([$cat, $kw, $weight, $type]);
        }
        
        log_msg(count($rules) . " règles de détection IA créées", 'success');
    } else {
        log_msg("Règles IA déjà présentes", 'warning');
    }
    
} catch (Exception $e) {
    log_msg("Erreur données par défaut: " . $e->getMessage(), 'error');
}
echo "</div>";

// ===== STEP 5: VERIFY SYSTEM =====
echo "<div class='section'><h3>Étape 5: Vérification</h3>";
try {
    $db = getDB();
    
    $tables = ['users', 'facebook_accounts', 'facebook_posts', 'ai_analysis', 'notifications'];
    $all_ok = true;
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetchColumn();
            log_msg("<strong>$table</strong>: OK", 'success');
        } catch (Exception $e) {
            log_msg("<strong>$table</strong>: ERREUR", 'error');
            $all_ok = false;
        }
    }
    
    if ($all_ok) {
        log_msg("✓ Tous les tests sont passés!", 'success');
        echo "<p style='margin-top:20px'><button onclick='location.href=\"" . APP_URL . "/login.php\"'>Aller à la Connexion</button></p>";
    }
    
} catch (Exception $e) {
    log_msg("Erreur vérification: " . $e->getMessage(), 'error');
}
echo "</div>";

echo "</body></html>";
?>
