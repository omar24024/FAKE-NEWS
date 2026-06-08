<?php
/**
 * Client PHP vers le serveur IA persistant (FastAPI).
 * Fallback vers analyze.py en shell si le serveur est indisponible.
 */

function getAiServerUrl(): string {
    $url = getenv('AI_SERVER_URL') ?: 'http://127.0.0.1:8765';
    return rtrim($url, '/');
}

function aiHttpGet(string $url, int $timeout = 120): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }

    if (!ini_get('allow_url_fopen')) {
        return null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) && $body !== '' ? $body : null;
}

function aiHttpPost(string $url, ?array $payload, int $timeout = 120): ?string {
    $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = $json;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }

    if (!ini_get('allow_url_fopen')) {
        return null;
    }

    $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
    if ($json !== null) {
        $headers .= 'Content-Length: ' . strlen($json) . "\r\n";
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => $headers,
            'content' => $json ?? '',
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) && $body !== '' ? $body : null;
}

function aiServiceAvailable(): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $body = aiHttpGet(getAiServerUrl() . '/health', 2);
    $cached = is_string($body) && str_contains($body, '"ok"');
    return $cached;
}

function aiHttpRequest(string $method, string $path, ?array $payload = null, int $timeout = 120): ?array {
    $url = getAiServerUrl() . $path;
    $body = strtoupper($method) === 'POST'
        ? aiHttpPost($url, $payload, $timeout)
        : aiHttpGet($url, $timeout);

    if ($body === null) {
        return null;
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function aiRunPythonScript(array $args): ?array {
    $pythonBin = getPythonExecutable();
    $scriptPath = realpath(__DIR__ . '/../python-ai/analyze.py');
    if (!$scriptPath) {
        return null;
    }

    $parts = [escapeshellarg($pythonBin), escapeshellarg($scriptPath)];
    foreach ($args as $arg) {
        $parts[] = escapeshellarg($arg);
    }
    $parts[] = '--json';
    $cmd = implode(' ', $parts);

    $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptorspec, $pipes, null, getPythonProcessEnv());
    if (!is_resource($process)) {
        return null;
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (!$output) {
        return null;
    }

    $decoded = json_decode($output, true);
    return is_array($decoded) ? $decoded : null;
}

function aiAnalyzeText(string $text): ?array {
    $result = aiHttpRequest('POST', '/analyze', ['text' => $text]);
    if ($result !== null) {
        return $result;
    }
    return aiRunPythonScript(['--text', $text]);
}

function aiAnalyzePost(int $postId): ?array {
    $result = aiHttpRequest('POST', "/analyze/post/{$postId}");
    if ($result !== null) {
        return $result;
    }
    return aiRunPythonScript(['--post-id', (string)$postId]);
}

function aiAnalyzeAllPending(): ?array {
    $result = aiHttpRequest('POST', '/analyze/all', null, 600);
    if ($result !== null) {
        return $result['results'] ?? $result;
    }
    return aiRunPythonScript(['--all']);
}

function aiAutoAnalyzeEnabled(): bool {
    $val = getenv('AI_AUTO_ANALYZE');
    return $val === false || $val === '' || strtolower((string)$val) !== 'false';
}

/**
 * Sauvegarde une analyse en base (utilisé quand seul le texte est analysé).
 */
function saveAnalysisResult(PDO $db, int $postId, array $analysis): void {
    if (!isset($analysis['category'])) {
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO ai_analysis
        (post_id, category, confidence_score, risk_level, model_used)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            category = VALUES(category),
            confidence_score = VALUES(confidence_score),
            risk_level = VALUES(risk_level),
            model_used = VALUES(model_used)
    ");

    $stmt->execute([
        $postId,
        $analysis['category'] ?? 'reliable',
        $analysis['confidence'] ?? 0,
        $analysis['risk_level'] ?? 'low',
        $analysis['model'] ?? 'gbert-hassaniya',
    ]);

    $db->prepare("UPDATE facebook_posts SET is_analyzed = 1 WHERE id = ?")->execute([$postId]);
}
