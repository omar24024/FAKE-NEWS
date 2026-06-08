<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/review_service.php';
requireLogin();

$user = getCurrentUser();
$metrics = getEvaluationMetrics();
$currentPage = 'evaluation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Évaluation IA — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Évaluation du modèle IA</div>
        <div class="page-subtitle">Métriques GBERT Hassaniya vs validation humaine (Facebook — texte & commentaires)</div>
      </div>
      <div class="topbar-right">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
          <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="alert alert-info" style="margin-bottom:16px;font-size:13px;line-height:1.6;">
        <strong>Méthode —</strong> Chaque validation sur la page <em>Détails</em> (Confirmer / Corriger / Rejeter) alimente ce tableau.
        Corpus : publications Facebook en <strong>Hassaniya / arabe</strong> uniquement. Pas d'analyse image, audio ou vidéo.
      </div>

      <?php if ($metrics['total_reviews'] === 0): ?>
        <div class="detail-card">
          <div class="empty-state">
            <p>Aucune validation humaine enregistrée.</p>
            <p style="margin-top:8px;font-size:13px;color:var(--text-muted);">
              Analysez des publications, puis validez-les depuis la page Détails pour calculer precision, recall et F1.
            </p>
            <a href="<?= APP_URL ?>/pages/publications.php?filter=analyzed" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">Voir les publications analysées</a>
          </div>
        </div>
      <?php else: ?>
        <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
          <div class="stat-card">
            <span class="stat-label">Validations</span>
            <div class="stat-value"><?= (int)$metrics['total_reviews'] ?></div>
          </div>
          <div class="stat-card">
            <span class="stat-label">Exactitude</span>
            <div class="stat-value"><?= $metrics['accuracy'] ?>%</div>
          </div>
          <div class="stat-card">
            <span class="stat-label">Précision (macro)</span>
            <div class="stat-value"><?= $metrics['precision_macro'] ?? '—' ?><?= $metrics['precision_macro'] !== null ? '%' : '' ?></div>
          </div>
          <div class="stat-card">
            <span class="stat-label">Rappel (macro)</span>
            <div class="stat-value"><?= $metrics['recall_macro'] ?? '—' ?><?= $metrics['recall_macro'] !== null ? '%' : '' ?></div>
          </div>
          <div class="stat-card">
            <span class="stat-label">F1 (macro)</span>
            <div class="stat-value"><?= $metrics['f1_macro'] ?? '—' ?><?= $metrics['f1_macro'] !== null ? '%' : '' ?></div>
          </div>
        </div>

        <div class="detail-card" style="margin-bottom:16px;">
          <div class="detail-card-title">Détection binaire (contenu nocif vs fiable)</div>
          <div style="font-size:28px;font-weight:800;color:var(--accent);"><?= $metrics['binary_harmful_accuracy'] ?>%</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Exactitude harmful / reliable agrégée</div>
        </div>

        <div class="detail-card" style="margin-bottom:16px;">
          <div class="detail-card-title">Par catégorie</div>
          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="border-bottom:1px solid var(--border);text-align:left;">
                  <th style="padding:10px 8px;">Catégorie</th>
                  <th style="padding:10px 8px;">Précision</th>
                  <th style="padding:10px 8px;">Rappel</th>
                  <th style="padding:10px 8px;">F1</th>
                  <th style="padding:10px 8px;">Support</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($metrics['by_category'] as $cat => $m): ?>
                  <?php if (($m['support'] ?? 0) === 0 && ($m['precision'] ?? null) === null) continue; ?>
                  <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:10px 8px;"><?= categoryLabel($cat) ?></td>
                    <td style="padding:10px 8px;"><?= $m['precision'] !== null ? $m['precision'] . '%' : '—' ?></td>
                    <td style="padding:10px 8px;"><?= $m['recall'] !== null ? $m['recall'] . '%' : '—' ?></td>
                    <td style="padding:10px 8px;"><?= $m['f1'] !== null ? $m['f1'] . '%' : '—' ?></td>
                    <td style="padding:10px 8px;"><?= (int)($m['support'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if (!empty($metrics['recent_reviews'])): ?>
        <div class="detail-card">
          <div class="detail-card-title">Dernières validations humaines</div>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($metrics['recent_reviews'] as $rev): ?>
              <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$rev['id'] ?>" style="text-decoration:none;color:inherit;border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;display:block;">
                <div style="font-size:12px;color:var(--text-muted);"><?= reviewStatusLabel((int)$rev['manual_review']) ?> · <?= $rev['reviewed_at'] ? formatDate($rev['reviewed_at']) : '' ?></div>
                <div style="font-size:13px;margin-top:6px;color:var(--text-secondary);">
                  IA : <?= categoryLabel($rev['ai_category_original'] ?? $rev['category']) ?>
                  → Humain : <strong><?= categoryLabel($rev['human_category'] ?? $rev['category']) ?></strong>
                </div>
                <div style="font-size:12px;margin-top:6px;color:var(--text-muted);"><?= htmlspecialchars(mb_substr($rev['content'] ?? '', 0, 120)) ?>…</div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
