<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Identifiants incorrects. Vérifiez votre nom d\'utilisateur et mot de passe.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — Détection du Fake News</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .login-page-bg {
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(37,99,235,.06) 0%, transparent 70%);
    }
    .login-card { position: relative; z-index: 1; }
  </style>
</head>
<body>
<div class="login-page-bg"></div>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <?php if (is_file(__DIR__ . '/' . APP_LOGO_PATH)): ?>
        <img src="<?= APP_URL ?>/<?= APP_LOGO_PATH ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
      <?php else: ?>
        <svg width="72" height="72" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg">
          <circle cx="36" cy="36" r="35" fill="#0F1117"/>
          <circle cx="31" cy="30" r="13" fill="none" stroke="#FFFFFF" stroke-width="4"/>
          <line x1="41" y1="40" x2="53" y2="52" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
          <polyline points="24,30 29,35 39,25" fill="none" stroke="#22C55E" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="24" y1="58" x2="29" y2="63" stroke="#EF4444" stroke-width="3.5" stroke-linecap="round"/>
          <line x1="29" y1="58" x2="24" y2="63" stroke="#EF4444" stroke-width="3.5" stroke-linecap="round"/>
        </svg>
      <?php endif; ?>
      <span class="login-brand-top">Plateforme institutionnelle</span>
      <span class="login-brand-main">DÉTECTION DU <span>FAKE NEWS</span></span>
      <span class="login-subtitle">Système de surveillance et d'analyse des publications Facebook</span>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="username">Nom d'utilisateur ou email</label>
        <input class="form-input" type="text" id="username" name="username"
               placeholder="Entrez votre identifiant"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Mot de passe</label>
        <input class="form-input" type="password" id="password" name="password"
               placeholder="Entrez votre mot de passe"
               autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">Se connecter</button>
    </form>
  </div>
</div>
</body>
</html>
