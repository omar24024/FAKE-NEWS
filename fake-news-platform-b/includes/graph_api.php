<?php
/**
 * Client léger Facebook Graph API (test de connexion).
 */

function graphTextPreview(string $text, int $length = 120): string {
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }
    return substr($text, 0, $length);
}

function getGraphApiSettings(?PDO $db = null): array {
    $db = $db ?? getDB();
    $settings = [];
    try {
        foreach ($db->query("SELECT setting_key, setting_value FROM api_settings")->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // ignore
    }

    return [
        'app_id' => trim($settings['fb_app_id'] ?? getenv('FACEBOOK_APP_ID') ?: ''),
        'app_secret' => trim($settings['fb_app_secret'] ?? getenv('FACEBOOK_APP_SECRET') ?: ''),
        'access_token' => trim($settings['fb_access_token'] ?? getenv('FACEBOOK_ACCESS_TOKEN') ?: ''),
        'graph_version' => trim(getenv('FACEBOOK_GRAPH_VERSION') ?: FACEBOOK_GRAPH_VERSION),
    ];
}

function graphApiHttpGet(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => 'Erreur réseau: ' . ($curlErr ?: 'inconnue')];
        }

        return ['ok' => true, 'body' => $body, 'http_code' => $code];
    }

    if (!ini_get('allow_url_fopen')) {
        return [
            'ok' => false,
            'error' => 'HTTP indisponible — installez php-curl : sudo apt install php-curl',
        ];
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Erreur réseau (file_get_contents) — installez php-curl si le problème persiste.'];
    }

    $code = 200;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }

    return ['ok' => true, 'body' => $body, 'http_code' => $code];
}

function graphApiRequest(string $path, string $accessToken, string $version = 'v25.0'): array {
    if ($accessToken === '') {
        return ['ok' => false, 'error' => 'Access Token manquant — enregistrez un token dans Paramètres.'];
    }

    $path = ltrim($path, '/');
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . $path;
    $url .= (str_contains($path, '?') ? '&' : '?') . 'access_token=' . rawurlencode($accessToken);

    $http = graphApiHttpGet($url);
    if (!$http['ok']) {
        return $http;
    }

    $body = $http['body'];
    $code = $http['http_code'];

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Réponse Graph API invalide', 'http_code' => $code];
    }

    if (isset($data['error'])) {
        $msg = $data['error']['message'] ?? 'Erreur Graph API';
        $type = $data['error']['type'] ?? '';
        $codeErr = $data['error']['code'] ?? '';
        return [
            'ok' => false,
            'error' => $msg,
            'error_type' => $type,
            'error_code' => $codeErr,
            'http_code' => $code,
        ];
    }

    return ['ok' => true, 'http_code' => $code, 'data' => $data];
}

function testGraphApiConnection(?string $tokenOverride = null): array {
    $cfg = getGraphApiSettings();
    $token = $tokenOverride ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';

    $me = graphApiRequest('me?fields=id,name', $token, $version);
    if (!$me['ok']) {
        return [
            'success' => false,
            'message' => $me['error'] ?? 'Échec du test Graph API',
            'details' => $me,
            'graph_version' => $version,
            'app_id' => $cfg['app_id'] ?: null,
        ];
    }

    $profile = $me['data'];
    $isPageToken = graphApiIsPageToken($token);
    $permissions = $isPageToken ? ['ok' => false] : graphApiRequest('me/permissions', $token, $version);
    $managedPages = $isPageToken
        ? ['success' => true, 'count' => 1]
        : graphApiListManagedPages($token);

    return [
        'success' => true,
        'message' => 'Connexion Graph API réussie',
        'graph_version' => $version,
        'app_id' => $cfg['app_id'] ?: null,
        'token_type' => $isPageToken ? 'page' : 'user',
        'profile' => [
            'id' => $profile['id'] ?? null,
            'name' => $profile['name'] ?? null,
        ],
        'permissions' => $permissions['ok']
            ? array_values(array_filter(array_map(
                static fn($p) => ($p['status'] ?? '') === 'granted' ? ($p['permission'] ?? null) : null,
                $permissions['data']['data'] ?? []
            )))
            : ($isPageToken ? ['pages_read_engagement (token Page)'] : []),
        'managed_pages_count' => (int)($managedPages['count'] ?? 0),
        'note' => $isPageToken
            ? 'Token Page détecté — import Graph API uniquement pour cette Page. Autres Pages → Scraper OSINT.'
            : 'Token utilisateur. Graph API pour vos Pages administrées ; Scraper OSINT pour les Pages publiques tierces.',
    ];
}

