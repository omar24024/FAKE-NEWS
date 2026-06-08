<?php
/**
 * reset_password.php — Réinitialiser le mot de passe admin
 * À supprimer après utilisation
 */

require_once __DIR__ . '/includes/config.php';

try {
    $db = getDB();
    
    // Hacher le nouveau mot de passe
    $newPassword = 'admin123';
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Mettre à jour
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hashedPassword]);
    
    if ($result) {
        echo "<div style='background:#ECFDF5;border:1px solid #6EE7B7;padding:20px;border-radius:8px;font-family:sans-serif;color:#065F46;'>";
        echo "<strong>✓ Succès !</strong><br>";
        echo "Mot de passe réinitialisé pour admin<br><br>";
        echo "<strong>Identifiants :</strong><br>";
        echo "Nom d'utilisateur : <code>admin</code><br>";
        echo "Mot de passe : <code>admin123</code><br><br>";
        echo "<a href='login.php' style='color:#059669;text-decoration:underline;'>Aller à la connexion →</a>";
        echo "</div>";
    } else {
        echo "<div style='background:#FEF2F2;border:1px solid #FCA5A5;padding:20px;border-radius:8px;color:#991B1B;'>";
        echo "<strong>✗ Erreur</strong> : Impossible de mettre à jour le mot de passe";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background:#FEF2F2;border:1px solid #FCA5A5;padding:20px;border-radius:8px;color:#991B1B;'>";
    echo "<strong>✗ Erreur</strong> : " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
