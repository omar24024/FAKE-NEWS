<?php
/**
 * Validation humaine des analyses IA (Facebook texte + commentaires).
 */

function reviewStatusLabel(int $status): string {
    return match ($status) {
        1 => 'Confirmée par l\'analyste',
        2 => 'Corrigée par l\'analyste',
        3 => 'Rejetée par l\'analyste',
        default => 'En attente de validation',
    };
}

function submitHumanReview(PDO $db, int $postId, int $userId, string $decision, ?string $humanCategory = null, ?string $notes = null): array {
    $decision = strtolower(trim($decision));
    $allowed = ['confirm', 'correct', 'reject'];
    if (!in_array($decision, $allowed, true)) {
        return ['success' => false, 'message' => 'Décision invalide.'];
    }

    $stmt = $db->prepare("
        SELECT a.id AS analysis_id, a.category, a.manual_review, a.human_category
        FROM facebook_posts p
        JOIN ai_analysis a ON a.post_id = p.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$postId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['success' => false, 'message' => 'Publication non analysée — lancez d\'abord l\'analyse IA.'];
    }

    $aiCategory = (string)($row['category'] ?? 'reliable');
    $analysisId = (int)$row['analysis_id'];
    $manualReview = match ($decision) {
        'confirm' => 1,
        'correct' => 2,
        'reject' => 3,
    };

    if ($decision === 'correct') {
        $humanCategory = trim((string)$humanCategory);
        $validCats = ['fake_news', 'disinformation', 'hate_speech', 'misinformation', 'propaganda', 'violence', 'cyberbullying', 'reliable'];
        if (!in_array($humanCategory, $validCats, true)) {
            return ['success' => false, 'message' => 'Catégorie humaine invalide.'];
        }
    } elseif ($decision === 'reject') {
        $humanCategory = 'reliable';
        if (trim((string)$notes) === '') {
            return ['success' => false, 'message' => 'Une note est requise pour rejeter l\'analyse IA.'];
        }
    } else {
        $humanCategory = $aiCategory;
    }

    $notes = trim((string)$notes);
    $finalCategory = $humanCategory;

    $db->prepare("
        UPDATE ai_analysis SET
            manual_review = ?,
            human_category = ?,
            ai_category_original = COALESCE(ai_category_original, category),
            category = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            analysis_notes = ?
        WHERE id = ?
    ")->execute([
        $manualReview,
        $humanCategory,
        $finalCategory,
        $userId,
        $notes !== '' ? $notes : null,
        $analysisId,
    ]);

    $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, 'info', 0, NOW())
    ")->execute([
        $userId,
        'Validation enregistrée',
        'Publication #' . $postId . ' — ' . reviewStatusLabel($manualReview) . '.',
    ]);

    return [
        'success' => true,
        'message' => reviewStatusLabel($manualReview),
        'manual_review' => $manualReview,
        'human_category' => $humanCategory,
        'ai_category' => $aiCategory,
        'category' => $finalCategory,
    ];
}

/**
 * Métriques d'évaluation IA vs validation humaine (publications Facebook).
 */