function graphApiTokenProfile(?string $tokenOverride = null): ?array {
    $cfg = getGraphApiSettings();
    $token = $tokenOverride ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';
    if ($token === '') {
        return null;
    }

    $me = graphApiRequest('me?fields=id,name,username,category,fan_count', $token, $version);
    if (!($me['ok'] ?? false)) {
        return null;
    }

    return $me['data'];
}

function graphApiIsPageToken(?string $tokenOverride = null): bool {
    $cfg = getGraphApiSettings();
    $token = $tokenOverride ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';
    if ($token === '') {
        return false;
    }

    $accounts = graphApiRequest('me/accounts?fields=id&limit=1', $token, $version);
    if ($accounts['ok'] ?? false) {
        return false;
    }

    $err = (string)($accounts['error'] ?? '');
    if (!str_contains($err, 'accounts')) {
        return false;
    }

    return graphApiTokenProfile($token) !== null;
}

function graphApiListManagedPages(?string $tokenOverride = null): array {
    $cfg = getGraphApiSettings();
    $token = $tokenOverride ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';

    if ($token === '') {
        return ['success' => false, 'message' => 'Access Token manquant — configurez Paramètres → Graph API.'];
    }

    $result = graphApiRequest(
        'me/accounts?fields=id,name,username,access_token,fan_count,category',
        $token,
        $version
    );

    if (!($result['ok'] ?? false)) {
        if (graphApiIsPageToken($token)) {
            $profile = graphApiTokenProfile($token);
            if ($profile) {
                $pages = [[
                    'id' => (string)($profile['id'] ?? ''),
                    'name' => (string)($profile['name'] ?? 'Page Facebook'),
                    'username' => (string)($profile['username'] ?? ''),
                    'fan_count' => (int)($profile['fan_count'] ?? 0),
                    'category' => (string)($profile['category'] ?? ''),
                    'has_page_token' => true,
                ]];
                return graphEnrichPagesWithLocalAccounts($pages, [
                    'success' => true,
                    'count' => 1,
                    'pages' => $pages,
                    'token_type' => 'page',
                    'message' => 'Token Page détecté : « ' . ($profile['name'] ?? 'Page') . ' ». Import possible pour cette Page uniquement.',
                ]);
            }
        }

        return [
            'success' => true,
            'count' => 0,
            'pages' => [],
            'token_type' => 'user',
            'list_warning' => $result['error'] ?? 'Impossible de lister les Pages',
            'message' => 'Impossible de lister les Pages via Graph API. Import manuel (username/ID) via Scraper OSINT toujours disponible.',
            'details' => $result,
        ];
    }

    $pages = [];
    foreach ($result['data']['data'] ?? [] as $p) {
        $pages[] = [
            'id' => $p['id'] ?? '',
            'name' => $p['name'] ?? '',
            'username' => $p['username'] ?? '',
            'fan_count' => (int)($p['fan_count'] ?? 0),
            'category' => $p['category'] ?? '',
            'has_page_token' => !empty($p['access_token']),
        ];
    }

    $count = count($pages);
    $message = $count > 0
        ? $count . ' Page(s) administrée(s) trouvée(s).'
        : 'Token utilisateur valide, mais aucune Page administrée. '
          . 'Pour une Page publique tierce, l\'import utilisera le Scraper OSINT automatiquement.';

    return graphEnrichPagesWithLocalAccounts($pages, [
        'success' => true,
        'count' => $count,
        'pages' => $pages,
        'token_type' => 'user',
        'message' => $message,
    ]);
}

