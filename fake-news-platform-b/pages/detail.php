<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_client.php';
require_once __DIR__ . '/../includes/review_service.php';
requireLogin();

$user = getCurrentUser();
$notifCount = getNotificationCount($user['id']);
$postId = (int)($_GET['id'] ?? 0);
$post = $postId ? getPostDetails($postId) : null;

if (!$post) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}
$currentPage = 'publications';
$cat = $post['category'] ?? 'reliable';
$risk = $post['risk_level'] ?? 'low';
$riskPct = match($risk) { 'critical' => 100, 'high' => 75, 'medium' => 50, 'low' => 25, default => 0 };
$modelName = modelLabel($post['model_used'] ?? null);
$isGbert = isGbertModel($post['model_used'] ?? null);
$aiOnline = aiServiceAvailable();
$manualReview = (int)($post['manual_review'] ?? 0);
$reviewPending = !empty($post['analysis_id']) && $manualReview === 0;
$reviewDone = $manualReview > 0;
$invalidContent = isInvalidScrapedContent($post['content'] ?? '', $post['author_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détails publication — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Détails de la publication</div>
        <div class="page-subtitle">Analyse complète et rapport IA</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="location.href='<?= APP_URL ?>/pages/notifications.php'">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <?php if ($notifCount > 0): ?>
            <span class="notif-badge"><?= $notifCount ?></span>
          <?php endif; ?>
        </div>
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
          <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="alert alert-info" style="margin-bottom:16px;font-size:13px;line-height:1.6;">
        <strong>Aide à l'analyse —</strong> Cette plateforme assiste l'analyste humain. Les résultats IA (GBERT Hassaniya) ne constituent pas une décision automatique et doivent être validés manuellement.
      </div>

      <?php if ($invalidContent): ?>
      <div class="alert alert-error" style="margin-bottom:16px;font-size:13px;line-height:1.6;">
        <strong>Extraction invalide —</strong> Cette publication contient la page de connexion Facebook, pas le vrai contenu. Supprimez-la et réextrayez avec une <strong>URL publique</strong> (visible sans login).
      </div>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/index.php" class="back-link">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M15 19l-7-7 7-7"/>
        </svg>
        Retour au tableau de bord
      </a>

      <div class="detail-grid">
        <!-- Main column -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- Publication content -->
          <div class="detail-card">
            <div class="detail-card-title">Contenu de la publication</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
              <span class="badge badge-default"><?= contentTypeLabel($post['content_type'] ?? 'text') ?></span>
              <span class="badge <?= linkStatusClass($post['link_status'] ?? 'unknown') ?>">Lien : <?= linkStatusLabel($post['link_status'] ?? 'unknown') ?></span>
            </div>
            <?php if ($post['image_url']): ?>
              <img class="detail-post-image" src="<?= htmlspecialchars($post['image_url']) ?>" alt="Image publication" onerror="this.style.display='none'">
            <?php endif; ?>
            <p class="detail-post-text"><?= nl2br(htmlspecialchars($post['content'] ?? '')) ?></p>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
              <a href="<?= htmlspecialchars($post['fb_post_url'] ?? '#') ?>" target="_blank" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Voir sur Facebook
              </a>
              <a href="../api/export.php?format=pdf&post_id=<?= $post['id'] ?>" class="btn btn-ghost">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                  <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Télécharger rapport
              </a>
            </div>
          </div>

          <!-- Mots-clés détectés -->
          <?php if (!empty($post['keywords'])): ?>
          <div class="detail-card">
            <div class="detail-card-title">Mots-clés détectés par l'IA</div>
            <div class="keyword-chips">
              <?php foreach ($post['keywords'] as $kw): ?>
                <span class="keyword-chip">
                  <?= htmlspecialchars($kw['keyword']) ?>
                  <span class="weight"><?= number_format($kw['weight'] * 100, 0) ?>%</span>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Commentaires analysés -->
          <?php if (!empty($post['comments'])): ?>
          <div class="detail-card">
            <div class="detail-card-title">Commentaires analysés par GBERT (<?= count($post['comments']) ?>)</div>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;line-height:1.6;">
              Texte des commentaires extraits sous la publication — chaque commentaire est classifié séparément.
            </p>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <?php foreach ($post['comments'] as $comment): ?>
                <?php
                  $cCat = $comment['category'] ?? 'reliable';
                  $cAnalyzed = !empty($comment['is_analyzed']);
                ?>
                <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;background:var(--surface-2);">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <div>
                      <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($comment['author_name'] ?? 'Anonyme') ?></div>
                      <?php if ($cAnalyzed): ?>
                        <span class="badge <?= categoryClass($cCat) ?>" style="font-size:11px;margin-top:6px;display:inline-block;">
                          <?= categoryLabel($cCat) ?> · <?= number_format((float)($comment['confidence_score'] ?? 0), 0) ?>%
                        </span>
                      <?php else: ?>
                        <span class="badge badge-default" style="font-size:11px;margin-top:6px;display:inline-block;">En attente d'analyse</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($cAnalyzed && !empty($comment['risk_level'])): ?>
                      <span style="font-size:11px;color:var(--text-muted);">Risque : <?= riskLabel($comment['risk_level']) ?></span>
                    <?php endif; ?>
                  </div>
                  <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0;white-space:pre-wrap;"><?= htmlspecialchars($comment['content'] ?? '') ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php elseif ((int)($post['comments_count'] ?? 0) > 0): ?>
          <div class="detail-card">
            <div class="detail-card-title">Commentaires</div>
            <p style="font-size:13px;color:var(--text-muted);margin:0;line-height:1.6;">
              <?= (int)$post['comments_count'] ?> commentaire(s) détecté(s) sur Facebook, mais le texte n'a pas pu être extrait.
              Réextrayez la publication avec une URL publique pour capturer les commentaires.
            </p>
          </div>
          <?php endif; ?>

          <!-- Politiques Facebook -->
          <?php if (!empty($post['facebook_policies'])): ?>
          <div class="detail-card">
            <div class="detail-card-title">Politiques Facebook (Meta Community Standards)</div>
            <?php foreach ($post['facebook_policies'] as $policy): ?>
              <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                  <span style="background:#1877F2;color:#fff;font-size:11px;font-weight:700;padding:2px 10px;border-radius:99px;font-family:var(--mono);"><?= htmlspecialchars($policy['policy_code']) ?></span>
                  <span style="font-size:13.5px;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($policy['title']) ?></span>
                </div>
                <p style="font-size:12.5px;color:var(--text-secondary);line-height:1.6;"><?= htmlspecialchars($policy['description'] ?? '') ?></p>
                <?php if (!empty($policy['source_url'])): ?>
                <a href="<?= htmlspecialchars($policy['source_url']) ?>" target="_blank" rel="noopener" style="font-size:11.5px;color:#1877F2;margin-top:4px;display:inline-block;">Voir sur Meta Transparency →</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Références légales -->
          <?php if (!empty($post['legal_refs'])): ?>
          <div class="detail-card">
            <div class="detail-card-title">Textes juridiques applicables (Mauritanie)</div>
            <?php foreach ($post['legal_refs'] as $law): ?>
              <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                  <span style="background:var(--accent-light);color:var(--accent);font-size:11px;font-weight:700;padding:2px 10px;border-radius:99px;font-family:var(--mono);"><?= htmlspecialchars($law['reference_code']) ?></span>
                  <span style="font-size:13.5px;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($law['title']) ?></span>
                </div>
                <p style="font-size:12.5px;color:var(--text-secondary);line-height:1.6;"><?= htmlspecialchars($law['description'] ?? '') ?></p>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($law['source'] ?? '') ?> · <?= $law['year'] ?? '' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right column -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- Analyse IA -->
          <div class="detail-card">
            <div class="detail-card-title">Analyse IA</div>

            <?php if ($isGbert): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;margin-bottom:12px;background:linear-gradient(135deg,#1a237e 0%,#283593 100%);border-radius:var(--radius-sm);color:#fff;">
              <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0;">GB</div>
              <div>
                <div style="font-weight:700;font-size:14px;letter-spacing:.02em;">GBERT Hassaniya</div>
                <div style="font-size:11px;opacity:.85;margin-top:2px;">AraBERT + AraGPT2 · Darija & Hassaniya</div>
              </div>
              <span style="margin-left:auto;font-size:10px;padding:3px 8px;border-radius:99px;background:<?= $aiOnline ? '#2e7d32' : '#f57c00' ?>;font-weight:600;">
                <?= $aiOnline ? 'API active' : 'Mode CLI' ?>
              </span>
            </div>
            <?php endif; ?>

            <div style="text-align:center;padding:16px 0 20px;">
              <span class="badge <?= categoryClass($cat) ?>" style="font-size:14px;padding:6px 20px;">
                <?= categoryLabel($cat) ?>
              </span>
              <div style="margin-top:16px;">
                <div style="font-size:48px;font-weight:800;letter-spacing:-.04em;color:var(--text-primary);font-variant-numeric:tabular-nums;" id="confScore">0%</div>
                <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Score de confiance</div>
              </div>
              <div class="conf-bar" style="width:100%;height:8px;margin-top:12px;">
                <div class="conf-bar-fill" id="confBar" style="width:0%;background:<?= match($cat) {
                  'fake_news' => 'var(--fake-color)',
                  'disinformation' => 'var(--disinfo-color)',
                  'hate_speech' => 'var(--hate-color)',
                  default => 'var(--reliable-color)'
                } ?>;transition:width .8s ease;"></div>
              </div>
            </div>
            <div class="meta-row">
              <span class="meta-label">Niveau de risque</span>
              <span class="meta-value"><?= riskLabel($risk) ?></span>
            </div>
            <div class="risk-bar">
              <div class="risk-bar-fill <?= $risk ?>" id="riskBar" style="width:0%;"></div>
            </div>
            <div class="meta-row" style="margin-top:8px;">
              <span class="meta-label">Modèle IA</span>
              <span class="meta-value" style="font-size:12px;font-weight:600;color:<?= $isGbert ? '#283593' : 'inherit' ?>;">
                <?= htmlspecialchars($modelName) ?>
              </span>
            </div>
            <?php if (!empty($post['model_used'])): ?>
            <div class="meta-row">
              <span class="meta-label">Identifiant technique</span>
              <span class="meta-value" style="font-family:var(--mono);font-size:10px;color:var(--text-muted);word-break:break-all;">
                <?= htmlspecialchars($post['model_used']) ?>
              </span>
            </div>
            <?php endif; ?>
          </div>

          <!-- Validation humaine -->
          <?php if (!empty($post['analysis_id'])): ?>
          <div class="detail-card" id="humanReviewCard">
            <div class="detail-card-title">Validation humaine (analyste)</div>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;line-height:1.6;">
              L'IA GBERT Hassaniya propose une catégorie — l'analyste confirme, corrige ou rejette. Décision finale humaine.
            </p>

            <?php if ($reviewDone): ?>
              <div style="padding:12px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:var(--radius-sm);margin-bottom:12px;">
                <div style="font-weight:700;font-size:13px;color:#065f46;"><?= htmlspecialchars(reviewStatusLabel($manualReview)) ?></div>
                <?php if (!empty($post['human_category'])): ?>
                  <div style="font-size:12px;color:#047857;margin-top:6px;">
                    Catégorie retenue : <strong><?= categoryLabel($post['human_category']) ?></strong>
                    <?php if (!empty($post['ai_category_original']) && $post['ai_category_original'] !== $post['human_category']): ?>
                      (IA avait prédit : <?= categoryLabel($post['ai_category_original']) ?>)
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($post['reviewer_name'])): ?>
                  <div style="font-size:11px;color:#6b7280;margin-top:4px;">
                    Par <?= htmlspecialchars($post['reviewer_name']) ?>
                    <?= !empty($post['reviewed_at']) ? ' · ' . formatDate($post['reviewed_at']) : '' ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($post['analysis_notes'])): ?>
                  <div style="font-size:12px;color:#374151;margin-top:8px;font-style:italic;">« <?= htmlspecialchars($post['analysis_notes']) ?> »</div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-ghost w-full" style="justify-content:center;font-size:12px;" onclick="document.getElementById('reviewForm').style.display='block';this.style.display='none';">
                Modifier la validation
              </button>
            <?php endif; ?>

            <div id="reviewForm" style="<?= $reviewDone ? 'display:none;' : '' ?>">
              <div class="meta-row" style="margin-bottom:10px;">
                <span class="meta-label">Prédiction IA</span>
                <span class="meta-value"><span class="badge <?= categoryClass($cat) ?>"><?= categoryLabel($cat) ?></span></span>
              </div>
              <div class="form-group" id="correctCategoryGroup" style="display:none;margin-bottom:10px;">
                <label class="form-label">Catégorie corrigée (Hassaniya / arabe)</label>
                <select class="form-input" id="humanCategory">
                  <option value="fake_news">Fake news</option>
                  <option value="disinformation">Désinformation</option>
                  <option value="hate_speech">Discours de haine</option>
                  <option value="cyberbullying">Cyberharcèlement</option>
                  <option value="misinformation">Misinformation</option>
                  <option value="reliable">Information fiable</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Notes analyste (optionnel)</label>
                <textarea class="form-input" id="reviewNotes" rows="2" placeholder="Justification en Hassaniya ou arabe…"></textarea>
              </div>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <button type="button" class="btn btn-primary w-full" style="justify-content:center;" onclick="submitReview('confirm')">Confirmer l'analyse IA</button>
                <button type="button" class="btn btn-ghost w-full" style="justify-content:center;" onclick="toggleCorrectForm()">Corriger la catégorie</button>
                <button type="button" class="btn btn-ghost w-full" id="saveCorrectBtn" style="display:none;justify-content:center;" onclick="submitReview('correct')">Enregistrer la correction</button>
                <button type="button" class="btn btn-ghost w-full" style="justify-content:center;color:#b91c1c;border-color:#fecaca;" onclick="submitReview('reject')">Rejeter (faux positif)</button>
              </div>
            </div>
            <div id="reviewToast" style="display:none;margin-top:12px;font-size:13px;padding:10px;border-radius:var(--radius-sm);"></div>
          </div>
          <?php endif; ?>

          <!-- Informations auteur -->
          <div class="detail-card">
            <div class="detail-card-title">Informations sur l'auteur</div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
              <div style="width:48px;height:48px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                <?= avatarSVG(postAuthorName($post), 48) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars(postAuthorName($post)) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= $post['account_type'] === 'page' ? 'Page Facebook' : 'Compte Facebook' ?></div>
              </div>
            </div>
            <div class="meta-row">
              <span class="meta-label">Abonnés</span>
              <span class="meta-value"><?= number_format($post['followers_count'] ?? 0) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Date publication</span>
              <span class="meta-value"><?= formatDate($post['published_at'] ?? '') ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">J'aime</span>
              <span class="meta-value"><?= number_format($post['likes_count'] ?? 0) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Partages</span>
              <span class="meta-value"><?= number_format($post['shares_count'] ?? 0) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Commentaires</span>
              <span class="meta-value"><?= number_format($post['comments_count'] ?? 0) ?></span>
            </div>
            <?php if ($post['account_url']): ?>
            <div style="margin-top:12px;">
              <a href="<?= htmlspecialchars($post['account_url']) ?>" target="_blank" class="btn btn-ghost w-full" style="justify-content:center;">
                Voir le profil Facebook
              </a>
            </div>
            <?php endif; ?>
          </div>

          <!-- URL -->
          <div class="detail-card">
            <div class="detail-card-title">URL de la publication</div>
            <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 12px;font-family:var(--mono);font-size:11px;color:var(--text-secondary);word-break:break-all;line-height:1.6;">
              <?= htmlspecialchars($post['fb_post_url'] ?? 'Non disponible') ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
