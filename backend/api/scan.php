<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/VideoScanner.php';

$config = require __DIR__ . '/../config/config.php';

try {
    // 异步执行扫描
    $scanScript = __DIR__ . '/../includes/ScanWorker.php';
    $logFile = '/tmp/movie_scan.log';

    // fork 后台进程执行扫描
    $cmd = sprintf(
        'php %s %s > %s 2>&1 &',
        escapeshellarg($scanScript),
        escapeshellarg(json_encode($config)),
        escapeshellarg($logFile)
    );
    exec($cmd);

    echo json_encode([
        'success' => true,
        'message' => '扫描已在后台开始'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '扫描失败: ' . $e->getMessage()
    ]);
}
