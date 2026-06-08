<?php
/**
 * fix_admin_password.php — Réinitialiser le mot de passe admin
 */

require_once __DIR__ . '/includes/config.php';

$newPassword = 'admin123';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if ($exists) {
        $db->prepare("UPDATE users SET password = ?, is_active = 1 WHERE username = 'admin'")
           ->execute([$newHash]);
    } else {
        $db->prepare("
            INSERT INTO users (username, email, password, full_name, role, is_active)
            VALUES ('admin', 'admin@fakenews.mr', ?, 'Administrateur Système', 'admin', 1)
        ")->execute([$newHash]);
    }

    echo "<div style='background:#ECFDF5;border:1px solid #6EE7B7;padding:20px;border-radius:8px;font-family:sans-serif;color:#065F46;margin:20px;'>";
    echo "<h2>✓ Mot de passe admin réinitialisé !</h2>";
    echo "<p><strong>Identifiants :</strong></p>";
    echo "<ul><li>Utilisateur : <code>admin</code></li><li>Mot de passe : <code>admin123</code></li></ul>";
    echo "<p><a href='login.php'>→ Se connecter</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background:#FEF2F2;border:1px solid #FCA5A5;padding:20px;border-radius:8px;color:#991B1B;margin:20px;'>";
    echo "<strong>✗ Erreur :</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