/** Associe chaque Page Graph API à son compte local et l'URL file d'attente. */
function graphEnrichPagesWithLocalAccounts(array $pages, array $result): array {
    if (empty($pages)) {
        $result['pages'] = [];
        return $result;
    }

    require_once __DIR__ . '/functions.php';
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM facebook_accounts WHERE fb_id = ? LIMIT 1');

    foreach ($pages as &$page) {
        $page['account_id'] = null;
        $page['pending_count'] = 0;
        $page['queue_url'] = publicationsQueueUrl(null, $page['name'] ?? null);

        $fbId = (string)($page['id'] ?? '');
        if ($fbId === '') {
            continue;
        }

        $stmt->execute([$fbId]);
        $row = $stmt->fetch();
        if (!$row) {
            continue;
        }

        $accountId = (int)$row['id'];
        $page['account_id'] = $accountId;

        $pendingStmt = $db->prepare('SELECT COUNT(*) FROM facebook_posts WHERE account_id = ? AND is_analyzed = 0');
        $pendingStmt->execute([$accountId]);
        $page['pending_count'] = (int)$pendingStmt->fetchColumn();
        $page['queue_url'] = publicationsQueueUrl($accountId, $page['name'] ?? null);
    }
    unset($page);

    $result['pages'] = $pages;
    return $result;
}

function graphApiGetPageAccessToken(string $pageId, ?string $userToken = null): ?string {
    $cfg = getGraphApiSettings();
    $token = $userToken ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';
    if ($token === '') {
        return null;
    }

    $profile = graphApiTokenProfile($token);
    if ($profile && ($profile['id'] ?? '') === $pageId && graphApiIsPageToken($token)) {
        return $token;
    }

    $result = graphApiRequest('me/accounts?fields=id,access_token', $token, $version);
    if (!($result['ok'] ?? false)) {
        return graphApiIsPageToken($token) ? $token : null;
    }
    foreach ($result['data']['data'] ?? [] as $p) {
        if (($p['id'] ?? '') === $pageId && !empty($p['access_token'])) {
            return $p['access_token'];
        }
    }
    return null;
}

function graphApiFetchPagePosts(string $pageId, ?string $tokenOverride = null, int $limit = 5): array {
    $cfg = getGraphApiSettings();
    $token = $tokenOverride ?: $cfg['access_token'];
    $version = $cfg['graph_version'] ?: 'v25.0';
    $limit = max(1, min($limit, 25));

    $pageToken = graphApiGetPageAccessToken($pageId, $tokenOverride) ?: ($tokenOverride ?: $cfg['access_token']);
    $fields = 'id,message,created_time,from,full_picture,permalink_url,shares,likes.summary(true),comments.summary(true)';

    $profile = graphApiTokenProfile($pageToken);
    $apiPageId = $pageId;
    if ($profile && graphApiIsPageToken($pageToken) && ($profile['id'] ?? '') === $pageId) {
        $apiPageId = 'me';
    }

    $path = rawurlencode($apiPageId) . '/posts?fields=' . rawurlencode($fields) . '&limit=' . $limit;
    $result = graphApiRequest($path, $pageToken, $version);
    if (!$result['ok']) {
        return ['success' => false, 'message' => $result['error'] ?? 'Erreur', 'details' => $result];
    }

    $posts = [];
    foreach ($result['data']['data'] ?? [] as $post) {
        $message = trim($post['message'] ?? '');
        $image = $post['full_picture'] ?? null;
        $posts[] = [
            'id' => $post['id'] ?? '',
            'message' => $message,
            'content' => $message,
            'created_time' => $post['created_time'] ?? '',
            'from' => $post['from']['name'] ?? '',
            'from_id' => $post['from']['id'] ?? $pageId,
            'permalink_url' => $post['permalink_url'] ?? ('https://www.facebook.com/' . str_replace('_', '/posts/', $post['id'] ?? '')),
            'image_url' => $image,
            'likes_count' => (int)($post['likes']['summary']['total_count'] ?? 0),
            'comments_count' => (int)($post['comments']['summary']['total_count'] ?? 0),
            'shares_count' => (int)($post['shares']['count'] ?? 0),
            'content_type' => ($message && $image) ? 'text_image' : ($image ? 'image' : 'text'),
        ];
    }

    return [
        'success' => true,
        'page_id' => $pageId,
        'count' => count($posts),
        'posts' => $posts,
    ];
}

