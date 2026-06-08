<?php
/**
 * api/graph_test.php — Test Facebook Graph API
 *
 * GET/POST ?action=test              Teste me + permissions
 * GET/POST ?action=page_posts&page_id=ID   Liste posts d'une Page (si autorisé)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/graph_api.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'test';
$tokenOverride = trim($_POST['access_token'] ?? $_GET['access_token'] ?? '');

try {
    switch ($action) {
        case 'test':
            $result = testGraphApiConnection($tokenOverride !== '' ? $tokenOverride : null);
            ob_clean();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'page_posts':
            $pageId = trim($_GET['page_id'] ?? $_POST['page_id'] ?? '');
            if ($pageId === '') {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Paramètre page_id requis']);
                break;
            }
            $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 5);
            $result = graphApiFetchPagePosts(
                $pageId,
                $tokenOverride !== '' ? $tokenOverride : null,
                $limit
            );
            ob_clean();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
