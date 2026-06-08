<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

echo json_encode([
    'stats'    => getStats(),
    'timestamp'=> time(),
]);