function graphEnsurePageAccount(PDO $db, array $page): int {
    $fbId = (string)($page['id'] ?? '');
    $name = trim($page['name'] ?? 'Page Facebook') ?: 'Page Facebook';
    $fanCount = (int)($page['fan_count'] ?? 0);
    $username = trim($page['username'] ?? '');
    $fbUrl = $username ? 'https://www.facebook.com/' . $username : ('https://www.facebook.com/' . $fbId);

    $stmt = $db->prepare('SELECT id FROM facebook_accounts WHERE fb_id = ? LIMIT 1');
    $stmt->execute([$fbId]);
    $row = $stmt->fetch();
    if ($row) {
        $accId = (int)$row['id'];
        $db->prepare('UPDATE facebook_accounts SET name = ?, fb_url = ?, followers_count = GREATEST(followers_count, ?), type = ? WHERE id = ?')
            ->execute([$name, $fbUrl, $fanCount, 'page', $accId]);
        return $accId;
    }

    $db->prepare("
        INSERT INTO facebook_accounts (fb_id, name, type, category, followers_count, fb_url, is_monitored, risk_level)
        VALUES (?, ?, 'page', ?, ?, ?, 1, 'low')
    ")->execute([$fbId, $name, $page['category'] ?? null, $fanCount, $fbUrl]);

    return (int)$db->lastInsertId();
}

function graphSaveImportedPost(PDO $db, int $accountId, array $post, string $authorName): array {
    $graphId = (string)($post['id'] ?? '');
    $content = trim($post['content'] ?? $post['message'] ?? '');
    $fbUrl = trim($post['permalink_url'] ?? '');
    if ($fbUrl === '' && $graphId !== '') {
        $fbUrl = 'https://www.facebook.com/' . $graphId;
    }

    $fbPostId = $graphId !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '', $graphId) : hash('sha1', $fbUrl);
    if (strlen($fbPostId) > 190) {
        $fbPostId = hash('sha1', $graphId ?: $fbUrl);
    }

    $publishedAt = null;
    if (!empty($post['created_time'])) {
        try {
            $publishedAt = (new DateTime($post['created_time']))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $publishedAt = null;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO facebook_posts
          (fb_post_id, account_id, author_name, content, image_url, content_type, fb_post_url, link_status,
           likes_count, shares_count, comments_count, published_at, is_analyzed)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE
          account_id = VALUES(account_id),
          author_name = VALUES(author_name),
          content = VALUES(content),
          image_url = VALUES(image_url),
          content_type = VALUES(content_type),
          link_status = 'active',
          likes_count = VALUES(likes_count),
          shares_count = VALUES(shares_count),
          comments_count = VALUES(comments_count),
          published_at = COALESCE(VALUES(published_at), published_at),
          fetched_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $fbPostId,
        $accountId,
        $authorName,
        mb_substr($content, 0, 5000),
        $post['image_url'] ?? null,
        $post['content_type'] ?? 'text',
        $fbUrl,
        (int)($post['likes_count'] ?? 0),
        (int)($post['shares_count'] ?? 0),
        (int)($post['comments_count'] ?? 0),
        $publishedAt,
    ]);

    $idStmt = $db->prepare('SELECT id FROM facebook_posts WHERE fb_post_id = ? LIMIT 1');
    $idStmt->execute([$fbPostId]);
    $row = $idStmt->fetch();

    return [
        'post_id' => (int)($row['id'] ?? 0),
        'fb_post_url' => $fbUrl,
        'graph_id' => $graphId,
        'skipped' => false,
    ];
}

