<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getCurrentUser();
$notifCount = getNotificationCount($user['id']);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$filter = $_GET['filter'] ?? 'all'; // all | analyzed | unanalyzed
$search = trim($_GET['q'] ?? '');
$category = $_GET['cat'] ?? '';
$accountId = (int)($_GET['account'] ?? 0);
$authorName = trim($_GET['author'] ?? '');

$dateTo = $_GET['to'] ?? date('Y-m-d');
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-14 days'));

$analyzed = null;
if ($filter === 'analyzed') $analyzed = true;
if ($filter === 'unanalyzed') $analyzed = false;

$result = getPostsList($page, $perPage, [
    'analyzed' => $analyzed,
    'category' => $category,
    'search' => $search,
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
    'accountId' => $accountId ?: null,
    'authorName' => $authorName ?: null,
]);

$currentPage = 'publications';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications — Détection du Fake News</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Publications</div>
        <div class="page-subtitle">Extraction OSINT et suivi de l'analyse IA (Facebook public)</div>
      </div>
      <div class="topbar-right">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <input type="hidden" name="cat" value="<?= htmlspecialchars($category) ?>">
          <input type="hidden" name="account" value="<?= $accountId ?: '' ?>">
          <input type="hidden" name="author" value="<?= htmlspecialchars($authorName) ?>">
          <div class="date-filter">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" style="border:none;outline:none;font-family:inherit;font-size:13px;color:var(--text-secondary);background:transparent;width:110px;">
            <span style="color:var(--text-muted);">–</span>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" style="border:none;outline:none;font-family:inherit;font-size:13px;color:var(--text-secondary);background:transparent;width:110px;">
          </div>
        </form>

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
      <?php if ($filter === 'unanalyzed'): ?>
        <div class="detail-card" style="margin-bottom:16px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-color:#ffe082;background:#fffbeb;">
          <div>
            <div style="font-size:12px;color:#b45309;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">File d'attente</div>
            <div style="font-size:15px;font-weight:700;margin-top:4px;color:#92400e;">
              Publications en attente d'analyse IA
              <?php if ($authorName): ?> — <?= htmlspecialchars($authorName) ?><?php endif; ?>
            </div>
          </div>
          <a href="<?= APP_URL ?>/pages/publications.php" class="btn btn-ghost">Toutes les publications</a>
        </div>
      <?php elseif ($authorName): ?>
        <div class="detail-card" style="margin-bottom:16px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
          <div>
            <div style="font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Filtre auteur</div>
            <div style="font-size:15px;font-weight:700;margin-top:4px;"><?= htmlspecialchars($authorName) ?></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($accountId): ?>
              <a href="<?= htmlspecialchars(publicationsQueueUrl($accountId, $authorName)) ?>" class="btn btn-ghost">En attente d'analyse</a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/pages/publications.php" class="btn btn-ghost">Tous les auteurs</a>
          </div>
        </div>
      <?php endif; ?>

      <div class="section-header">
        <h2 class="section-title">Flux des publications</h2>
        <div style="display:flex;gap:8px;align-items:center;">
          <div class="filter-tabs">
            <button class="filter-tab <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all')">Toutes</button>
            <button class="filter-tab <?= $filter==='unanalyzed'?'active':'' ?>" onclick="setFilter('unanalyzed')">À analyser</button>
            <button class="filter-tab <?= $filter==='analyzed'?'active':'' ?>" onclick="setFilter('analyzed')">Analysées</button>
          </div>
          <form method="GET" style="display:flex;gap:8px;">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <input type="hidden" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            <input type="hidden" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($category) ?>">
            <input type="hidden" name="account" value="<?= $accountId ?: '' ?>">
          <input type="hidden" name="author" value="<?= htmlspecialchars($authorName) ?>">
            <div class="search-bar">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input type="text" name="q" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </form>
          <button class="btn btn-primary" onclick="openExtractModal()">Extraire une publication</button>
        </div>
      </div>

      <div id="toast" class="toast-container" style="display:none;"></div>

      <div class="posts-table">
        <?php if (empty($result['posts'])): ?>
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>Aucune publication trouvée pour ces critères.</p>
          </div>
        <?php else: ?>
          <?php foreach ($result['posts'] as $post): ?>
          <div class="post-row">
            <div class="post-author">
              <div class="author-avatar">
                <?= avatarSVG(postAuthorName($post), 44) ?>
                <div class="fb-badge">
                  <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="8" fill="#1877F2"/>
                    <path d="M10.5 8H9v5H7V8H6V6h1V4.5C7 3.12 7.67 2 9.27 2H11v2H9.73c-.33 0-.73.17-.73.88V6h2l-.5 2z" fill="white"/>
                  </svg>
                </div>
              </div>
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
                <div class="post-thumb" style="display:flex;align-items:center;justify-content:center;">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="width:24px;height:24px;color:var(--text-muted);">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                  </svg>
                </div>
              <?php endif; ?>
              <div class="post-text-wrap">
                <div class="post-text"><?= htmlspecialchars($post['content'] ?? '') ?></div>
                <a href="<?= htmlspecialchars($post['fb_post_url'] ?? '#') ?>" target="_blank" class="post-fb-link">
                  Voir sur Facebook
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                  </svg>
                </a>
              </div>
            </div>

            <div class="post-analysis">
              <?php if ((int)$post['is_analyzed'] === 1): ?>
                <?php $cat = $post['category'] ?? 'reliable'; ?>
                <span class="badge <?= categoryClass($cat) ?>"><?= categoryLabel($cat) ?></span>
                <div class="confidence-text">Confiance : <span><?= number_format($post['confidence_score'] ?? 0, 0) ?>%</span></div>
                <div class="conf-bar">
                  <div class="conf-bar-fill" style="width:<?= $post['confidence_score'] ?? 0 ?>%;background:<?= match($cat) {
                    'fake_news' => 'var(--fake-color)',
                    'disinformation' => 'var(--disinfo-color)',
                    'hate_speech' => 'var(--hate-color)',
                    default => 'var(--reliable-color)'
                  } ?>;"></div>
                </div>
              <?php else: ?>
                <span class="badge" style="background:var(--surface-2);color:var(--text-secondary);border:1px solid var(--border);">Non analysée</span>
                <div class="confidence-text">En attente d'analyse IA</div>
              <?php endif; ?>
            </div>

            <div class="post-actions">
              <button class="btn btn-ghost" onclick="analyzePost(<?= (int)$post['id'] ?>, this)"><?= (int)$post['is_analyzed'] === 1 ? 'Réanalyser' : 'Analyser' ?></button>
              <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$post['id'] ?>" class="btn btn-ghost">Détails</a>
              <button class="btn-icon" title="Supprimer" onclick="deletePost(<?= (int)$post['id'] ?>, this)">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 3a1 1 0 00-1 1v1H5.5a1 1 0 100 2H6v13a2 2 0 002 2h8a2 2 0 002-2V7h.5a1 1 0 100-2H16V4a1 1 0 00-1-1H9zm1 3V5h4v1h-4zm-1 4a1 1 0 112 0v9a1 1 0 11-2 0v-9zm5-1a1 1 0 00-1 1v9a1 1 0 102 0v-9a1 1 0 00-1-1z"/></svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="pagination-bar">
          <span class="pagination-info">
            Affichage <?= min(($page-1)*$perPage+1, $result['total']) ?> à <?= min($page*$perPage, $result['total']) ?> sur <?= number_format($result['total']) ?> publications
          </span>
          <div class="pagination-controls">
            <?php
              $baseUrl = "?filter=" . urlencode($filter)
                . "&from=" . urlencode($dateFrom)
                . "&to=" . urlencode($dateTo)
                . "&cat=" . urlencode($category)
                . "&q=" . urlencode($search)
                . ($accountId ? "&account=" . $accountId : "")
                . ($authorName ? "&author=" . urlencode($authorName) : "")
                . "&page=";
              $totalPages = $result['pages'];
            ?>
            <button class="page-btn" onclick="location.href='<?= $baseUrl . max(1,$page-1) ?>'" <?= $page<=1?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
            </button>
            <?php
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
              if ($start > 1) echo "<button class='page-btn' onclick=\"location.href='{$baseUrl}1'\">1</button><span class='page-btn' style='border:none;cursor:default;'>…</span>";
              for ($i = $start; $i <= $end; $i++):
            ?>
              <button class="page-btn <?= $i==$page?'active':'' ?>" onclick="location.href='<?= $baseUrl . $i ?>'"><?= $i ?></button>
            <?php endfor;
              if ($end < $totalPages) echo "<span class='page-btn' style='border:none;cursor:default;'>…</span><button class='page-btn' onclick=\"location.href='{$baseUrl}{$totalPages}'\">{$totalPages}</button>";
            ?>
            <button class="page-btn" onclick="location.href='<?= $baseUrl . min($totalPages,$page+1) ?>'" <?= $page>=$totalPages?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal extraction -->
