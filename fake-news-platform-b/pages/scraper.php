<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$notifCount = getNotificationCount($user['id']);
$scraperStats = getScraperStats();
$authorExpr = sqlPostAuthorExpr('p', 'acc');

$recentPending = $db->query("
  SELECT p.id, p.content, p.image_url, p.fb_post_url, p.published_at, p.fetched_at,
         p.author_name, acc.name as account_name, acc.type as account_type,
         {$authorExpr} AS display_author
  FROM facebook_posts p
  LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
  WHERE p.is_analyzed = 0
  ORDER BY COALESCE(p.published_at, p.fetched_at) DESC
  LIMIT 10
")->fetchAll();

$currentPage = 'scraper';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scraper OSINT — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Scraper OSINT</div>
        <div class="page-subtitle">Extraction de publications publiques Facebook et orchestration des analyses</div>
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
      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon total">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <span class="stat-label">Publications</span>
          </div>
          <div class="stat-value"><?= number_format($scraperStats['total_posts']) ?></div>
        </div>
        <a href="<?= htmlspecialchars(publicationsQueueUrl()) ?>" class="stat-card" style="text-decoration:none;color:inherit;<?= $scraperStats['pending'] === 0 ? 'pointer-events:none;opacity:.85;' : '' ?>">
          <div class="stat-header">
            <div class="stat-icon disinfo">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="stat-label">En attente IA</span>
          </div>
          <div class="stat-value"><?= number_format($scraperStats['pending']) ?></div>
        </a>
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon reliable">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="stat-label">Analysées</span>
          </div>
          <div class="stat-value"><?= number_format($scraperStats['analyzed']) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon total">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="stat-label">Dernière extraction</span>
          </div>
          <div class="stat-value" style="font-size:16px;">
            <?= $scraperStats['last_extraction'] ? htmlspecialchars(formatDate($scraperStats['last_extraction'])) : '—' ?>
          </div>
        </div>
      </div>

      <div class="detail-card" style="margin-bottom:16px;">
        <div class="detail-card-title">Import Facebook Graph API</div>
        <p style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin:0 0 14px;">
          Importe les publications récentes d'une Page Facebook (username ou ID numérique).
          <strong>Graph API</strong> si vous administrez la Page · sinon <strong>Scraper OSINT automatique</strong> (Playwright).
          Exemples : <code>sidna.sidi.serba</code> ou <code>100024619680062</code>
        </p>
        <div style="display:grid;grid-template-columns:1fr 120px;gap:10px;align-items:end;flex-wrap:wrap;">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Page Facebook</label>
            <select class="form-input" id="graphPageSelect">
              <option value="">— Charger les Pages —</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Nombre</label>
            <select class="form-input" id="graphImportLimit">
              <option value="5">5 posts</option>
              <option value="10" selected>10 posts</option>
              <option value="15">15 posts</option>
              <option value="25">25 posts</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top:10px;margin-bottom:0;">
          <label class="form-label">Ou ID / username de Page (manuel)</label>
          <input class="form-input" type="text" id="graphPageIdManual" placeholder="Ex: 123456789012345 ou monPageUsername">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center;">
          <button type="button" class="btn btn-ghost" id="graphLoadPagesBtn" onclick="loadGraphPages()">Charger mes Pages</button>
          <button type="button" class="btn btn-primary" id="graphImportBtn" onclick="importGraphPosts()">Importer via Graph API</button>
          <a class="btn btn-ghost" id="graphPageQueueLink" href="<?= htmlspecialchars(publicationsQueueUrl()) ?>" style="display:none;">Voir en attente d'analyse</a>
          <a class="btn btn-ghost" href="<?= APP_URL ?>/pages/parametres.php">Configurer le token</a>
        </div>
        <div id="graphImportStatus" style="display:none;margin-top:12px;font-size:13px;line-height:1.6;padding:12px;border-radius:var(--radius-sm);"></div>
      </div>

      <div class="detail-card" style="margin-bottom:16px;">
        <div class="detail-card-title">Actions rapides (Scraper OSINT)</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn btn-primary" onclick="openExtractModal()">Extraire une publication</button>
          <button class="btn btn-ghost" onclick="runAnalyzeAll()" <?= $scraperStats['pending'] === 0 ? 'disabled' : '' ?>>Analyser toutes les publications en attente</button>
          <a class="btn btn-ghost" href="<?= APP_URL ?>/pages/publications.php?filter=unanalyzed">Voir la file d'attente</a>
        </div>
        <div style="margin-top:10px;color:var(--text-muted);font-size:12.5px;line-height:1.6;">
          Coller une URL publique → extraction Playwright. Ou utiliser l'import Graph API ci-dessus pour vos Pages administrées.
        </div>
      </div>

      <div class="section-header">
        <h2 class="section-title">Dernières publications en attente</h2>
        <div style="display:flex;gap:8px;">
          <a class="btn btn-ghost" href="<?= APP_URL ?>/pages/publications.php?filter=unanalyzed">Ouvrir Publications</a>
        </div>
      </div>

      <div class="posts-table">
        <?php if (!$recentPending): ?>
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>Aucune publication en attente d'analyse.</p>
          </div>
        <?php else: ?>
          <?php foreach ($recentPending as $post): ?>
            <div class="post-row">
              <div class="post-author">
                <div class="author-avatar"><?= avatarSVG(postAuthorName($post), 44) ?></div>
                <div class="author-info">
                  <div class="author-name"><?= htmlspecialchars(postAuthorName($post)) ?></div>
                  <div class="author-type"><?= ($post['account_type'] ?? 'page') === 'page' ? 'Page Facebook' : 'Compte Facebook' ?></div>
                  <div class="author-date"><?= $post['published_at'] ? formatDate($post['published_at']) : formatDate($post['fetched_at']) ?></div>
                </div>
              </div>
              <div class="post-content-col">
                <?php if ($post['image_url']): ?>
                  <img class="post-thumb" src="<?= htmlspecialchars($post['image_url']) ?>" alt="Publication" onerror="this.style.display='none'">
                <?php else: ?>
                  <div class="post-thumb" style="display:flex;align-items:center;justify-content:center;"></div>
                <?php endif; ?>
                <div class="post-text-wrap">
                  <div class="post-text"><?= htmlspecialchars($post['content'] ?? '') ?></div>
                  <a href="<?= htmlspecialchars($post['fb_post_url'] ?? '#') ?>" target="_blank" class="post-fb-link">Voir sur Facebook</a>
                </div>
              </div>
              <div class="post-analysis">
                <span class="badge" style="background:var(--surface-2);color:var(--text-secondary);border:1px solid var(--border);">Non analysée</span>
                <div class="confidence-text">En attente d'analyse IA</div>
              </div>
              <div class="post-actions">
                <button class="btn btn-ghost" onclick="analyzeOne(<?= (int)$post['id'] ?>, this)">Analyser</button>
                <a class="btn btn-ghost" href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$post['id'] ?>">Détails</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal extraction (same pattern as Publications) -->
<div id="extractModal" style="display:none;position:fixed;inset:0;background:rgba(15,17,23,.35);z-index:1000;align-items:center;justify-content:center;padding:24px;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow-md);width:100%;max-width:520px;padding:22px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
      <div style="font-weight:800;font-size:16px;">Extraire une publication Facebook</div>
      <button class="btn-icon" onclick="closeExtractModal()" title="Fermer">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.3 5.71a1 1 0 00-1.41 0L12 10.59 7.11 5.7A1 1 0 105.7 7.11L10.59 12 5.7 16.89a1 1 0 101.41 1.41L12 13.41l4.89 4.89a1 1 0 001.41-1.41L13.41 12l4.89-4.89a1 1 0 000-1.4z"/></svg>
      </button>
    </div>
    <div style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin-bottom:14px;">
      Coller l’URL d’une publication <strong>publique</strong> Facebook. L’extraction récupère le texte, l’auteur, la date et l’image (si disponible).
    </div>
    <form onsubmit="submitExtract(event)">
      <div class="form-group">
        <label class="form-label">URL de publication</label>
        <input class="form-input" type="url" id="postUrl" placeholder="https://www.facebook.com/..." required>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="extractBtn" style="justify-content:center;">Lancer l’extraction</button>
    </form>
  </div>
</div>

<div id="toast" class="toast-container" style="display:none;"></div>
<script>
function openExtractModal(){
  console.log('openExtractModal called');
  const modal = document.getElementById('extractModal');
  console.log('Modal element:', modal);
  if(modal){
    modal.style.display='flex';
    console.log('Modal display set to flex');
  } else {
    console.error('Modal element not found!');
  }
}
function closeExtractModal(){ document.getElementById('extractModal').style.display='none'; document.getElementById('postUrl').value=''; }
window.addEventListener('click', (e)=>{ if(e.target && e.target.id==='extractModal') closeExtractModal(); });

function toast(msg){
  const cont = document.getElementById('toast');
  cont.style.display = 'flex';
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  cont.appendChild(el);
  setTimeout(()=>{ el.remove(); if(!cont.children.length) cont.style.display='none'; }, 3500);
}

async function submitExtract(e){
  e.preventDefault();
  const url = document.getElementById('postUrl').value.trim();
  if(!url.includes('facebook.com') && !url.includes('fb.com')) { toast('Veuillez entrer une URL Facebook valide.'); return; }
  const btn = document.getElementById('extractBtn');
  btn.disabled = true; btn.textContent = 'Extraction en cours...';
  try{
    const r = await fetch('../api/facebook_post_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`action=extract&url=${encodeURIComponent(url)}`
    });
    const raw = await r.text();
    console.log('Raw extraction response:', raw);
    let data;
    try {
      data = JSON.parse(raw);
    } catch(e) {
      console.error('Invalid JSON response:', raw);
      toast('Erreur extraction: réponse serveur invalide');
      return;
    }
    if(data.success){
      toast('Publication extraite avec succès.');
      closeExtractModal();
      // Refresh page to show new post
      location.reload();
    } else {
      toast('Extraction échouée: ' + (data.message || data.error || 'Erreur'));
    }
  } catch(err){
    toast('Erreur extraction: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Lancer l\'extraction';
  }
}

async function analyzeOne(id, btn){
  const old = btn.textContent;
  btn.disabled = true; btn.textContent = 'Analyse...';
  try{
    const r = await fetch('../api/facebook_post_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`action=analyze&post_id=${encodeURIComponent(id)}`
    });
    const raw = await r.text();
    console.log('Raw analysis response:', raw);
    let data;
    try {
      data = JSON.parse(raw);
    } catch(e) {
      console.error('Invalid JSON response:', raw);
      toast('Erreur analyse: réponse serveur invalide');
      return;
    }
    if(data.success){ toast('Analyse IA terminée.'); setTimeout(()=>location.reload(), 700); }
    else toast('Analyse échouée: ' + (data.message || data.error || 'Erreur'));
  } catch(err){
    toast('Erreur analyse: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = old;
  }
}

async function runAnalyzeAll(){
  if(!confirm('Analyser toutes les publications en attente ?')) return;
  toast('Analyse en cours... (cela peut prendre du temps)');
  try{
    const r = await fetch('../api/ai_analyze.php?all=1', { method:'GET' });
    const raw = await r.text();
    console.log('Raw analyze all response:', raw);
    let data;
    try {
      data = JSON.parse(raw);
    } catch(e) {
      console.error('Invalid JSON response:', raw);
      toast('Erreur: réponse serveur invalide');
      return;
    }
    if(Array.isArray(data)) toast('Analyse terminée: ' + data.length + ' publication(s).');
    else toast('Analyse déclenchée.');
    setTimeout(()=>location.reload(), 1200);
  } catch(err){
    toast('Erreur: ' + err.message);
  }
}

function updateGraphPageQueueLink(){
  const sel = document.getElementById('graphPageSelect');
  const link = document.getElementById('graphPageQueueLink');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) {
    link.style.display = 'none';
    return;
  }
  const queueUrl = opt.dataset.queueUrl;
  const pending = parseInt(opt.dataset.pendingCount || '0', 10);
  if (queueUrl) {
    link.href = queueUrl;
    link.textContent = pending > 0
      ? 'Voir en attente d\'analyse (' + pending + ')'
      : 'Voir publications de cette Page';
    link.style.display = 'inline-flex';
  } else {
    link.style.display = 'none';
  }
}

