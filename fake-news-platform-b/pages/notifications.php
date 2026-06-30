<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'mark_read' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
  }
  if ($action === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user['id']]);
  }
  header('Location: notifications.php');
  exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$onlyUnread = (int)($_GET['unread'] ?? 0) === 1;

$where = "user_id = ?";
$params = [$user['id']];
if ($onlyUnread) {
  $where .= " AND is_read = 0";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $db->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$notifCount = getNotificationCount($user['id']);
$currentPage = 'notifications';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Notifications</div>
        <div class="page-subtitle">Alertes système, extraction, analyses IA et activités récentes</div>
      </div>
      <div class="topbar-right">
        <div class="filter-tabs" style="margin-right:6px;">
          <button class="filter-tab <?= $onlyUnread ? '' : 'active' ?>" onclick="location.href='?unread=0'">Toutes</button>
          <button class="filter-tab <?= $onlyUnread ? 'active' : '' ?>" onclick="location.href='?unread=1'">Non lues</button>
        </div>
        <div class="notif-btn">
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
      <div class="section-header">
        <h2 class="section-title">Centre de notifications</h2>
        <div style="display:flex;gap:8px;align-items:center;">
          <form method="POST">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn btn-ghost" type="submit">Tout marquer comme lu</button>
          </form>
        </div>
      </div>

      <div class="posts-table">
        <?php if (!$rows): ?>
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p>Aucune notification pour le moment.</p>
          </div>
        <?php else: ?>
          <?php foreach ($rows as $n): ?>
            <?php
              $type = $n['type'] ?? 'info';
              $pill = match($type) {
                'alert' => ['#FEF2F2', '#DC2626', 'Alerte'],
                'warning' => ['#FFFBEB', '#D97706', 'Attention'],
                'success' => ['#ECFDF5', '#059669', 'Succès'],
                default => ['#EFF4FF', '#2563EB', 'Info'],
              };
            ?>
            <div class="post-row" style="grid-template-columns:220px 1fr 160px;">
              <div>
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;letter-spacing:.08em;text-transform:uppercase;">
                  <?= htmlspecialchars($pill[2]) ?>
                </div>
                <div style="margin-top:6px;">
                  <span style="background:<?= $pill[0] ?>;color:<?= $pill[1] ?>;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:99px;display:inline-flex;">
                    <?= $n['is_read'] ? 'Lue' : 'Non lue' ?>
                  </span>
                </div>
                <div class="author-date" style="margin-top:8px;"><?= formatDate($n['created_at']) ?></div>
              </div>

              <div style="min-width:0;">
                <div style="font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($n['title']) ?></div>
                <?php if (!empty($n['message'])): ?>
                  <div style="margin-top:6px;color:var(--text-secondary);font-size:13px;line-height:1.55;">
                    <?= htmlspecialchars($n['message']) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($n['post_id'])): ?>
                  <div style="margin-top:8px;">
                    <a class="post-fb-link" href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$n['post_id'] ?>">
                      Ouvrir la publication liée
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                      </svg>
                    </a>
                  </div>
                <?php endif; ?>
              </div>

              <div class="post-actions">
                <?php if (!(int)$n['is_read']): ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button class="btn btn-ghost" type="submit">Marquer comme lu</button>
                  </form>
                <?php else: ?>
                  <span style="font-size:12px;color:var(--text-muted);">—</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="pagination-bar">
          <span class="pagination-info">
            Affichage <?= min(($page-1)*$perPage+1, $total) ?> à <?= min($page*$perPage, $total) ?> sur <?= number_format($total) ?> notifications
          </span>
          <div class="pagination-controls">
            <?php
              $baseUrl = "?unread=" . ($onlyUnread ? "1" : "0") . "&page=";
              $start = max(1, $page - 2);
              $end = min($pages, $page + 2);
            ?>
            <button class="page-btn" onclick="location.href='<?= $baseUrl . max(1,$page-1) ?>'" <?= $page<=1?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
            </button>
            <?php if ($start > 1): ?>
              <button class="page-btn" onclick="location.href='<?= $baseUrl ?>1'">1</button>
              <span class="page-btn" style="border:none;cursor:default;">…</span>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
              <button class="page-btn <?= $i===$page?'active':'' ?>" onclick="location.href='<?= $baseUrl . $i ?>'"><?= $i ?></button>
            <?php endfor; ?>
            <?php if ($end < $pages): ?>
              <span class="page-btn" style="border:none;cursor:default;">…</span>
              <button class="page-btn" onclick="location.href='<?= $baseUrl . $pages ?>'"><?= $pages ?></button>
            <?php endif; ?>
            <button class="page-btn" onclick="location.href='<?= $baseUrl . min($pages,$page+1) ?>'" <?= $page>=$pages?'disabled':'' ?>>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

