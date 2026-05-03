<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/VideoScanner.php';

$config = require __DIR__ . '/../config/config.php';

try {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    $scheme = (!empty($_SERVER['HTTPS']) || ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $host;

$cmd = sprintf(
        'curl -s -o /dev/null -X POST %s/api/scan_cli > /dev/null 2>&1 &',
        escapeshellarg($baseUrl)
    );
    exec($cmd);

    echo json_encode([
        'success' => true,
        'message' => '扫描已在后台开始',
        'data' => [
            'cmd' => $cmd
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '扫描失败: ' . $e->getMessage()
    ]);
}