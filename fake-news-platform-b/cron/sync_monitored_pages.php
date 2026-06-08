#!/usr/bin/env php
<?php
/**
 * Synchronisation automatique des Pages Facebook surveillées (cron).
 *
 *   php cron/sync_monitored_pages.php
 *   php cron/sync_monitored_pages.php --force
 *   php cron/sync_monitored_pages.php --dry-run
 *
 * Cron (toutes les heures) :
 *   0 * * * * cd /chemin/vers/fake-news-platform-b && php cron/sync_monitored_pages.php >> /var/log/osint-sync.log 2>&1
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}

$projectRoot = dirname(__DIR__);
chdir($projectRoot);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/sync_service.php';

$force = in_array('--force', $argv ?? [], true);
$dryRun = in_array('--dry-run', $argv ?? [], true);

autoInstall();
$db = getDB();

function syncLog(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$result = runMonitoredPagesSync($db, [
    'force' => $force,
    'dry_run' => $dryRun,
    'use_lock' => true,
]);

if ($result['skipped'] ?? false) {
    syncLog($result['message'] ?? 'Sync ignorée.');
    exit(0);
}

if (isset($result['wait_seconds'])) {
    syncLog($result['message'] ?? 'En attente.');
    exit(0);
}

syncLog($result['message'] ?? 'Terminé.');

foreach ($result['pages'] ?? [] as $page) {
    $label = $page['name'] ?? $page['fb_id'] ?? '?';
    if ($page['dry_run'] ?? false) {
        syncLog("  [dry-run] {$label}");
        continue;
    }
    if ($page['success'] ?? false) {
        syncLog(sprintf(
            '  ✓ %s — %d importée(s), %d analysée(s)',
            $label,
            (int)($page['imported_count'] ?? 0),
            (int)($page['analyzed_count'] ?? 0)
        ));
    } else {
        syncLog('  ✗ ' . $label . ' — ' . ($page['message'] ?? 'Erreur'));
    }
}

if (!empty($result['errors'])) {
    syncLog('Erreurs : ' . implode(' | ', $result['errors']));
}

exit(($result['success'] ?? false) ? 0 : 1);