function osintImportPagePosts(string $pageId, int $limit = 10, bool $autoAnalyze = true): array {
    $pythonBin = getPythonExecutable();
    $scriptPath = realpath(__DIR__ . '/../python-ai/facebook_post_extractor.py');
    if (!$scriptPath) {
        return ['success' => false, 'message' => 'Script extracteur introuvable'];
    }

    $limit = max(1, min($limit, 25));
    $cmd = sprintf(
        '%s %s --page %s --limit %d --json --save-db',
        escapeshellarg($pythonBin),
        escapeshellarg($scriptPath),
        escapeshellarg($pageId),
        $limit
    );

    $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptorspec, $pipes, null, getPythonProcessEnv());
    if (!is_resource($process)) {
        return ['success' => false, 'message' => 'Impossible de lancer le Scraper OSINT'];
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $result = json_decode($output ?: '', true);
    if (!is_array($result) || ($result['status'] ?? '') !== 'success') {
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Import OSINT échoué',
            'details' => $result,
            'source' => 'osint_scraper',
        ];
    }

    require_once __DIR__ . '/ai_client.php';
    $db = getDB();
    $imported = [];
    $analyzed = 0;
    $skipped = 0;

    foreach ($result['posts'] ?? [] as $post) {
        $fbUrl = trim($post['fb_post_url'] ?? '');
        if ($fbUrl === '') {
            $skipped++;
            continue;
        }

        $stmt = $db->prepare('SELECT id, content FROM facebook_posts WHERE fb_post_url = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$fbUrl]);
        $row = $stmt->fetch();
        if (!$row) {
            $skipped++;
            continue;
        }

        $entry = [
            'post_id' => (int)$row['id'],
            'fb_post_url' => $fbUrl,
            'preview' => graphTextPreview($row['content'] ?? '', 120),
        ];

        if ($autoAnalyze && aiAutoAnalyzeEnabled() && trim($row['content'] ?? '') !== '') {
            $analysis = aiAnalyzePost((int)$row['id']);
            if ($analysis && !isset($analysis['error'])) {
                $entry['analysis'] = [
                    'category' => $analysis['category'] ?? null,
                    'confidence' => $analysis['confidence'] ?? null,
                ];
                $analyzed++;
            }
        }

        $imported[] = $entry;
    }

    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if ($user && count($imported) > 0) {
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, 'success', 0, NOW())
        ")->execute([
            $user['id'],
            'Import OSINT terminé',
            count($imported) . ' publication(s) importée(s) depuis « ' . $pageId . ' » (Scraper).',
        ]);
    }

    $accountId = null;
    if (!empty($imported[0]['post_id'])) {
        $accStmt = $db->prepare('SELECT account_id FROM facebook_posts WHERE id = ? LIMIT 1');
        $accStmt->execute([(int)$imported[0]['post_id']]);
        $accountId = (int)($accStmt->fetchColumn() ?: 0) ?: null;
    }

    require_once __DIR__ . '/functions.php';

    return [
        'success' => true,
        'message' => count($imported) . ' publication(s) importée(s) via Scraper OSINT',
        'page' => ['id' => $pageId, 'name' => $pageId, 'page_url' => $result['page_url'] ?? null],
        'account_id' => $accountId,
        'queue_url' => publicationsQueueUrl($accountId, $pageId),
        'imported_count' => count($imported),
        'analyzed_count' => $analyzed,
        'skipped_empty' => $skipped,
        'imported' => $imported,
        'source' => 'osint_scraper',
        'fallback' => true,
    ];
}