<div id="extractModal" style="display:none;position:fixed;inset:0;background:rgba(15,17,23,.35);z-index:1000;align-items:center;justify-content:center;padding:24px;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow-md);width:100%;max-width:520px;padding:22px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
      <div style="font-weight:800;font-size:16px;">Extraire une publication Facebook</div>
      <button class="btn-icon" onclick="closeExtractModal()" title="Fermer">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.3 5.71a1 1 0 00-1.41 0L12 10.59 7.11 5.7A1 1 0 105.7 7.11L10.59 12 5.7 16.89a1 1 0 101.41 1.41L12 13.41l4.89 4.89a1 1 0 001.41-1.41L13.41 12l4.89-4.89a1 1 0 000-1.4z"/></svg>
      </button>
    </div>
    <div style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin-bottom:14px;">
      Coller l’URL d’une publication <strong>publique</strong> Facebook. L’extraction OSINT récupère le texte, l’auteur, la date et l’image (si disponible).
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

function setFilter(f){
  const u = new URL(window.location.href);
  u.searchParams.set('filter', f);
  u.searchParams.set('page', '1');
  window.location.href = u.toString();
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
    const raw = await r.text(); console.log('Raw response:', raw); let data; try { data = JSON.parse(raw); } catch(e) { console.error('Invalid JSON:', raw); toast('Erreur serveur'); return; }
    if(data.success){
      toast('Publication extraite avec succès.');
      closeExtractModal();
      // Refresh page to show new post
      location.reload();
    } else {
      toast('Extraction échouée: ' + (data.message || 'Erreur'));
    }
  } catch(err){
    toast('Erreur extraction: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Lancer l\'extraction';
  }
}

