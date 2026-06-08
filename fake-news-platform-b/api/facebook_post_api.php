<?php
/**
 * api/facebook_post_api.php
 * ================================================================
 * API STABLE OSINT pour extraction et analyse de publications Facebook
 * 
 * Endpoints:
 *   ?action=extract&url=...         Extrait une publication
 *   ?action=analyze&post_id=...     Analyse avec IA
 *   ?action=get_post&id=...         Récupère un post
 *   ?action=get_recent_posts        Récupère les derniers posts
 *   ?action=delete_post&id=...      Supprime un post
 *   ?action=get_stats               Statistiques globales
 * ================================================================
 */

// Configuration stricte
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Clean start - no output before JSON
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_client.php';

// Ensure user is logged in
requireLogin();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Database and Python config
$db = getDB();
$pythonBin = getPythonExecutable();
$extractorPath = realpath(__DIR__ . '/../python-ai/facebook_post_extractor.py');

/**
 * Safe JSON response builder
 */
function json_response($success, $data = [], $message = ''): string {
    return json_encode([
        'success' => (bool)$success,
        'message' => $message ?: ($success ? 'OK' : 'Erreur'),
        'data' => $data,
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Error response
 */
function json_error($message, $code = 400): string {
    http_response_code($code);
    return json_response(false, [], $message);
}

// ────────────────────────────────────────────────────────────────
// ACTION DISPATCHER
// ────────────────────────────────────────────────────────────────

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_recent_posts';

    switch ($action) {
        case 'extract':
            handle_extract();
            break;
        case 'analyze':
            handle_analyze();
            break;
        case 'get_post':
            handle_get_post();
            break;
        case 'get_recent_posts':
            handle_get_recent_posts();
            break;
        case 'delete_post':
            handle_delete_post();
            break;
        case 'get_stats':
            handle_get_stats();
            break;
        default:
            ob_clean();
            echo json_error("Action inconnue: $action", 400);
            exit;
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_error("Erreur serveur: " . $e->getMessage(), 500);
    exit;
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Extract Publication
// ────────────────────────────────────────────────────────────────

function handle_extract(): void {
    global $db, $pythonBin, $extractorPath;

    // Get URL
    $url = trim($_POST['url'] ?? $_GET['url'] ?? '');
    if (!$url) {
        ob_clean();
        echo json_error("Paramètre manquant: url", 400);
        exit;
    }

    // Validate URL - accept Facebook URLs (facebook.com, fb.com, m.facebook.com)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        ob_clean();
        echo json_error("URL invalide", 400);
        exit;
    }

    // Accept various Facebook URL formats
    $facebook_domains = ['facebook.com', 'fb.com', 'm.facebook.com', 'www.facebook.com', 'mbasic.facebook.com'];
    $url_host = parse_url($url, PHP_URL_HOST);
    $is_facebook = false;
    foreach ($facebook_domains as $domain) {
        if (strpos($url_host, $domain) !== false) {
            $is_facebook = true;
            break;
        }
    }

    if (!$is_facebook) {
        ob_clean();
        echo json_error("URL Facebook requise (facebook.com, fb.com, m.facebook.com)", 400);
        exit;
    }

    try {
        // Run extractor - capture stdout (JSON) and stderr (logs) separately
        $cmd = sprintf(
            '%s %s --url %s --json --save-db',
            $pythonBin,
            escapeshellarg($extractorPath),
            escapeshellarg($url)
        );

        // Log the command for debugging
        $logFile = __DIR__ . '/extractor_debug.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Command: $cmd\n", FILE_APPEND);

        // Execute with proc_open to capture stdout and stderr separately
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"],  // stderr
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, null, getPythonProcessEnv());

        if (!is_resource($process)) {
            ob_clean();
            echo json_error("Erreur d'exécution Python - Impossible de lancer le processus", 500);
            exit;
        }

        // Close stdin
        fclose($pipes[0]);

        // Read stdout (JSON)
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        // Read stderr (logs)
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        // Get return code
        $returnCode = proc_close($process);

        // Log stderr and return code
        file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Stderr: $stderr\n", FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Stdout: $output\n", FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Return Code: $returnCode\n", FILE_APPEND);

        // Check execution
        if ($output === null || $output === false) {
            ob_clean();
            echo json_error("Erreur d'exécution Python - Pas de sortie. Stderr: " . substr($stderr, 0, 500), 500);
            exit;
        }

        // Parse JSON
        $result = json_decode($output, true);

        if (!is_array($result)) {
            ob_clean();
            // Return the actual error for debugging
            echo json_error("Extraction échouée - Invalid JSON. Stderr: " . substr($stderr, 0, 500) . ". Output: " . substr($output, 0, 500), 500);
            exit;
        }

        // Create notification if successful
        if (($result['status'] ?? '') === 'success' && !empty($result['author_name'])) {
            $user = getCurrentUser();
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (?, ?, ?, 'success', 0, NOW())
            ");
            $stmt->execute([
                $user['id'],
                'Nouvelle publication extraite',
                'Publication de ' . $result['author_name'] . ' a été extraite avec succès.'
            ]);

            // Get the created post from database to return to frontend
            $fb_post_url = $result['fb_post_url'] ?? null;
            if ($fb_post_url) {
                $stmt = $db->prepare("
                    SELECT p.*, acc.name as account_name, acc.type as account_type
                    FROM facebook_posts p
                    LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
                    WHERE p.fb_post_url = ?
                    ORDER BY p.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$fb_post_url]);
                $post = $stmt->fetch();

                if ($post) {
                    $result['post'] = $post;
                    $result['post_id'] = $post['id'];

                    // Analyse automatique GBERT après extraction
                    if (aiAutoAnalyzeEnabled() && !empty($post['content'])) {
                        $analysis = aiAnalyzePost((int)$post['id']);
                        if ($analysis && !isset($analysis['error'])) {
                            $result['analysis'] = $analysis;
                            $result['auto_analyzed'] = true;

                            // Recharger le post avec l'analyse
                            $stmt = $db->prepare("
                                SELECT p.*, a.category, a.confidence_score, a.risk_level, a.model_used
                                FROM facebook_posts p
                                LEFT JOIN ai_analysis a ON p.id = a.post_id
                                WHERE p.id = ?
                            ");
                            $stmt->execute([$post['id']]);
                            $result['post'] = $stmt->fetch() ?: $post;
                        }
                    }
                }
            }
        }

        ob_clean();
        echo json_response(
            $result['success'] ?? false,
            $result,
            $result['message'] ?? 'Extraction terminée'
        );

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur extraction: " . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Analyze with AI
// ────────────────────────────────────────────────────────────────

function handle_analyze(): void {
    global $db;

    $post_id = (int)($_POST['post_id'] ?? $_GET['post_id'] ?? 0);
    
    if (!$post_id) {
        ob_clean();
        echo json_error("Paramètre manquant: post_id", 400);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT id, content FROM facebook_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();

        if (!$post) {
            ob_clean();
            echo json_error("Post non trouvé", 404);
            exit;
        }

        $analysis = aiAnalyzePost($post_id);

        if (!$analysis || isset($analysis['error'])) {
            ob_clean();
            echo json_error($analysis['error'] ?? "Erreur d'analyse IA", 500);
            exit;
        }

        // Notification pour l'utilisateur
        if (isset($analysis['category'])) {
            $user = getCurrentUser();
            $risk_level = $analysis['risk_level'] ?? 'low';
            $notif_type = in_array($risk_level, ['high', 'critical']) ? 'alert' : 'success';
            $conf = round((float)($analysis['confidence'] ?? 0));
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, post_id, created_at)
                VALUES (?, ?, ?, ?, 0, ?, NOW())
            ");
            $stmt->execute([
                $user['id'],
                'Analyse IA terminée',
                'Publication analysée par GBERT Hassaniya — ' . ($analysis['category'] ?? 'reliable') . " ({$conf}%)",
                $notif_type,
                $post_id
            ]);
        }

        ob_clean();
        echo json_response(true, $analysis, "Analyse terminée");

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur analyse: " . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Get Single Post
// ────────────────────────────────────────────────────────────────

function handle_get_post(): void {
    global $db;

    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        ob_clean();
        echo json_error("Paramètre manquant: id", 400);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT 
                p.*,
                a.category,
                a.confidence_score,
                a.risk_level,
                acc.name as account_name,
                acc.type as account_type
            FROM facebook_posts p
            LEFT JOIN ai_analysis a ON p.id = a.post_id
            LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
            WHERE p.id = ?
        ");
        
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) {
            ob_clean();
            echo json_error("Post non trouvé", 404);
            exit;
        }

        ob_clean();
        echo json_response(true, $post, "Post récupéré");

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur lecture: " . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Get Recent Posts
// ────────────────────────────────────────────────────────────────

function handle_get_recent_posts(): void {
    global $db;

    try {
        $limit = (int)($_GET['limit'] ?? 20);
        $limit = min(max($limit, 1), 100); // 1-100

        $stmt = $db->prepare("
            SELECT 
                p.*,
                a.category,
                a.confidence_score,
                a.risk_level,
                acc.name as account_name,
                acc.type as account_type
            FROM facebook_posts p
            LEFT JOIN ai_analysis a ON p.id = a.post_id
            LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
            ORDER BY p.fetched_at DESC
            LIMIT ?
        ");
        
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        ob_clean();
        echo json_response(true, [
            'posts' => $posts,
            'count' => count($posts),
        ], "Posts récupérés");

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur lecture: " . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Delete Post
// ────────────────────────────────────────────────────────────────

function handle_delete_post(): void {
    global $db;

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        ob_clean();
        echo json_error("Paramètre manquant: id", 400);
        exit;
    }

    try {
        // Delete analysis first (FK constraint)
        $db->prepare("DELETE FROM ai_analysis WHERE post_id = ?")->execute([$id]);

        // Delete post
        $stmt = $db->prepare("DELETE FROM facebook_posts WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result && $stmt->rowCount() > 0) {
            // Create notification for deletion
            $user = getCurrentUser();
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (?, ?, ?, 'info', 0, NOW())
            ");
            $stmt->execute([
                $user['id'],
                'Publication supprimée',
                'Une publication a été supprimée de la base de données.'
            ]);

            ob_clean();
            echo json_response(true, ['deleted_id' => $id], "Post supprimé");
        } else {
            ob_clean();
            echo json_error("Post non trouvé", 404);
        }

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur suppression: " . $e->getMessage(), 500);
    }
}

// ────────────────────────────────────────────────────────────────
// HANDLER: Get Statistics
// ────────────────────────────────────────────────────────────────

function handle_get_stats(): void {
    global $db;

    try {
        // Total posts
        $total = $db->query("SELECT COUNT(*) FROM facebook_posts")->fetchColumn();
        
        // Posts by analysis
        $analyzed = $db->query("SELECT COUNT(*) FROM facebook_posts WHERE is_analyzed = 1")->fetchColumn();
        $unanalyzed = $db->query("SELECT COUNT(*) FROM facebook_posts WHERE is_analyzed = 0")->fetchColumn();
        
        // Posts by category
        $by_category = $db->query("
            SELECT category, COUNT(*) as count
            FROM ai_analysis
            GROUP BY category
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Recent posts
        $recent = $db->query("
            SELECT MAX(fetched_at) as last_fetch FROM facebook_posts
        ")->fetch()['last_fetch'];

        ob_clean();
        echo json_response(true, [
            'total' => (int)$total,
            'analyzed' => (int)$analyzed,
            'unanalyzed' => (int)$unanalyzed,
            'by_category' => $by_category ?: [],
            'last_fetch' => $recent,
        ], "Statistiques récupérées");

    } catch (Throwable $e) {
        ob_clean();
        echo json_error("Erreur stats: " . $e->getMessage(), 500);
    }
}
?>
