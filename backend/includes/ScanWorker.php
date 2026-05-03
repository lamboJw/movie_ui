<?php
// 后台扫描工作器

$config = json_decode($argv[1] ?? '{}', true);

if (empty($config)) {
    exit(1);
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/VideoScanner.php';

try {
    $scanner = new VideoScanner($config);
    $results = $scanner->scanAll();

    // 记录日志
    $logFile = '/tmp/movie_scan.log';
    $log = sprintf(
        "[%s] 扫描完成: scanned=%d, added=%d, updated=%d, errors=%d\n",
        date('Y-m-d H:i:s'),
        $results['scanned'],
        $results['added'],
        $results['updated'],
        count($results['errors'])
    );
    file_put_contents($logFile, $log, FILE_APPEND);

} catch (Exception $e) {
    $logFile = '/tmp/movie_scan.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " 扫描失败: " . $e->getMessage() . "\n", FILE_APPEND);
}