<?php
/**
 * api/review.php — Validation humaine des analyses IA
 *
 * POST ?action=submit&post_id=ID&decision=confirm|correct|reject
 * POST human_category=... (si correct)
 * POST notes=... (optionnel / requis si reject)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_service.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? 'submit';

try {
    $db = getDB();

    if ($action === 'submit') {
        $postId = (int)($_POST['post_id'] ?? $_GET['post_id'] ?? 0);
        $decision = $_POST['decision'] ?? $_GET['decision'] ?? '';
        $humanCategory = $_POST['human_category'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if ($postId <= 0) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'post_id requis']);
            exit;
        }

        $result = submitHumanReview($db, $postId, (int)$user['id'], $decision, $humanCategory, $notes);
        ob_clean();
        if (!($result['success'] ?? false)) {
            http_response_code(400);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
