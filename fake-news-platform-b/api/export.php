<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$format  = $_GET['format']  ?? 'csv';
$postId  = (int)($_GET['post_id'] ?? 0);
$cat     = $_GET['cat']     ?? '';
$search  = $_GET['q']       ?? '';
$risk    = $_GET['risk']    ?? '';
$range   = $_GET['range']   ?? ''; // month
$db      = getDB();

// ── CSV export ────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'fake-news-export-' . date('Ymd-His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    fputcsv($out, [
        'ID', 'Auteur', 'Type compte', 'Contenu', 'Date publication',
        'Catégorie IA', 'Confiance (%)', 'Niveau de risque',
        'J\'aime', 'Partages', 'Commentaires', 'URL Facebook'
    ], ';');

    $sql = "SELECT p.id, acc.name, acc.type, p.content, p.published_at,
                   a.category, a.confidence_score, a.risk_level,
                   p.likes_count, p.shares_count, p.comments_count, p.fb_post_url
            FROM facebook_posts p
            LEFT JOIN ai_analysis a ON p.id = a.post_id
            LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
            WHERE p.is_analyzed = 1";
    $params = [];
    if ($cat)    { $sql .= " AND a.category = ?"; $params[] = $cat; }
    if ($risk)   { $sql .= " AND a.risk_level = ?"; $params[] = $risk; }
    if ($range === 'month') { $sql .= " AND p.published_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"; }
    if ($search) { $sql .= " AND (p.content LIKE ? OR acc.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($postId) { $sql .= " AND p.id = ?"; $params[] = $postId; }
    $sql .= " ORDER BY p.published_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['type'] === 'page' ? 'Page Facebook' : 'Compte Facebook',
            $row['content'],
            $row['published_at'],
            categoryLabel($row['category'] ?? ''),
            number_format($row['confidence_score'] ?? 0, 1),
            riskLabel($row['risk_level'] ?? ''),
            $row['likes_count'],
            $row['shares_count'],
            $row['comments_count'],
            $row['fb_post_url'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ── PDF export (rapport d'une publication) ────────────────
if ($format === 'pdf') {
    if (!$postId) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètre post_id requis pour format=pdf']);
        exit;
    }

    require_once __DIR__ . '/../includes/pdf_report.php';
    $post = getPostDetails($postId);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Publication non trouvée']);
        exit;
    }

    $user = getCurrentUser();
    $pdf = generatePostReportPdf($post, $user);
    $filename = 'rapport-post-' . $postId . '-' . date('Ymd-His');

    if ($pdf !== null) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    // Fallback HTML si Chromium indisponible
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    echo buildPostReportHtml($post, $user);
    exit;
}

// ── JSON export ───────────────────────────────────────────
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="fake-news-export-' . date('Ymd') . '.json"');

    $result = getPosts(1, 1000, $cat, $search);
    echo json_encode([
        'exported_at' => date('c'),
        'total'       => $result['total'],
        'posts'       => $result['posts'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Fallback ──────────────────────────────────────────────
http_response_code(400);
echo json_encode(['error' => 'Format non supporté. Utilisez ?format=csv, ?format=json ou ?format=pdf&post_id=ID']);
