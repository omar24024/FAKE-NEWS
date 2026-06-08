<?php
/**
 * ============================================================
 * API — Gestion des règles de détection IA
 * ============================================================
 * 
 * Endpoints:
 *   GET  /api/ai_rules.php?action=list&category=fake_news
 *   POST /api/ai_rules.php?action=create
 *   POST /api/ai_rules.php?action=update&id=1
 *   POST /api/ai_rules.php?action=delete&id=1
 *   GET  /api/ai_rules.php?action=get_by_category&category=fake_news
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = getCurrentUser();

// Vérifier que l'utilisateur est admin
if ($user['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied: Admin role required']));
}

$db = getDB();
$action = $_GET['action'] ?? 'list';

// ============================================================
// LIST — Récupérer toutes les règles (optionnellement filtrées par catégorie)
// ============================================================
if ($action === 'list') {
    $category = $_GET['category'] ?? null;
    
    if ($category) {
        $stmt = $db->prepare("
            SELECT id, category, keyword, weight, is_active, rule_type, priority, description, created_at, updated_at
            FROM ai_detection_rules
            WHERE category = ? AND is_active = 1
            ORDER BY priority DESC, created_at DESC
        ");
        $stmt->execute([$category]);
    } else {
        $stmt = $db->query("
            SELECT id, category, keyword, weight, is_active, rule_type, priority, description, created_at, updated_at
            FROM ai_detection_rules
            WHERE is_active = 1
            ORDER BY category, priority DESC, created_at DESC
        ");
    }
    
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper par catégorie si pas de filtre
    if (!$category) {
        $grouped = [];
        foreach ($rules as $rule) {
            $cat = $rule['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $rule;
        }
        echo json_encode(['success' => true, 'data' => $grouped]);
    } else {
        echo json_encode(['success' => true, 'data' => $rules]);
    }
    exit;
}

// ============================================================
// CREATE — Ajouter une nouvelle règle
// ============================================================
if ($action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $category = $input['category'] ?? null;
    $keyword = $input['keyword'] ?? null;
    $weight = floatval($input['weight'] ?? 0.15);
    $rule_type = $input['rule_type'] ?? 'keyword';
    $priority = intval($input['priority'] ?? 1);
    $description = $input['description'] ?? '';
    
    if (!$category || !$keyword) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing category or keyword']);
        exit;
    }
    
    // Valider la catégorie
    $valid_categories = ['fake_news', 'disinformation', 'hate_speech', 'misinformation', 'propaganda', 'violence', 'cyberbullying', 'neutral_indicators'];
    if (!in_array($category, $valid_categories)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid category']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO ai_detection_rules (category, keyword, weight, rule_type, priority, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$category, $keyword, $weight, $rule_type, $priority, $description, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'id' => $db->lastInsertId(),
            'message' => 'Rule created successfully'
        ]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(400);
            echo json_encode(['error' => 'This keyword already exists for this category']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// ============================================================
// UPDATE — Modifier une règle
// ============================================================
if ($action === 'update') {
    $id = intval($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing rule ID']);
        exit;
    }
    
    $weight = isset($input['weight']) ? floatval($input['weight']) : null;
    $priority = isset($input['priority']) ? intval($input['priority']) : null;
    $is_active = isset($input['is_active']) ? intval($input['is_active']) : null;
    $description = isset($input['description']) ? $input['description'] : null;
    
    $updates = [];
    $params = [];
    
    if ($weight !== null) {
        $updates[] = "weight = ?";
        $params[] = $weight;
    }
    if ($priority !== null) {
        $updates[] = "priority = ?";
        $params[] = $priority;
    }
    if ($is_active !== null) {
        $updates[] = "is_active = ?";
        $params[] = $is_active;
    }
    if ($description !== null) {
        $updates[] = "description = ?";
        $params[] = $description;
    }
    
    $updates[] = "updated_by = ?";
    $params[] = $user['id'];
    
    $params[] = $id;
    
    try {
        $stmt = $db->prepare("
            UPDATE ai_detection_rules
            SET " . implode(", ", $updates) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rule updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// DELETE — Supprimer une règle
// ============================================================
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing rule ID']);
        exit;
    }
    
    try {
        // Soft delete (marquer comme inactive)
        $stmt = $db->prepare("
            UPDATE ai_detection_rules
            SET is_active = 0, updated_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rule deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// GET_BY_CATEGORY — Récupérer les règles d'une catégorie
// ============================================================
if ($action === 'get_by_category') {
    $category = $_GET['category'] ?? null;
    
    if (!$category) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing category parameter']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT id, category, keyword, weight, is_active, rule_type, priority, description
        FROM ai_detection_rules
        WHERE category = ? AND is_active = 1
        ORDER BY priority DESC, keyword ASC
    ");
    $stmt->execute([$category]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $rules]);
    exit;
}

// ============================================================
// STATS — Obtenir des statistiques sur les règles
// ============================================================
if ($action === 'stats') {
    $stmt = $db->query("
        SELECT 
            category,
            COUNT(*) as total_rules,
            SUM(is_active) as active_rules,
            AVG(weight) as avg_weight
        FROM ai_detection_rules
        GROUP BY category
        ORDER BY category
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// Action non trouvée
http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
?>
