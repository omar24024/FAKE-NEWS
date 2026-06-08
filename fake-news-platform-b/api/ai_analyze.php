<?php
/**
 * api/ai_analyze.php
 * Lance l'analyse IA via le serveur FastAPI persistant (fallback: analyze.py).
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_client.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$postId = (int)($_GET['post_id'] ?? 0);
$runAll = isset($_GET['all']);
$text   = $_POST['text'] ?? '';

function aiLog(string $message): void {
    file_put_contents(
        __DIR__ . '/ai_analyze_log.txt',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
        FILE_APPEND
    );
}

try {
    if ($text) {
        $result = aiAnalyzeText($text);
        aiLog('Text analysis via ' . (aiServiceAvailable() ? 'API' : 'CLI'));
        echo $result ? json_encode($result, JSON_UNESCAPED_UNICODE) : json_encode(['success' => false, 'error' => 'Analyse échouée']);
        exit;
    }

    if ($postId) {
        $result = aiAnalyzePost($postId);
        aiLog("Post #{$postId} via " . (aiServiceAvailable() ? 'API' : 'CLI'));
        echo $result ? json_encode($result, JSON_UNESCAPED_UNICODE) : json_encode(['success' => false, 'error' => 'Analyse échouée']);
        exit;
    }

    if ($runAll) {
        $results = aiAnalyzeAllPending();
        aiLog('Analyze all via ' . (aiServiceAvailable() ? 'API' : 'CLI'));
        echo $results !== null ? json_encode($results, JSON_UNESCAPED_UNICODE) : json_encode(['success' => false, 'error' => 'Analyse batch échouée']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Paramètre manquant : post_id, all ou text (POST)']);

} catch (Throwable $e) {
    error_log('AI analyze error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
