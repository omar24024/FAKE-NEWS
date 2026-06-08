<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser();
$currentPage = 'rapports';
$db = getDB();
$stats = getStats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapports — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Rapports</div>
        <div class="page-subtitle">Génération et export de rapports d'analyse</div>
      </div>
      <div class="topbar-right">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
          <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
        </div>
      </div>
    </div>
    <div class="content">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">

        <div class="detail-card" style="cursor:pointer;" onclick="window.location='../api/export.php?format=csv'">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:#ECFDF5;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2" style="width:22px;height:22px;">
                <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
            </div>
            <div>
              <div style="font-weight:700;font-size:14px;">Export CSV complet</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Toutes les publications analysées</div>
            </div>
          </div>
        </div>

        <div class="detail-card" style="cursor:pointer;" onclick="window.location='../api/export.php?format=csv&risk=critical'">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:#FEF2F2;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2" style="width:22px;height:22px;">
                <path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              </svg>
            </div>
            <div>
              <div style="font-weight:700;font-size:14px;">Rapport d'alerte</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Contenus à risque critique</div>
            </div>
          </div>
        </div>

        <div class="detail-card" style="cursor:pointer;" onclick="window.location='../api/export.php?format=csv&range=month'">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:#EFF4FF;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#2563EB" stroke-width="2" style="width:22px;height:22px;">
                <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div>
              <div style="font-weight:700;font-size:14px;">Rapport mensuel</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Synthèse du mois en cours</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary stats -->
      <div class="detail-card">
        <div class="detail-card-title">Résumé statistique global</div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;padding:8px 0;">
          <?php
          $items = [
            ['Total', $stats['total'], 'var(--accent)'],
            ['Fake news', $stats['fake'], 'var(--fake-color)'],
            ['Désinformation', $stats['disinfo'], 'var(--disinfo-color)'],
            ['Haine/Discours', $stats['hate'], 'var(--hate-color)'],
            ['Fiables', $stats['reliable'], 'var(--reliable-color)'],
          ];
          foreach ($items as [$label, $val, $color]): ?>
          <div style="text-align:center;">
            <div style="font-size:36px;font-weight:800;color:<?= $color ?>;letter-spacing:-.03em;"><?= number_format($val) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