const conf = <?= number_format($post['confidence_score'] ?? 0, 2) ?>;
const riskPct = <?= $riskPct ?>;

// Animate confidence
setTimeout(() => {
  document.getElementById('confBar').style.width = conf + '%';
  document.getElementById('riskBar').style.width = riskPct + '%';
  let c = 0;
  const timer = setInterval(() => {
    c = Math.min(c + conf / 40, conf);
    document.getElementById('confScore').textContent = Math.floor(c) + '%';
    if (c >= conf) clearInterval(timer);
  }, 20);
}, 200);

function toggleCorrectForm() {
  const g = document.getElementById('correctCategoryGroup');
  const saveBtn = document.getElementById('saveCorrectBtn');
  const show = g.style.display === 'none';
  g.style.display = show ? 'block' : 'none';
  if (saveBtn) saveBtn.style.display = show ? 'flex' : 'none';
}

async function submitReview(decision) {
  const toast = document.getElementById('reviewToast');
  const notes = document.getElementById('reviewNotes').value.trim();
  const humanCategory = document.getElementById('humanCategory')?.value || '';

  if (decision === 'reject' && !notes) {
    alert('Veuillez indiquer pourquoi l\'analyse IA est rejetée.');
    return;
  }
  if (decision === 'confirm' && !confirm('Confirmer que l\'analyse IA est correcte ?')) return;
  if (decision === 'reject' && !confirm('Rejeter cette analyse (classer comme fiable) ?')) return;
  if (decision === 'correct' && !confirm('Enregistrer la catégorie corrigée ?')) return;

  const body = new URLSearchParams({
    action: 'submit',
    post_id: '<?= (int)$post['id'] ?>',
    decision: decision === 'correct' ? 'correct' : decision,
    notes: notes
  });
  if (decision === 'correct') body.set('human_category', humanCategory);

  toast.style.display = 'block';
  toast.style.background = 'var(--surface-2)';
  toast.textContent = 'Enregistrement…';

  try {
    const r = await fetch('<?= APP_URL ?>/api/review.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    const data = await r.json();
    if (data.success) {
      toast.style.background = '#e8f5e9';
      toast.style.color = '#1b5e20';
      toast.textContent = data.message || 'Validation enregistrée.';
      setTimeout(() => location.reload(), 900);
    } else {
      toast.style.background = '#ffebee';
      toast.style.color = '#b71c1c';
      toast.textContent = data.message || 'Erreur';
    }
  } catch (err) {
    toast.style.background = '#ffebee';
    toast.textContent = err.message;
  }
}
</script>
</body>
</html>