function getEvaluationMetrics(?PDO $db = null): array {
    $db = $db ?? getDB();

    $rows = $db->query("
        SELECT
            a.category AS current_category,
            COALESCE(a.ai_category_original, a.category) AS ai_category,
            a.human_category,
            a.manual_review
        FROM ai_analysis a
        WHERE a.manual_review > 0
          AND a.human_category IS NOT NULL
          AND TRIM(a.human_category) != ''
    ")->fetchAll();

    $total = count($rows);
    if ($total === 0) {
        return [
            'total_reviews' => 0,
            'accuracy' => null,
            'precision_macro' => null,
            'recall_macro' => null,
            'f1_macro' => null,
            'by_category' => [],
            'confusion' => [],
            'recent_reviews' => [],
        ];
    }

    $categories = ['fake_news', 'disinformation', 'hate_speech', 'cyberbullying', 'misinformation', 'reliable'];
    $harmful = ['fake_news', 'disinformation', 'hate_speech', 'cyberbullying', 'misinformation', 'propaganda', 'violence'];

    $correct = 0;
    $byCat = [];
    $confusion = [];

    foreach ($rows as $row) {
        $ai = (string)($row['ai_category'] ?? 'reliable');
        $human = (string)($row['human_category'] ?? 'reliable');
        if ($ai === $human) {
            $correct++;
        }
        $confusion[$ai][$human] = ($confusion[$ai][$human] ?? 0) + 1;
        foreach ([$ai, $human] as $c) {
            if (!isset($byCat[$c])) {
                $byCat[$c] = ['tp' => 0, 'fp' => 0, 'fn' => 0];
            }
        }
    }

    foreach ($rows as $row) {
        $ai = (string)($row['ai_category'] ?? 'reliable');
        $human = (string)($row['human_category'] ?? 'reliable');
        foreach ($categories as $cat) {
            if (!isset($byCat[$cat])) {
                $byCat[$cat] = ['tp' => 0, 'fp' => 0, 'fn' => 0];
            }
            if ($ai === $cat && $human === $cat) {
                $byCat[$cat]['tp']++;
            } elseif ($ai === $cat && $human !== $cat) {
                $byCat[$cat]['fp']++;
            } elseif ($ai !== $cat && $human === $cat) {
                $byCat[$cat]['fn']++;
            }
        }
    }

    $precisions = [];
    $recalls = [];
    $f1s = [];
    $byCategoryOut = [];

    foreach ($byCat as $cat => $m) {
        $tp = $m['tp'];
        $fp = $m['fp'];
        $fn = $m['fn'];
        $p = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : null;
        $r = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : null;
        $f1 = ($p !== null && $r !== null && ($p + $r) > 0) ? (2 * $p * $r) / ($p + $r) : null;
        if ($p !== null) {
            $precisions[] = $p;
        }
        if ($r !== null) {
            $recalls[] = $r;
        }
        if ($f1 !== null) {
            $f1s[] = $f1;
        }
        $byCategoryOut[$cat] = [
            'precision' => $p !== null ? round($p * 100, 1) : null,
            'recall' => $r !== null ? round($r * 100, 1) : null,
            'f1' => $f1 !== null ? round($f1 * 100, 1) : null,
            'support' => $tp + $fn,
        ];
    }

    $binaryCorrect = 0;
    foreach ($rows as $row) {
        $aiH = in_array($row['ai_category'], $harmful, true);
        $huH = in_array($row['human_category'], $harmful, true);
        if ($aiH === $huH) {
            $binaryCorrect++;
        }
    }

    $recent = $db->query("
        SELECT p.id, p.content, a.category, a.human_category, a.ai_category_original,
               a.manual_review, a.reviewed_at, u.full_name AS reviewer_name
        FROM ai_analysis a
        JOIN facebook_posts p ON p.id = a.post_id
        LEFT JOIN users u ON u.id = a.reviewed_by
        WHERE a.manual_review > 0
        ORDER BY a.reviewed_at DESC
        LIMIT 10
    ")->fetchAll();

    return [
        'total_reviews' => $total,
        'accuracy' => round($correct / $total * 100, 1),
        'binary_harmful_accuracy' => round($binaryCorrect / $total * 100, 1),
        'precision_macro' => $precisions ? round(array_sum($precisions) / count($precisions) * 100, 1) : null,
        'recall_macro' => $recalls ? round(array_sum($recalls) / count($recalls) * 100, 1) : null,
        'f1_macro' => $f1s ? round(array_sum($f1s) / count($f1s) * 100, 1) : null,
        'by_category' => $byCategoryOut,
        'confusion' => $confusion,
        'recent_reviews' => $recent,
    ];
}
