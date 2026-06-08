<?php
/**
 * api/sync.php — Synchronisation des Pages surveillées
 *
 * GET  ?action=status
 * POST ?action=run&force=1
 * POST ?action=toggle_monitor&account_id=ID&is_monitored=0|1
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sync_service.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    $db = getDB();

    switch ($action) {
        case 'status':
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => getSyncStatus($db),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'run':
            set_time_limit(0);
            $force = (int)($_POST['force'] ?? $_GET['force'] ?? 1) === 1;
            $result = runMonitoredPagesSync($db, [
                'force' => $force,
                'notify_user_id' => (int)$user['id'],
            ]);
            ob_clean();
            if (!($result['success'] ?? false) && !($result['skipped'] ?? false)) {
                http_response_code(($result['wait_seconds'] ?? 0) > 0 ? 429 : 400);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'toggle_monitor':
            $accountId = (int)($_POST['account_id'] ?? $_GET['account_id'] ?? 0);
            if ($accountId <= 0) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'account_id requis']);
                break;
            }
            $monitored = (int)($_POST['is_monitored'] ?? $_GET['is_monitored'] ?? 1) === 1;
            $result = setAccountMonitored($db, $accountId, $monitored);
            ob_clean();
            if (!($result['success'] ?? false)) {
                http_response_code(404);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Action inconnue: $action"]);
    }
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
