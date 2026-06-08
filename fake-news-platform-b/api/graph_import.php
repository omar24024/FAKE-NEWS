<?php
/**
 * api/graph_import.php — Import publications via Facebook Graph API
 *
 * GET/POST ?action=list_pages     Pages administrées par le token
 * GET/POST ?action=import&page_id=ID&limit=10   Import + analyse auto
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/graph_api.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list_pages';

try {
    switch ($action) {
        case 'list_pages':
            $result = graphApiListManagedPages();
            ob_clean();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'import':
            $pageId = trim($_GET['page_id'] ?? $_POST['page_id'] ?? '');
            if ($pageId === '') {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Paramètre page_id requis']);
                break;
            }
            $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 10);
            $result = graphImportPagePosts($pageId, $limit, true);
            ob_clean();
            if (!($result['success'] ?? false)) {
                http_response_code(400);
            }
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