document.getElementById('graphPageSelect').addEventListener('change', updateGraphPageQueueLink);

async function loadGraphPages(){
  const btn = document.getElementById('graphLoadPagesBtn');
  const sel = document.getElementById('graphPageSelect');
  const importBtn = document.getElementById('graphImportBtn');
  const status = document.getElementById('graphImportStatus');
  btn.disabled = true; btn.textContent = 'Chargement...';
  status.style.display = 'none';
  try {
    const r = await fetch('../api/graph_import.php?action=list_pages');
    const data = await r.json();
    sel.innerHTML = '<option value="">— Sélectionner une Page —</option>';
    if (data.success && data.pages && data.pages.length) {
      data.pages.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + (p.fan_count ? ' (' + p.fan_count + ' abonnés)' : '');
        if (p.queue_url) opt.dataset.queueUrl = p.queue_url;
        opt.dataset.pendingCount = String(p.pending_count || 0);
        sel.appendChild(opt);
      });
      importBtn.disabled = false;
      status.style.display = 'none';
      updateGraphPageQueueLink();
      toast(data.count + ' Page(s) trouvée(s).');
    } else if (data.success) {
      importBtn.disabled = false;
      status.style.display = 'block';
      status.style.background = '#fff8e1';
      status.style.border = '1px solid #ffe082';
      status.style.color = '#e65100';
      status.innerHTML = '<strong>Token OK — 0 Page administrée</strong><br>'
        + (data.message || 'Import manuel possible : saisissez un username/ID puis « Importer » (Scraper OSINT automatique).')
        + '<br><span style="font-size:12px;">Ex. sidna.sidi.serba — l\'import utilisera Playwright si Graph API n\'a pas accès.</span>';
      toast('0 Page admin — import manuel via Scraper OSINT possible');
    } else {
      importBtn.disabled = true;
      status.style.display = 'block';
      status.style.background = '#ffebee';
      status.style.border = '1px solid #ef9a9a';
      status.style.color = '#b71c1c';
      status.textContent = data.message || 'Erreur Graph API — vérifiez le token dans Paramètres.';
    }
  } catch (err) {
    toast('Erreur: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Charger mes Pages';
  }
}

