<?php
/**
 * Service de synchronisation automatique des Pages Facebook surveillées.
 */

require_once __DIR__ . '/ai_client.php';
require_once __DIR__ . '/graph_api.php';

function syncGetSetting(PDO $db, string $key, ?string $default = null): ?string {
    $stmt = $db->prepare('SELECT setting_value FROM api_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (string)$val : $default;
}

function syncSetSetting(PDO $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT INTO api_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function getSyncStatus(?PDO $db = null): array {
    $db = $db ?? getDB();
    $interval = max(300, (int)syncGetSetting($db, 'auto_sync_interval', '3600'));
    $lastRun = syncGetSetting($db, 'last_auto_sync_at');
    $lastResult = syncGetSetting($db, 'last_auto_sync_result');

    $monitoredCount = (int)$db->query("
        SELECT COUNT(*) FROM facebook_accounts
        WHERE is_monitored = 1 AND type = 'page'
          AND fb_id IS NOT NULL AND TRIM(fb_id) != ''
    ")->fetchColumn();

    $nextRun = null;
    $canRunNow = true;
    if ($lastRun) {
        $nextTs = strtotime($lastRun) + $interval;
        $nextRun = date('Y-m-d H:i:s', $nextTs);
        $canRunNow = time() >= $nextTs;
    }

    return [
        'interval_seconds' => $interval,
        'last_run_at' => $lastRun,
        'next_run_at' => $nextRun,
        'can_run_now' => $canRunNow,
        'monitored_pages_count' => $monitoredCount,
        'last_result' => $lastResult ? (json_decode($lastResult, true) ?: null) : null,
    ];
}

/**
 * @param array{
 *   force?: bool,
 *   dry_run?: bool,
 *   limit?: int,
 *   notify_user_id?: int|null,
 *   use_lock?: bool
 * } $opts
 */
function runMonitoredPagesSync(PDO $db, array $opts = []): array {
    $force = (bool)($opts['force'] ?? false);
    $dryRun = (bool)($opts['dry_run'] ?? false);
    $limit = max(1, min((int)($opts['limit'] ?? 10), 25));
    $notifyUserId = isset($opts['notify_user_id']) ? (int)$opts['notify_user_id'] : null;
    $useLock = (bool)($opts['use_lock'] ?? true);

    $lock = null;
    if ($useLock) {
        $lockFile = sys_get_temp_dir() . '/fakenews_sync_monitored.lock';
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return [
                'success' => false,
                'message' => 'Une synchronisation est déjà en cours.',
                'skipped' => true,
            ];
        }
    }

    $interval = max(300, (int)syncGetSetting($db, 'auto_sync_interval', '3600'));
    $lastRun = syncGetSetting($db, 'last_auto_sync_at');

    if (!$force && $lastRun) {
        $elapsed = time() - strtotime($lastRun);
        if ($elapsed < $interval) {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            return [
                'success' => false,
                'message' => 'Prochaine sync dans ' . ($interval - $elapsed) . ' secondes.',
                'wait_seconds' => $interval - $elapsed,
            ];
        }
    }

    $pages = $db->query("
        SELECT id, fb_id, name, type
        FROM facebook_accounts
        WHERE is_monitored = 1
          AND type = 'page'
          AND fb_id IS NOT NULL
          AND TRIM(fb_id) != ''
        ORDER BY updated_at DESC
    ")->fetchAll();

    if (!$pages) {
        if (!$dryRun) {
            syncSetSetting($db, 'last_auto_sync_at', date('Y-m-d H:i:s'));
        }
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return [
            'success' => true,
            'message' => 'Aucune Page surveillée. Activez la surveillance dans Comptes.',
            'pages_count' => 0,
            'imported_count' => 0,
            'analyzed_count' => 0,
            'errors' => [],
            'pages' => [],
        ];
    }

    $totalImported = 0;
    $totalAnalyzed = 0;
    $errors = [];
    $pageResults = [];

    foreach ($pages as $page) {
        $fbId = (string)$page['fb_id'];
        $label = $page['name'] ?: $fbId;
        $entry = [
            'account_id' => (int)$page['id'],
            'fb_id' => $fbId,
            'name' => $label,
            'imported_count' => 0,
            'analyzed_count' => 0,
            'success' => true,
        ];

        if ($dryRun) {
            $entry['dry_run'] = true;
            $pageResults[] = $entry;
            continue;
        }

        try {
            $result = graphImportPagePosts($fbId, $limit, aiAutoAnalyzeEnabled());
            if ($result['success'] ?? false) {
                $entry['imported_count'] = (int)($result['imported_count'] ?? 0);
                $entry['analyzed_count'] = (int)($result['analyzed_count'] ?? 0);
                $entry['message'] = $result['message'] ?? 'OK';
                $totalImported += $entry['imported_count'];
                $totalAnalyzed += $entry['analyzed_count'];
            } else {
                $entry['success'] = false;
                $entry['message'] = $result['message'] ?? 'Erreur';
                $errors[] = "{$label}: {$entry['message']}";
            }
        } catch (Throwable $e) {
            $entry['success'] = false;
            $entry['message'] = $e->getMessage();
            $errors[] = "{$label}: {$e->getMessage()}";
        }

        $pageResults[] = $entry;
        usleep(500_000);
    }

    $finishedAt = date('Y-m-d H:i:s');
    if (!$dryRun) {
        syncSetSetting($db, 'last_auto_sync_at', $finishedAt);
    }

    $summary = [
        'success' => empty($errors) || $totalImported > 0,
        'message' => $dryRun
            ? 'Simulation : ' . count($pages) . ' Page(s) seraient synchronisée(s).'
            : $totalImported . ' publication(s) importée(s) depuis ' . count($pages) . ' Page(s).',
        'finished_at' => $finishedAt,
        'pages_count' => count($pages),
        'imported_count' => $totalImported,
        'analyzed_count' => $totalAnalyzed,
        'errors' => $errors,
        'pages' => $pageResults,
        'dry_run' => $dryRun,
    ];

    if (!$dryRun) {
        syncSetSetting($db, 'last_auto_sync_result', json_encode([
            'finished_at' => $finishedAt,
            'imported_count' => $totalImported,
            'analyzed_count' => $totalAnalyzed,
            'errors_count' => count($errors),
        ], JSON_UNESCAPED_UNICODE));
    }

    if (!$dryRun && $notifyUserId && ($totalImported > 0 || $errors)) {
        $body = "Sync : {$totalImported} publication(s) importée(s), {$totalAnalyzed} analysée(s).";
        if ($errors) {
            $body .= ' Erreurs : ' . implode(' | ', array_slice($errors, 0, 3));
        }
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, 'Synchronisation Pages', ?, ?, 0, NOW())
        ")->execute([
            $notifyUserId,
            $body,
            $errors ? 'warning' : 'success',
        ]);
    } elseif (!$dryRun && !$notifyUserId && ($totalImported > 0 || $errors)) {
        $admin = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
        if ($admin) {
            $body = "Sync auto : {$totalImported} publication(s) importée(s), {$totalAnalyzed} analysée(s).";
            if ($errors) {
                $body .= ' Erreurs : ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (?, 'Sync automatique', ?, ?, 0, NOW())
            ")->execute([
                (int)$admin['id'],
                $body,
                $errors ? 'warning' : 'success',
            ]);
        }
    }

    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    return $summary;
}

function setAccountMonitored(PDO $db, int $accountId, bool $monitored): array {
    $stmt = $db->prepare('SELECT id, name, type, fb_id, is_monitored FROM facebook_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();

    if (!$account) {
        return ['success' => false, 'message' => 'Compte introuvable.'];
    }

    $db->prepare('UPDATE facebook_accounts SET is_monitored = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$monitored ? 1 : 0, $accountId]);

    return [
        'success' => true,
        'message' => $monitored
            ? 'Surveillance activée pour « ' . ($account['name'] ?? 'Page') . ' ».'
            : 'Surveillance désactivée pour « ' . ($account['name'] ?? 'Page') . ' ».',
        'account_id' => $accountId,
        'is_monitored' => $monitored ? 1 : 0,
        'name' => $account['name'],
        'type' => $account['type'],
    ];
}