function graphImportPagePosts(string $pageId, int $limit = 10, bool $autoAnalyze = true): array {
    $db = getDB();
    $cfg = getGraphApiSettings();

    if ($cfg['access_token'] === '') {
        return ['success' => false, 'message' => 'Token Graph API manquant — Paramètres → Facebook Graph API.'];
    }

    $pagesList = graphApiListManagedPages();
    if (!($pagesList['success'] ?? false)) {
        $pagesList = ['success' => true, 'count' => 0, 'pages' => []];
    }

    $pageMeta = null;
    foreach ($pagesList['pages'] ?? [] as $p) {
        if (($p['id'] ?? '') === $pageId) {
            $pageMeta = $p;
            break;
        }
    }

    if (!$pageMeta) {
        // Import manuel par ID/username (Page publique ou token avec accès)
        $pageMeta = [
            'id' => $pageId,
            'name' => 'Page ' . $pageId,
            'username' => '',
            'fan_count' => 0,
            'category' => '',
        ];
    }

    $fetch = graphApiFetchPagePosts($pageId, null, $limit);
    if (!($fetch['success'] ?? false) || (int)($fetch['count'] ?? 0) === 0) {
        $osint = osintImportPagePosts($pageId, $limit, $autoAnalyze);
        if ($osint['success'] ?? false) {
            $graphNote = ($fetch['success'] ?? false)
                ? 'Graph API : 0 post — '
                : 'Graph API indisponible — ';
            $osint['message'] .= ' (' . $graphNote . 'fallback Scraper OSINT)';
            return $osint;
        }
        if ($fetch['success'] ?? false) {
            return [
                'success' => true,
                'message' => '0 publication via Graph API et Scraper OSINT pour « ' . $pageId . ' »',
                'page' => $pageMeta,
                'imported_count' => 0,
                'analyzed_count' => 0,
                'skipped_empty' => 0,
                'imported' => [],
                'source' => 'graph_api',
            ];
        }
        return [
            'success' => false,
            'message' => 'Import échoué pour « ' . $pageId . ' ». Graph API : '
                . ($fetch['message'] ?? 'accès refusé')
                . ' | Scraper OSINT : ' . ($osint['message'] ?? 'échec'),
            'details' => ['graph_api' => $fetch, 'osint' => $osint],
        ];
    }

    require_once __DIR__ . '/ai_client.php';

    $accountId = graphEnsurePageAccount($db, $pageMeta);
    $authorName = $pageMeta['name'] ?? 'Page Facebook';
    $imported = [];
    $analyzed = 0;
    $skipped = 0;

    foreach ($fetch['posts'] as $post) {
        if (trim($post['content'] ?? '') === '' && empty($post['image_url'])) {
            $skipped++;
            continue;
        }

        $saved = graphSaveImportedPost($db, $accountId, $post, $authorName);
        $entry = [
            'post_id' => $saved['post_id'],
            'graph_id' => $saved['graph_id'],
            'fb_post_url' => $saved['fb_post_url'],
            'preview' => graphTextPreview($post['content'] ?? '', 120),
        ];

        if ($autoAnalyze && aiAutoAnalyzeEnabled() && $saved['post_id'] > 0 && trim($post['content'] ?? '') !== '') {
            $analysis = aiAnalyzePost($saved['post_id']);
            if ($analysis && !isset($analysis['error'])) {
                $entry['analysis'] = [
                    'category' => $analysis['category'] ?? null,
                    'confidence' => $analysis['confidence'] ?? null,
                ];
                $analyzed++;
            }
        }

        $imported[] = $entry;
    }

    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if ($user && count($imported) > 0) {
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, 'success', 0, NOW())
        ")->execute([
            $user['id'],
            'Import Graph API terminé',
            count($imported) . ' publication(s) importée(s) depuis « ' . ($pageMeta['name'] ?? 'Page') . ' ».',
        ]);
    }

    require_once __DIR__ . '/functions.php';

    return [
        'success' => true,
        'message' => count($imported) . ' publication(s) importée(s) via Graph API',
        'page' => $pageMeta,
        'account_id' => $accountId,
        'queue_url' => publicationsQueueUrl($accountId, $authorName),
        'imported_count' => count($imported),
        'analyzed_count' => $analyzed,
        'skipped_empty' => $skipped,
        'imported' => $imported,
        'source' => 'graph_api',
    ];
}
