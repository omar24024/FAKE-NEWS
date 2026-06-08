<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$stats = getStats();
$notifCount = getNotificationCount($user['id']);

// Filters
$page = max(1, (int)($_GET['page'] ?? 1));
$category = $_GET['cat'] ?? '';
$search = $_GET['q'] ?? '';
$dateTo = $_GET['to'] ?? date('Y-m-d');
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));

$result = getPosts($page, 5, $category, $search, $dateFrom, $dateTo);
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord — Détection du Fake News</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Tableau de bord</div>
        <div class="page-subtitle">Vue d'ensemble des publications analysées</div>
      </div>
      <div class="topbar-right">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <div class="date-filter">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" style="border:none;outline:none;font-family:inherit;font-size:13px;color:var(--text-secondary);background:transparent;width:110px;">
            <span style="color:var(--text-muted);">–</span>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" style="border:none;outline:none;font-family:inherit;font-size:13px;color:var(--text-secondary);background:transparent;width:110px;">
          </div>
          <select name="cat" class="status-filter" onchange="this.form.submit()" style="border:1px solid var(--border);border-radius:var(--radius);padding:7px 14px;font-family:inherit;font-size:13px;color:var(--text-secondary);background:var(--surface);cursor:pointer;outline:none;">
            <option value="">Tous les statuts</option>
            <option value="fake_news" <?= $category==='fake_news'?'selected':'' ?>>Fake news</option>
            <option value="disinformation" <?= $category==='disinformation'?'selected':'' ?>>Désinformation</option>
            <option value="hate_speech" <?= $category==='hate_speech'?'selected':'' ?>>Haine / Discours</option>
            <option value="reliable" <?= $category==='reliable'?'selected':'' ?>>Informations fiables</option>
          </select>
        </form>

        <div class="notif-btn" onclick="toggleNotifs()">
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
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon total">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <span class="stat-label">Total analysées</span>
          </div>
          <div class="stat-value" data-count="<?= $stats['total'] ?>">0</div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon fake">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
              </svg>
            </div>
            <span class="stat-label">Fake news</span>
          </div>
          <div class="stat-value" data-count="<?= $stats['fake'] ?>">0</div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon disinfo">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <span class="stat-label">Désinformation</span>
          </div>
          <div class="stat-value" data-count="<?= $stats['disinfo'] ?>">0</div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon hate">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
              </svg>
            </div>
            <span class="stat-label">Haine / Discours</span>
          </div>
          <div class="stat-value" data-count="<?= $stats['hate'] ?>">0</div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon reliable">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="stat-label">Informations fiables</span>
          </div>
          <div class="stat-value" data-count="<?= $stats['reliable'] ?>">0</div>
        </div>
      </div>

      <!-- Posts section -->
      <div class="section-header">
        <h2 class="section-title">Dernières publications analysées</h2>
        <div style="display:flex;gap:8px;align-items:center;">
          <form method="GET" style="display:flex;gap:8px;">
            <input type="hidden" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            <input type="hidden" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($category) ?>">
            <div class="search-bar">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input type="text" name="q" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </form>
          <a href="api/export.php?format=csv&cat=<?= urlencode($category) ?>&q=<?= urlencode($search) ?>" class="btn btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
              <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
          </a>
        </div>
      </div>

      <!-- Table -->
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
            <!-- Author -->
            <div class="post-author">
              <div class="author-avatar">
                <?= avatarSVG(postAuthorName($post), 44) ?>
                <!-- Facebook badge -->
                <div class="fb-badge">
                  <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="8" fill="#1877F2"/>
                    <path d="M10.5 8H9v5H7V8H6V6h1V4.5C7 3.12 7.67 2 9.27 2H11v2H9.73c-.33 0-.73.17-.73.88V6h2l-.5 2z" fill="white"/>
                  </svg>
                </div>
              </div>
              <div class="author-info">
                <div class="author-name"><?= htmlspecialchars(postAuthorName($post)) ?></div>
                <div class="author-type"><?= $post['account_type'] === 'page' ? 'Page Facebook' : 'Compte Facebook' ?></div>
                <div class="author-date"><?= formatDate($post['published_at'] ?? '') ?></div>
              </div>
            </div>

            <!-- Content -->
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

            <!-- Analysis -->
            <div class="post-analysis">
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
            </div>

            <!-- Actions -->
            <div class="post-actions">
              <a href="pages/detail.php?id=<?= $post['id'] ?>" class="btn btn-ghost">Voir détails</a>
              <button class="btn-icon" title="Plus d'options">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                  <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                </svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="pagination-bar">
          <span class="pagination-info">
            Affichage <?= min(($page-1)*5+1, $result['total']) ?> à <?= min($page*5, $result['total']) ?> sur <?= number_format($result['total']) ?> publications
          </span>
          <div class="pagination-controls">
            <?php
            $baseUrl = "?from={$dateFrom}&to={$dateTo}&cat={$category}&q=" . urlencode($search) . "&page=";
            ?>
            <button class="page-btn" onclick="location.href='<?= $baseUrl . max(1,$page-1) ?>'" <?= $page<=1?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M15 19l-7-7 7-7"/>
              </svg>
            </button>

            <?php
            $totalPages = $result['pages'];
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1) echo "<span class='page-btn'>1</span><span class='page-btn' style='border:none;cursor:default;'>…</span>";
            for ($i = $start; $i <= $end; $i++):
            ?>
              <button class="page-btn <?= $i==$page?'active':'' ?>" onclick="location.href='<?= $baseUrl . $i ?>'">
                <?= $i ?>
              </button>
            <?php endfor;
            if ($end < $totalPages) echo "<span class='page-btn' style='border:none;cursor:default;'>…</span><button class='page-btn' onclick=\"location.href='{$baseUrl}{$totalPages}'\">{$totalPages}</button>";
            ?>

            <button class="page-btn" onclick="location.href='<?= $baseUrl . min($totalPages,$page+1) ?>'" <?= $page>=$totalPages?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Count-up animation
document.querySelectorAll('.stat-value[data-count]').forEach(el => {
  const target = parseInt(el.dataset.count);
  const duration = 800;
  const step = target / (duration / 16);
  let current = 0;
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = Math.floor(current).toLocaleString('fr-FR');
    if (current >= target) clearInterval(timer);
  }, 16);
});

function toggleNotifs() {
  window.location.href = 'pages/notifications.php';
}
</script>
</body>
</html>
