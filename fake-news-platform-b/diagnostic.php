<?php
/**
 * diagnostic.php — Vérifier l'installation de la base de données
 */

require_once __DIR__ . '/includes/config.php';

echo "<h1>🔍 Diagnostic d'installation</h1>";
echo "<pre style='background:#f5f5f5;padding:20px;border-radius:8px;font-family:monospace;'>";

try {
    $db = getDB();
    echo "✓ Connexion MySQL OK\n\n";
    
    // Vérifier si la table users existe
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables trouvées: " . count($tables) . "\n";
    
    if (in_array('users', $tables)) {
        echo "✓ Table 'users' existe\n\n";
        
        // Lister tous les utilisateurs
        $users = $db->query("SELECT id, username, email, role FROM users")->fetchAll();
        if (count($users) > 0) {
            echo "Utilisateurs dans la base :\n";
            foreach ($users as $user) {
                echo "  - {$user['username']} ({$user['role']}) — {$user['email']}\n";
            }
        } else {
            echo "✗ AUCUN UTILISATEUR TROUVÉ !\n";
            echo "\n📌 Solution : Accédez à install.php pour créer les utilisateurs par défaut\n";
        }
    } else {
        echo "✗ Table 'users' N'EXISTE PAS !\n";
        echo "\n📌 Solution : Accédez à install.php pour installer la base de données\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    echo "\n📌 La base de données n'est pas accessible.\n";
    echo "Vérifiez votre configuration dans includes/config.php\n";
}

echo "</pre>";

echo "<h2>Actions recommandées :</h2>";
echo "<ol>";
echo "<li><a href='install.php' style='color:blue;text-decoration:underline;'><strong>1. Accédez à install.php</strong></a> pour initialiser la base de données</li>";
echo "<li><a href='reset_password.php' style='color:blue;text-decoration:underline;'><strong>2. Puis à reset_password.php</strong></a> pour réinitialiser admin</li>";
echo "<li><a href='login.php' style='color:blue;text-decoration:underline;'><strong>3. Connectez-vous à login.php</strong></a> avec admin / admin123</li>";
echo "</ol>";
?>