async function analyzePost(id, btn){
  if(!confirm('Analyser cette publication avec l’IA ?')) return;
  const old = btn.textContent;
  btn.disabled = true; btn.textContent = 'Analyse...';
  try{
    const r = await fetch('../api/facebook_post_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`action=analyze&post_id=${encodeURIComponent(id)}`
    });
    const raw = await r.text(); console.log('Raw response:', raw); let data; try { data = JSON.parse(raw); } catch(e) { console.error('Invalid JSON:', raw); toast('Erreur serveur'); return; }
    if(data.success){ toast('Analyse IA terminée.'); setTimeout(()=>location.reload(), 700); }
    else toast('Analyse échouée: ' + (data.message || 'Erreur'));
  } catch(err){
    toast('Erreur analyse: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = old;
  }
}

async function deletePost(id, btn){
  if(!confirm('Supprimer cette publication ?')) return;
  btn.disabled = true;
  try{
    const r = await fetch('../api/facebook_post_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`action=delete_post&id=${encodeURIComponent(id)}`
    });
    const raw = await r.text();
    console.log('Raw delete response:', raw);
    let data;
    try {
      data = JSON.parse(raw);
    } catch(e) {
      console.error('Invalid JSON response:', raw);
      toast('Erreur suppression: réponse serveur invalide');
      return;
    }
    if(data.success){ toast('Publication supprimée.'); setTimeout(()=>location.reload(), 600); }
    else toast('Suppression échouée: ' + (data.message || 'Erreur'));
  } catch(err){
    toast('Erreur suppression: ' + err.message);
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>