async function importGraphPosts(){
  const pageId = document.getElementById('graphPageSelect').value
    || document.getElementById('graphPageIdManual').value.trim();
  const limit = document.getElementById('graphImportLimit').value;
  const btn = document.getElementById('graphImportBtn');
  const status = document.getElementById('graphImportStatus');
  if (!pageId) { toast('Sélectionnez une Page Facebook.'); return; }

  btn.disabled = true;
  btn.textContent = 'Import en cours...';
  status.style.display = 'block';
  status.style.background = 'var(--surface-2)';
  status.style.border = '1px solid var(--border)';
  status.style.color = 'var(--text-secondary)';
  status.textContent = 'Import Page (Graph API ou Scraper OSINT) + analyse GBERT en cours…';

  try {
    const body = new URLSearchParams({ action: 'import', page_id: pageId, limit });
    const r = await fetch('../api/graph_import.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    const data = await r.json();
    if (data.success) {
      status.style.background = '#e8f5e9';
      status.style.border = '1px solid #a5d6a7';
      status.style.color = '#1b5e20';
      const queueUrl = data.queue_url || '<?= htmlspecialchars(publicationsQueueUrl(), ENT_QUOTES) ?>';
      status.innerHTML = '<strong>✓ ' + (data.message || 'Import réussi') + '</strong><br>'
        + (data.analyzed_count ? data.analyzed_count + ' analysée(s) par GBERT. ' : '')
        + (data.skipped_empty ? data.skipped_empty + ' ignorée(s) (sans texte). ' : '')
        + '<a href="' + queueUrl + '" style="color:inherit;font-weight:700;">Ouvrir la file d\'attente →</a>';
      toast(data.message || 'Import réussi');
      setTimeout(() => { window.location.href = queueUrl; }, 1200);
    } else {
      status.style.background = '#ffebee';
      status.style.border = '1px solid #ef9a9a';
      status.style.color = '#b71c1c';
      status.textContent = data.message || 'Import échoué';
      toast('Import échoué: ' + (data.message || 'Erreur'));
    }
  } catch (err) {
    status.style.background = '#ffebee';
    status.textContent = 'Erreur: ' + err.message;
    toast('Erreur: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Importer via Graph API';
  }
}
</script>
</body>
</html>

