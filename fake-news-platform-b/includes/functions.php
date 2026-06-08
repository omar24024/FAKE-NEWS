<?php
// ============================================================
// Fonctions utilitaires globales
// ============================================================

/** Expression SQL: nom d'auteur affiché (colonne post + compte). */
function sqlPostAuthorExpr(string $p = 'p', string $acc = 'acc'): string {
    return "COALESCE(NULLIF(TRIM({$p}.author_name), ''), NULLIF(TRIM({$acc}.name), ''))";
}

/** Nom d'auteur pour l'affichage UI. */
function postAuthorName(array $row): string {
    $name = trim($row['author_name'] ?? $row['account_name'] ?? $row['display_author'] ?? '');
    $lower = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    if ($name === '' || in_array($lower, ['inconnu', 'auteur inconnu', 'auteur non identifié', 'unknown'], true)) {
        return 'Auteur non identifié';
    }
    return $name;
}

/** Statistiques scraper / tableau de bord (données réelles). */
function getScraperStats(): array {
    $db = getDB();
    $row = $db->query("
        SELECT
            COUNT(*) AS total_posts,
            SUM(CASE WHEN is_analyzed = 0 THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN is_analyzed = 1 THEN 1 ELSE 0 END) AS analyzed,
            MAX(fetched_at) AS last_extraction
        FROM facebook_posts
    ")->fetch();

    return [
        'total_posts' => (int)($row['total_posts'] ?? 0),
        'pending' => (int)($row['pending'] ?? 0),
        'analyzed' => (int)($row['analyzed'] ?? 0),
        'last_extraction' => $row['last_extraction'] ?? null,
    ];
}

function getStats(): array {
    $db = getDB();
    $total = $db->query("SELECT COUNT(*) FROM facebook_posts WHERE is_analyzed = 1")->fetchColumn();
    $fake = $db->query("SELECT COUNT(*) FROM ai_analysis WHERE category = 'fake_news'")->fetchColumn();
    $disinfo = $db->query("SELECT COUNT(*) FROM ai_analysis WHERE category = 'disinformation'")->fetchColumn();
    $hate = $db->query("SELECT COUNT(*) FROM ai_analysis WHERE category = 'hate_speech'")->fetchColumn();
    $reliable = $db->query("SELECT COUNT(*) FROM ai_analysis WHERE category = 'reliable'")->fetchColumn();
    return compact('total', 'fake', 'disinfo', 'hate', 'reliable');
}

function getPosts(int $page = 1, int $perPage = 5, string $category = '', string $search = '', string $dateFrom = '', string $dateTo = ''): array {
    $db = getDB();
    $offset = ($page - 1) * $perPage;
    $params = [];
    $where = ['p.is_analyzed = 1'];

    if ($category) {
        $where[] = 'a.category = ?';
        $params[] = $category;
    }
    if ($search) {
        $where[] = '(p.content LIKE ? OR acc.name LIKE ? OR p.author_name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($dateFrom) {
        $where[] = 'p.published_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $where[] = 'p.published_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $authorExpr = sqlPostAuthorExpr();

    $countParams = $params;
    $countSQL = "SELECT COUNT(*) FROM facebook_posts p
                 LEFT JOIN ai_analysis a ON p.id = a.post_id
                 LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
                 $whereSQL";
    $total = $db->prepare($countSQL);
    $total->execute($countParams);
    $totalCount = (int) $total->fetchColumn();

    $sql = "SELECT p.*, a.category, a.confidence_score, a.risk_level, a.id as analysis_id,
                   acc.name as account_name, acc.type as account_type, acc.profile_picture,
                   acc.fb_url as account_url,
                   {$authorExpr} AS display_author
            FROM facebook_posts p
            LEFT JOIN ai_analysis a ON p.id = a.post_id
            LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
            $whereSQL
            ORDER BY COALESCE(p.published_at, p.fetched_at) DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    return [
        'posts' => $posts,
        'total' => $totalCount,
        'pages' => ceil($totalCount / $perPage),
        'current' => $page,
    ];
}

/**
 * Récupérer une liste de publications (analysées ou non) avec filtres.
 *
 * Options supportées:
 * - analyzed: null|bool (null = tous, true = analysés, false = non analysés)
 * - category: string (filtre sur ai_analysis.category)
 * - search: string (contenu + nom compte)
 * - dateFrom/dateTo: YYYY-MM-DD (sur published_at)
 * - accountId: int
 */
function getPostsList(int $page = 1, int $perPage = 20, array $opts = []): array {
    $db = getDB();
    $offset = ($page - 1) * $perPage;
    $params = [];
    $where = [];

    if (array_key_exists('analyzed', $opts) && $opts['analyzed'] !== null) {
        $where[] = 'p.is_analyzed = ?';
        $params[] = $opts['analyzed'] ? 1 : 0;
    }

    if (!empty($opts['category'])) {
        $where[] = 'a.category = ?';
        $params[] = $opts['category'];
    }

    if (!empty($opts['search'])) {
        $where[] = '(p.content LIKE ? OR acc.name LIKE ? OR p.author_name LIKE ?)';
        $params[] = '%' . $opts['search'] . '%';
        $params[] = '%' . $opts['search'] . '%';
        $params[] = '%' . $opts['search'] . '%';
    }

    if (!empty($opts['dateFrom'])) {
        $where[] = 'p.published_at >= ?';
        $params[] = $opts['dateFrom'] . ' 00:00:00';
    }
    if (!empty($opts['dateTo'])) {
        $where[] = 'p.published_at <= ?';
        $params[] = $opts['dateTo'] . ' 23:59:59';
    }

    if (!empty($opts['accountId'])) {
        $where[] = 'p.account_id = ?';
        $params[] = (int)$opts['accountId'];
    }

    if (!empty($opts['authorName'])) {
        $where[] = sqlPostAuthorExpr() . ' = ?';
        $params[] = $opts['authorName'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $authorExpr = sqlPostAuthorExpr();

    $countSQL = "SELECT COUNT(*)
                 FROM facebook_posts p
                 LEFT JOIN ai_analysis a ON p.id = a.post_id
                 LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
                 $whereSQL";
    $totalStmt = $db->prepare($countSQL);
    $totalStmt->execute($params);
    $totalCount = (int)$totalStmt->fetchColumn();

    $sql = "SELECT p.*, a.category, a.confidence_score, a.risk_level, a.id as analysis_id,
                   acc.name as account_name, acc.type as account_type, acc.profile_picture,
                   acc.fb_url as account_url,
                   {$authorExpr} AS display_author
            FROM facebook_posts p
            LEFT JOIN ai_analysis a ON p.id = a.post_id
            LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
            $whereSQL
            ORDER BY COALESCE(p.published_at, p.fetched_at) DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    return [
        'posts' => $posts,
        'total' => $totalCount,
        'pages' => max(1, (int)ceil($totalCount / $perPage)),
        'current' => $page,
    ];
}

/**
 * Comptes surveillés dérivés des publications extraites (OSINT).
 * Agrège facebook_posts par auteur (nom du compte Facebook lié).
 */
function getMonitoredAccounts(): array {
    $db = getDB();
    $authorExpr = sqlPostAuthorExpr('fp', 'acc');

    $sql = "
        SELECT
            MIN(fp.account_id) AS account_id,
            {$authorExpr} AS author_name,
            MAX(acc.type) AS account_type,
            MAX(acc.fb_id) AS fb_id,
            MAX(acc.is_monitored) AS is_monitored,
            MAX(acc.profile_picture) AS profile_picture,
            MAX(acc.fb_url) AS account_url,
            MAX(acc.followers_count) AS followers_count,
            COUNT(fp.id) AS post_count,
            SUM(CASE WHEN fp.is_analyzed = 1 THEN 1 ELSE 0 END) AS analyzed_count,
            SUM(CASE WHEN fp.is_analyzed = 0 THEN 1 ELSE 0 END) AS pending_count,
            MAX(COALESCE(fp.published_at, fp.fetched_at)) AS latest_activity,
            MAX(CASE a.risk_level
                WHEN 'critical' THEN 4
                WHEN 'high' THEN 3
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 1
                ELSE 0
            END) AS risk_rank,
            SUBSTRING_INDEX(
                GROUP_CONCAT(a.category ORDER BY a.created_at DESC SEPARATOR ','),
                ',', 1
            ) AS last_category,
            SUBSTRING_INDEX(
                GROUP_CONCAT(a.confidence_score ORDER BY a.created_at DESC SEPARATOR ','),
                ',', 1
            ) AS last_confidence
        FROM facebook_posts fp
        LEFT JOIN facebook_accounts acc ON fp.account_id = acc.id
        LEFT JOIN ai_analysis a ON a.post_id = fp.id
        GROUP BY {$authorExpr}
        HAVING author_name IS NOT NULL
           AND TRIM(author_name) != ''
           AND LOWER(author_name) NOT IN ('auteur inconnu', 'inconnu', 'auteur non identifié', 'unknown')
        ORDER BY latest_activity DESC, post_count DESC
    ";

    $rows = $db->query($sql)->fetchAll();

    foreach ($rows as &$row) {
        $row['risk_level'] = match((int)($row['risk_rank'] ?? 0)) {
            4 => 'critical',
            3 => 'high',
            2 => 'medium',
            1 => 'low',
            default => 'low',
        };
        unset($row['risk_rank']);

        $pending = (int)($row['pending_count'] ?? 0);
        $analyzed = (int)($row['analyzed_count'] ?? 0);
        if ($analyzed > 0 && $pending === 0) {
            $row['analysis_status'] = 'complete';
            $row['analysis_status_label'] = 'Analyses à jour';
        } elseif ($analyzed > 0 && $pending > 0) {
            $row['analysis_status'] = 'partial';
            $row['analysis_status_label'] = $pending . ' en attente';
        } else {
            $row['analysis_status'] = 'pending';
            $row['analysis_status_label'] = 'Non analysées';
        }
    }
    unset($row);

    return $rows;
}

/** URL vers la file d'attente d'analyse (publications non analysées). */
function publicationsQueueUrl(?int $accountId = null, ?string $authorName = null): string {
    $params = ['filter' => 'unanalyzed'];
    if ($accountId) {
        $params['account'] = $accountId;
    }
    if ($authorName !== null && trim($authorName) !== '') {
        $params['author'] = trim($authorName);
    }
    return APP_URL . '/pages/publications.php?' . http_build_query($params);
}

/** URL vers toutes les publications d'un compte (analysées ou non). */
function publicationsAccountUrl(int $accountId, string $authorName): string {
    return APP_URL . '/pages/publications.php?' . http_build_query([
        'account' => $accountId,
        'author' => trim($authorName),
    ]);
}

/** URL d'avatar réelle (exclut les chemins de démo seedés). */
function isRealProfilePicture(?string $url): bool {
    if (!$url || trim($url) === '') {
        return false;
    }
    if (str_contains($url, 'assets/images/avatars/')) {
        return false;
    }
    return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
}

function getPostDetails(int $postId): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, a.category, a.confidence_score, a.risk_level, a.id as analysis_id, a.model_used,
               a.manual_review, a.human_category, a.ai_category_original, a.analysis_notes,
               a.reviewed_at, a.reviewed_by,
               rev.full_name AS reviewer_name,
               acc.name as account_name, acc.type as account_type, acc.profile_picture,
               acc.fb_url as account_url, acc.followers_count,
               " . sqlPostAuthorExpr() . " AS display_author
        FROM facebook_posts p
        LEFT JOIN ai_analysis a ON p.id = a.post_id
        LEFT JOIN users rev ON rev.id = a.reviewed_by
        LEFT JOIN facebook_accounts acc ON p.account_id = acc.id
        WHERE p.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) return null;

    // Keywords
    if ($post['analysis_id']) {
        $kw = $db->prepare("SELECT * FROM detected_keywords WHERE analysis_id = ? ORDER BY weight DESC");
        $kw->execute([$post['analysis_id']]);
        $post['keywords'] = $kw->fetchAll();
    }

    // Références légales mauritaniennes
    $legalCatMap = [
        'fake_news' => 'fake_news',
        'disinformation' => 'fake_news',
        'misinformation' => 'fake_news',
        'hate_speech' => 'hate_speech',
        'cyberbullying' => 'cyber',
        'violence' => 'general',
        'propaganda' => 'general',
        'reliable' => 'general',
    ];
    $legalCat = $legalCatMap[$post['category'] ?? ''] ?? 'general';
    $legal = $db->prepare("SELECT * FROM legal_references WHERE category = ? OR category = 'general' ORDER BY category DESC LIMIT 4");
    $legal->execute([$legalCat]);
    $post['legal_refs'] = $legal->fetchAll();

    // Politiques Facebook (Community Standards)
    $post['facebook_policies'] = [];
    try {
        $fbCatMap = [
            'fake_news' => ['fake_news', 'misinformation', 'general'],
            'disinformation' => ['disinformation', 'misinformation', 'general'],
            'misinformation' => ['misinformation', 'fake_news', 'general'],
            'hate_speech' => ['hate_speech', 'general'],
            'cyberbullying' => ['cyberbullying', 'general'],
            'violence' => ['violence', 'general'],
            'propaganda' => ['propaganda', 'general'],
            'reliable' => ['general'],
        ];
        $fbCats = $fbCatMap[$post['category'] ?? ''] ?? ['general'];
        $placeholders = implode(',', array_fill(0, count($fbCats), '?'));
        $fb = $db->prepare("SELECT * FROM facebook_policies WHERE category IN ($placeholders) LIMIT 4");
        $fb->execute($fbCats);
        $post['facebook_policies'] = $fb->fetchAll();
    } catch (PDOException $e) {
        $post['facebook_policies'] = [];
    }

    if (empty($post['keywords'])) {
        $post['keywords'] = [];
    }

    $post['comments'] = getPostComments($postId);

    return $post;
}

/** Commentaires texte extraits + analyse GBERT pour une publication. */
function getPostComments(int $postId): array {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT id, author_name, content, sort_order, is_analyzed,
                   category, confidence_score, risk_level, model_used, analyzed_at, created_at
            FROM post_comments
            WHERE post_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function contentTypeLabel(?string $type): string {
    return match($type) {
        'text_image' => 'Texte + Image',
        'image' => 'Image',
        'text' => 'Texte',
        default => 'Texte',
    };
}

function linkStatusLabel(?string $status): string {
    return match($status) {
        'active' => 'Actif',
        'inaccessible' => 'Inaccessible',
        'deleted' => 'Supprimé',
        default => 'Inconnu',
    };
}

function linkStatusClass(?string $status): string {
    return match($status) {
        'active' => 'badge-reliable',
        'inaccessible', 'deleted' => 'badge-fake',
        default => 'badge-default',
    };
}

/** Détecte un contenu scrape invalide (page login Facebook, JS brut). */
function isInvalidScrapedContent(?string $content, ?string $author = null): bool {
    if (!$content || strlen(trim($content)) < 20) {
        return true;
    }
    $blob = strtolower($content . ' ' . ($author ?? ''));
    if (str_starts_with(trim($content), 'requireLazy')) {
        return true;
    }
    $markers = ['se connecter', 'mot de passe', 'bootstrapwebsession', 'créer une page', 'log in'];
    $hits = 0;
    foreach ($markers as $m) {
        if (str_contains($blob, $m)) {
            $hits++;
        }
    }
    return $hits >= 2;
}

function categoryLabel(string $cat): string {
    return match($cat) {
        'fake_news' => 'Fake news',
        'disinformation' => 'Désinformation',
        'hate_speech' => 'Discours de haine',
        'misinformation' => 'Mauvaise information',
        'cyberbullying' => 'Cyberharcèlement',
        'violence' => 'Violence',
        'propaganda' => 'Propagande',
        'reliable' => 'Information fiable',
        default => ucfirst(str_replace('_', ' ', $cat)),
    };
}

function categoryClass(string $cat): string {
    return match($cat) {
        'fake_news' => 'badge-fake',
        'disinformation' => 'badge-disinfo',
        'hate_speech' => 'badge-hate',
        'misinformation' => 'badge-disinfo',
        'cyberbullying' => 'badge-hate',
        'violence' => 'badge-fake',
        'propaganda' => 'badge-disinfo',
        'reliable' => 'badge-reliable',
        default => 'badge-default',
    };
}

function isGbertModel(?string $model): bool {
    if (!$model) {
        return true;
    }
    $m = strtolower($model);
    return str_contains($m, 'gbert') || str_contains($m, 'hassaniya');
}

function modelLabel(?string $model): string {
    if (isGbertModel($model)) {
        return 'GBERT Hassaniya';
    }
    return $model ?: 'Modèle IA';
}

function riskLabel(string $risk): string {
    return match($risk) {
        'critical' => 'Critique',
        'high' => 'Élevé',
        'medium' => 'Modéré',
        'low' => 'Faible',
        default => 'Inconnu',
    };
}

function formatDate(string $dt): string {
    return date('d/m/Y H:i', strtotime($dt));
}

function avatarSVG(string $name, int $size = 44): string {
    $colors = ['#4F46E5','#0EA5E9','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4'];
    $i = crc32($name) % count($colors);
    $color = $colors[abs($i)];
    $initials = strtoupper(substr($name, 0, 1));
    $parts = explode(' ', $name);
    if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));
    return "<svg width='$size' height='$size' viewBox='0 0 $size $size' xmlns='http://www.w3.org/2000/svg'><rect width='$size' height='$size' rx='" . ($size/2) . "' fill='$color'/><text x='50%' y='50%' font-family='DM Sans,sans-serif' font-size='" . ($size*0.36) . "' fill='white' text-anchor='middle' dominant-baseline='central'>" . htmlspecialchars($initials) . "</text></svg>";
}
