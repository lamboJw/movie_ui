<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/VideoScanner.php';

$config = require __DIR__ . '/../config/config.php';

try {
    $scanner = new VideoScanner($config);
    $results = $scanner->scanAll();

    echo json_encode([
        'success' => true,
        'message' => '扫描完成',
        'scan_mode' => $config['scan_mode'] ?? 'local',
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '扫描失败: ' . $e->getMessage()
    ]);
}
