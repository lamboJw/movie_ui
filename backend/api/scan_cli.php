<?php
// CLI 扫描脚本 - 通过 curl 异步调用
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/VideoScanner.php';
$config = require __DIR__ . '/../config/config.php';
$logFile = '/tmp/movie_scan.log';

try {
    file_put_contents($logFile, sprintf("[%s] 开始扫描...\n", date('Y-m-d H:i:s')), FILE_APPEND);
    $scanner = new VideoScanner($config);
    $results = $scanner->scanAll();

    $log = sprintf(
        "[%s] 扫描完成:\n 视频: scanned=%d, added=%d, updated=%d\n 图片套图: scanned=%d, added=%d, updated=%d, removed=%d\n",
        date('Y-m-d H:i:s'),
        $results['videos']['scanned'],
        $results['videos']['added'],
        $results['videos']['updated'],
        $results['image_sets']['scanned'],
        $results['image_sets']['added'],
        $results['image_sets']['updated'],
        $results['image_sets']['removed']
    );
    file_put_contents($logFile, $log, FILE_APPEND);

    echo $log;
} catch (Throwable $e) {
    $log = date('Y-m-d H:i:s') . " 扫描失败: " . $e->getMessage() . "\n". $e->getTraceAsString() . "\n";
    echo $log;
    file_put_contents($logFile, $log, FILE_APPEND);
    echo $log;
}