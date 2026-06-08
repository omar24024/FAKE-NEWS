<?php
// includes/sidebar.php — Barre de navigation latérale
$currentPage = $currentPage ?? '';
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <?php if (is_file(__DIR__ . '/../' . APP_LOGO_PATH)): ?>
      <img src="<?= APP_URL ?>/<?= APP_LOGO_PATH ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
    <?php else: ?>
      <!-- Fallback logo (SVG) -->
      <svg width="42" height="42" viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
        <circle cx="21" cy="21" r="20" fill="#0F1117" stroke="#E8EAF0" stroke-width="1"/>
        <circle cx="18" cy="18" r="8" fill="none" stroke="#FFFFFF" stroke-width="2.5"/>
        <line x1="24" y1="24" x2="31" y2="31" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round"/>
        <polyline points="14,18 17,21 23,15" fill="none" stroke="#22C55E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="14" y1="32" x2="17" y2="35" stroke="#EF4444" stroke-width="2.2" stroke-linecap="round"/>
        <line x1="17" y1="32" x2="14" y2="35" stroke="#EF4444" stroke-width="2.2" stroke-linecap="round"/>
      </svg>
    <?php endif; ?>
    <div class="sidebar-logo-text">
      <span class="brand-top">Plateforme</span>
      <span class="brand-main">DÉTECTION DU<br><span>FAKE NEWS</span></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
      Tableau de bord
    </a>

    <a href="<?= APP_URL ?>/pages/publications.php" class="nav-item <?= $currentPage==='publications'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      Publications
    </a>

    <a href="<?= APP_URL ?>/pages/comptes.php" class="nav-item <?= $currentPage==='comptes'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Comptes
    </a>

    <a href="<?= APP_URL ?>/pages/analyse.php" class="nav-item <?= $currentPage==='analyse'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
      Analyse
    </a>

    <a href="<?= APP_URL ?>/pages/evaluation.php" class="nav-item <?= $currentPage==='evaluation'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
      Évaluation IA
    </a>

    <a href="<?= APP_URL ?>/pages/rapports.php" class="nav-item <?= $currentPage==='rapports'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      Rapports
    </a>

    <a href="<?= APP_URL ?>/pages/notifications.php" class="nav-item <?= $currentPage==='notifications'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      Notifications
    </a>

    <a href="<?= APP_URL ?>/pages/scraper.php" class="nav-item <?= $currentPage==='scraper'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
      Scraper OSINT
    </a>

    <a href="<?= APP_URL ?>/pages/parametres.php" class="nav-item <?= $currentPage==='parametres'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Paramètres
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/logout.php" class="nav-item" style="color: #DC2626;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Déconnexion
    </a>
  </div>
</aside>
