<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Stats for charts
$categoryStats = $db->query("SELECT category, COUNT(*) as cnt, AVG(confidence_score) as avg_conf FROM ai_analysis GROUP BY category")->fetchAll();
$riskStats = $db->query("SELECT risk_level, COUNT(*) as cnt FROM ai_analysis GROUP BY risk_level")->fetchAll();
$dailyStats = $db->query("SELECT DATE(p.published_at) as day, COUNT(*) as cnt FROM facebook_posts p JOIN ai_analysis a ON p.id = a.post_id WHERE p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(p.published_at) ORDER BY day")->fetchAll();

$currentPage = 'analyse';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Analyse — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
    .analysis-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .bar-chart { display: flex; flex-direction: column; gap: 12px; }
    .bar-row { display: flex; align-items: center; gap: 12px; }
    .bar-label { width: 130px; font-size: 12.5px; color: var(--text-secondary); flex-shrink: 0; }
    .bar-track { flex: 1; height: 8px; background: var(--surface-2); border-radius: 99px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 99px; transition: width .8s ease; }
    .bar-val { width: 40px; text-align: right; font-size: 12px; font-weight: 700; color: var(--text-primary); }
  </style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Analyse</div>
        <div class="page-subtitle">Statistiques et tendances des contenus détectés</div>
      </div>
      <div class="topbar-right">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
          <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
        </div>
      </div>
    </div>
    <div class="content">

      <!-- Analyse par catégorie -->
      <div class="analysis-grid">
        <div class="detail-card">
          <div class="detail-card-title">Répartition par catégorie</div>
          <?php
          $total = array_sum(array_column($categoryStats, 'cnt'));
          $catColors = ['fake_news'=>'var(--fake-color)','disinformation'=>'var(--disinfo-color)','hate_speech'=>'var(--hate-color)','reliable'=>'var(--reliable-color)'];
          ?>
          <div class="bar-chart">
            <?php foreach ($categoryStats as $row): ?>
            <div class="bar-row">
              <div class="bar-label"><?= categoryLabel($row['category']) ?></div>
              <div class="bar-track">
                <div class="bar-fill" data-width="<?= $total ? round($row['cnt']/$total*100) : 0 ?>" style="width:0%;background:<?= $catColors[$row['category']] ?? 'var(--accent)' ?>;"></div>
              </div>
              <div class="bar-val"><?= $row['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="detail-card">
          <div class="detail-card-title">Score de confiance moyen</div>
          <div class="bar-chart">
            <?php foreach ($categoryStats as $row): ?>
            <div class="bar-row">
              <div class="bar-label"><?= categoryLabel($row['category']) ?></div>
              <div class="bar-track">
                <div class="bar-fill" data-width="<?= round($row['avg_conf']) ?>" style="width:0%;background:<?= $catColors[$row['category']] ?? 'var(--accent)' ?>;"></div>
              </div>
              <div class="bar-val"><?= round($row['avg_conf']) ?>%</div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Niveau de risque -->
      <div class="detail-card" style="margin-bottom:16px;">
        <div class="detail-card-title">Distribution des niveaux de risque</div>
        <?php
        $riskMap = ['critical'=>['Critique','#DC2626'],'high'=>['Élevé','#F59E0B'],'medium'=>['Modéré','#3B82F6'],'low'=>['Faible','#10B981']];
        $totalRisk = array_sum(array_column($riskStats, 'cnt'));
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
          <?php foreach ($riskStats as $row): ?>
          <?php [$label, $color] = $riskMap[$row['risk_level']] ?? ['Inconnu','#9AA0B4']; ?>
          <div style="text-align:center;padding:16px;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border);">
            <div style="font-size:28px;font-weight:800;color:<?= $color ?>;"><?= $row['cnt'] ?></div>
            <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-top:4px;"><?= $label ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= $totalRisk ? round($row['cnt']/$totalRisk*100) : 0 ?>%</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Daily stats -->
      <?php if (!empty($dailyStats)): ?>
      <div class="detail-card">
        <div class="detail-card-title">Publications analysées (7 derniers jours)</div>
        <div style="display:flex;align-items:flex-end;gap:12px;height:120px;padding-top:8px;">
          <?php
          $maxDay = max(array_column($dailyStats, 'cnt'));
          foreach ($dailyStats as $day):
            $h = $maxDay ? round($day['cnt'] / $maxDay * 100) : 0;
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div style="font-size:11px;font-weight:700;color:var(--text-secondary);"><?= $day['cnt'] ?></div>
            <div style="width:100%;height:<?= $h ?>px;background:var(--accent);border-radius:4px 4px 0 0;min-height:4px;transition:height .5s ease;"></div>
            <div style="font-size:10px;color:var(--text-muted);"><?= date('d/m', strtotime($day['day'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
// Animate bars
document.querySelectorAll('.bar-fill[data-width]').forEach(el => {
  setTimeout(() => { el.style.width = el.dataset.width + '%'; }, 300);
});
</script>
</body>
</html>
