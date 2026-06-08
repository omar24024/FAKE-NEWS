<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$notifCount = getNotificationCount($user['id']);
$accounts = getMonitoredAccounts();

$totalPosts = array_sum(array_column($accounts, 'post_count'));
$totalPending = array_sum(array_column($accounts, 'pending_count'));

$currentPage = 'comptes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comptes — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Comptes surveillés</div>
        <div class="page-subtitle">Auteurs détectés via les publications Facebook — activez la surveillance auto par Page</div>
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
      <?php if ($accounts): ?>
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon total">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <span class="stat-label">Auteurs détectés</span>
          </div>
          <div class="stat-value"><?= number_format(count($accounts)) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon total">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <span class="stat-label">Publications extraites</span>
          </div>
          <div class="stat-value"><?= number_format($totalPosts) ?></div>
        </div>
        <a href="<?= htmlspecialchars(publicationsQueueUrl()) ?>" class="stat-card" style="text-decoration:none;color:inherit;<?= $totalPending === 0 ? 'pointer-events:none;opacity:.85;' : '' ?>">
          <div class="stat-header">
            <div class="stat-icon disinfo">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="stat-label">Analyses en attente</span>
          </div>
          <div class="stat-value"><?= number_format($totalPending) ?></div>
        </a>
      </div>
      <?php endif; ?>

      <?php if (empty($accounts)): ?>
        <div class="detail-card">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p>Aucun compte surveillé pour le moment.</p>
            <p style="margin-top:8px;font-size:13px;">Les auteurs apparaissent automatiquement après l'extraction de publications Facebook.</p>
            <a href="<?= APP_URL ?>/pages/scraper.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">Extraire une publication</a>
          </div>
        </div>
      <?php else: ?>
        <div class="accounts-grid">
          <?php foreach ($accounts as $acc): ?>
          <?php
            $riskColors = [
              'critical' => ['#FEF2F2', '#DC2626'],
              'high' => ['#FFFBEB', '#D97706'],
              'medium' => ['#EFF6FF', '#2563EB'],
              'low' => ['#ECFDF5', '#059669'],
            ];
            $rc = $riskColors[$acc['risk_level']] ?? $riskColors['low'];
            $showFollowers = isset($acc['followers_count']) && (int)$acc['followers_count'] > 0;
            $hasPhoto = isRealProfilePicture($acc['profile_picture'] ?? null);
            $pending = (int)($acc['pending_count'] ?? 0);
            $isMonitored = (int)($acc['is_monitored'] ?? 1) === 1;
            $canMonitor = ($acc['account_type'] ?? 'page') === 'page' && !empty($acc['fb_id']);
            $pubsUrl = $pending > 0
              ? publicationsQueueUrl((int)$acc['account_id'], $acc['author_name'])
              : publicationsAccountUrl((int)$acc['account_id'], $acc['author_name']);
          ?>
          <div class="account-card" id="account-card-<?= (int)$acc['account_id'] ?>">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
              <div style="width:50px;height:50px;border-radius:50%;overflow:hidden;flex-shrink:0;position:relative;">
                <?php if ($hasPhoto): ?>
                  <img src="<?= htmlspecialchars($acc['profile_picture']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                  <span style="display:none;"><?= avatarSVG($acc['author_name'], 50) ?></span>
                <?php else: ?>
                  <?= avatarSVG($acc['author_name'], 50) ?>
                <?php endif; ?>
                <div style="position:absolute;bottom:-1px;right:-1px;">
                  <svg viewBox="0 0 14 14" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="7" cy="7" r="7" fill="#1877F2"/>
                    <path d="M9.2 7H8v4.5H6.2V7H5.2V5.3h1V4.2C6.2 2.8 6.7 2 8.1 2H9.7v1.8H8.7c-.3 0-.6.1-.6.7v.8H9.6L9.2 7z" fill="white"/>
                  </svg>
                </div>
              </div>
              <div style="min-width:0;flex:1;">
                <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($acc['author_name']) ?></div>
                <div style="font-size:11.5px;color:var(--text-muted);">
                  <?= ($acc['account_type'] ?? 'page') === 'page' ? 'Page Facebook' : 'Compte Facebook' ?>
                </div>
                <?php if (!empty($acc['latest_activity'])): ?>
                  <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                    Dernière activité : <?= formatDate($acc['latest_activity']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap;">
              <?php if ((int)($acc['analyzed_count'] ?? 0) > 0): ?>
                <span style="background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:99px;">
                  Risque <?= riskLabel($acc['risk_level']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($acc['last_category'])): ?>
                <span class="badge <?= categoryClass($acc['last_category']) ?>" style="font-size:11px;padding:3px 10px;">
                  <?= categoryLabel($acc['last_category']) ?>
                </span>
              <?php endif; ?>
              <?php if ($pending > 0): ?>
                <a href="<?= htmlspecialchars(publicationsQueueUrl((int)$acc['account_id'], $acc['author_name'])) ?>"
                   style="background:var(--surface-2);color:var(--text-secondary);font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:99px;border:1px solid var(--border);text-decoration:none;">
                  <?= htmlspecialchars($acc['analysis_status_label']) ?>
                </a>
              <?php else: ?>
                <span style="background:var(--surface-2);color:var(--text-secondary);font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:99px;border:1px solid var(--border);">
                  <?= htmlspecialchars($acc['analysis_status_label']) ?>
                </span>
              <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:<?= $showFollowers ? '1fr 1fr' : '1fr' ?>;gap:8px;margin-bottom:14px;">
              <?php if ($showFollowers): ?>
              <div style="text-align:center;background:var(--surface-2);border-radius:var(--radius-sm);padding:10px;">
                <div style="font-size:20px;font-weight:800;color:var(--text-primary);"><?= number_format((int)$acc['followers_count']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);">Abonnés</div>
              </div>
              <?php endif; ?>
              <div style="text-align:center;background:var(--surface-2);border-radius:var(--radius-sm);padding:10px;">
                <div style="font-size:20px;font-weight:800;color:var(--text-primary);"><?= number_format((int)$acc['post_count']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);">Publications</div>
              </div>
            </div>

            <?php if ($canMonitor): ?>
            <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;padding:10px 12px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;">
              <span style="font-size:12.5px;font-weight:600;color:var(--text-secondary);">
                Sync automatique
                <span id="monitor-label-<?= (int)$acc['account_id'] ?>" style="display:block;font-size:11px;font-weight:500;color:var(--text-muted);margin-top:2px;">
                  <?= $isMonitored ? 'Page surveillée' : 'Surveillance désactivée' ?>
                </span>
              </span>
              <input type="checkbox"
                     id="monitor-<?= (int)$acc['account_id'] ?>"
                     <?= $isMonitored ? 'checked' : '' ?>
                     onchange="toggleMonitor(<?= (int)$acc['account_id'] ?>, this)"
                     style="width:18px;height:18px;accent-color:var(--accent);cursor:pointer;">
            </label>
            <?php endif; ?>

            <?php if (!empty($acc['account_url']) && str_starts_with($acc['account_url'], 'http')): ?>
              <a href="<?= htmlspecialchars($acc['account_url']) ?>" target="_blank" rel="noopener" class="btn btn-ghost w-full" style="justify-content:center;font-size:12px;margin-bottom:8px;">
                Profil Facebook
              </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($pubsUrl) ?>" class="btn btn-primary w-full" style="justify-content:center;font-size:12.5px;">
              <?= $pending > 0 ? 'Voir en attente d\'analyse' : 'Voir les publications' ?>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast" class="toast-container" style="display:none;position:fixed;bottom:24px;right:24px;z-index:1000;"></div>
<script>
function toast(msg) {
  const cont = document.getElementById('toast');
  cont.style.display = 'flex';
  cont.style.flexDirection = 'column';
  cont.style.gap = '8px';
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  cont.appendChild(el);
  setTimeout(() => { el.remove(); if (!cont.children.length) cont.style.display = 'none'; }, 3500);
}

async function toggleMonitor(accountId, checkbox) {
  const monitored = checkbox.checked ? 1 : 0;
  const label = document.getElementById('monitor-label-' + accountId);
  checkbox.disabled = true;

  try {
    const r = await fetch('<?= APP_URL ?>/api/sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=toggle_monitor&account_id=${accountId}&is_monitored=${monitored}`
    });
    const data = await r.json();
    if (data.success) {
      if (label) label.textContent = monitored ? 'Page surveillée' : 'Surveillance désactivée';
      toast(data.message || 'Mis à jour');
    } else {
      checkbox.checked = !checkbox.checked;
      toast(data.message || 'Erreur');
    }
  } catch (err) {
    checkbox.checked = !checkbox.checked;
    toast('Erreur: ' + err.message);
  } finally {
    checkbox.disabled = false;
  }
}
</script>
</body>
</html>
