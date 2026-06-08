<?php
/**
 * install.php — Installation automatique de la base de données
 * Accéder via : http://localhost/fake-news-platform/install.php
 * À supprimer ou protéger après le premier lancement.
 */

// ── Paramètres de connexion ────────────────────────────────────────
$host    = 'localhost';
$user    = 'root';
$pass    = '';           // Laisser vide pour WAMP par défaut
$dbName  = 'fake_news_platform';

$errors  = [];
$success = [];

// ── Connexion initiale (sans base) ────────────────────────────────
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $success[] = "✓ Connexion MySQL établie";
} catch (PDOException $e) {
    $errors[] = "✗ Connexion MySQL impossible : " . $e->getMessage();
    $pdo = null;
}

if ($pdo) {
    // Créer la base de données
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        $success[] = "✓ Base de données '$dbName' créée / vérifiée";
    } catch (PDOException $e) {
        $errors[] = "✗ Création DB : " . $e->getMessage();
    }

    // Importer le schema SQL
    $sqlFile = __DIR__ . '/database/schema.sql';
    if (file_exists($sqlFile)) {
        try {
            $sql = file_get_contents($sqlFile);
            // Exécuter statement par statement
            $statements = array_filter(
                array_map('trim', explode(";\n", $sql)),
                fn($s) => !empty($s) && !str_starts_with(ltrim($s), '--')
            );
            foreach ($statements as $stmt) {
                if (trim($stmt)) {
                    try { $pdo->exec($stmt); } catch (PDOException $e) {
                        // Ignorer les erreurs "already exists"
                        if (strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate entry') === false) {
                            $errors[] = "⚠ SQL: " . substr($e->getMessage(), 0, 120);
                        }
                    }
                }
            }
            $success[] = "✓ Schema SQL importé avec succès";
        } catch (Exception $e) {
            $errors[] = "✗ Import SQL : " . $e->getMessage();
        }
    } else {
        $errors[] = "✗ Fichier schema.sql introuvable";
    }

    // Vérifier que le compte admin existe
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'");
        if ($stmt->fetchColumn() > 0) {
            $success[] = "✓ Compte admin disponible (admin / admin123)";
        } else {
            $pdo->exec("INSERT INTO users (username, email, password, full_name, role) VALUES
                ('admin','admin@fakenews.mr','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uivHy/Ivq','Administrateur Système','admin')");
            $success[] = "✓ Compte admin créé (admin / admin123)";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠ Vérification admin : " . $e->getMessage();
    }
}

$allOk = empty($errors);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Installation — Détection du Fake News</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #F8F9FC; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .card { background: #fff; border: 1px solid #E8EAF0; border-radius: 16px; padding: 40px 44px; max-width: 520px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
    .logo-title { font-size: 18px; font-weight: 800; color: #0F1117; }
    .logo-title span { color: #DC2626; }
    h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
    .sub { color: #9AA0B4; font-size: 13px; margin-bottom: 24px; }
    .steps { display: flex; flex-direction: column; gap: 8px; margin-bottom: 28px; }
    .step { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; }
    .step.ok  { background: #ECFDF5; color: #059669; }
    .step.err { background: #FEF2F2; color: #DC2626; }
    .btn { display: block; width: 100%; padding: 12px; background: #2563EB; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; transition: background .15s; }
    .btn:hover { background: #1D4ED8; }
    .btn-gray { background: #F3F4F8; color: #5B6278; margin-top: 8px; }
    .btn-gray:hover { background: #E8EAF0; }
    .warn { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; color: #92400E; margin-top: 16px; line-height: 1.6; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
      <circle cx="20" cy="20" r="19" fill="#0F1117"/>
      <circle cx="17" cy="16" r="7" fill="none" stroke="#fff" stroke-width="2.5"/>
      <line x1="22" y1="21" x2="29" y2="28" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
      <polyline points="13,16 16,19 21,13" fill="none" stroke="#22C55E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="logo-title">DÉTECTION DU <span>FAKE NEWS</span></div>
  </div>

  <h1><?= $allOk ? '🎉 Installation réussie !' : '⚠ Installation partielle' ?></h1>
  <p class="sub">Plateforme de surveillance des publications Facebook</p>

  <div class="steps">
    <?php foreach ($success as $msg): ?>
      <div class="step ok"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg): ?>
      <div class="step err"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>
  </div>

  <?php if ($allOk): ?>
    <a href="login.php" class="btn">Accéder à la plateforme →</a>
    <div class="warn">
      ⚠ <strong>Sécurité :</strong> Supprimez ce fichier après installation.<br>
      <code>Supprimez : /fake-news-platform/install.php</code>
    </div>
  <?php else: ?>
    <a href="install.php" class="btn">Réessayer l'installation</a>
    <a href="#" class="btn btn-gray">Voir la documentation</a>
  <?php endif; ?>
</div>
</body>
</html>
