<?php
// 视频流媒体端点 - 支持 HTTP Range（快进/快退/拖拽进度）
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance()->getConnection();

$id = intval($_GET['id'] ?? 0);
$path = $_GET['path'] ?? '';

if ($id) {
    $stmt = $db->prepare("SELECT video_path FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    if (!$movie || empty($movie['video_path'])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Video not found']);
        exit;
    }
    $filePath = $movie['video_path'];
} elseif ($path) {
    $filePath = $path;
    if (strpos($filePath, '/disks/') === 0) {
        $filePath = '/home/pi' . $filePath;
    }
} else {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing id or path parameter']);
    exit;
}

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'File not found: ' . $filePath]);
    exit;
}

$fileName = basename($filePath);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$mimeTypes = [
    'mp4' => 'video/mp4',
    'mkv' => 'video/x-matroska',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'wmv' => 'video/x-ms-wmv',
    'flv' => 'video/x-flv',
    'm4v' => 'video/mp4',
    'webm' => 'video/webm',
];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Cache-Control: no-cache');

// X-Accel-Redirect 零拷贝（nginx 模式）
if (!empty($_SERVER['X_ACCEL_ENABLED'])) {
    $relativePath = substr($filePath, strlen('/home/pi'));
    header('X-Accel-Redirect: /x-accel-video' . $relativePath);
    exit;
}

// 开发服务器回退：PHP 直接流式传输
$fileSize = filesize($filePath);
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    $start = intval($matches[1]);
    $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

    if ($start >= $fileSize || $end >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));

    $f = fopen($filePath, 'rb');
    fseek($f, $start);
    $toSend = $end - $start + 1;
    $chunkSize = 8192;
    while ($toSend > 0 && !feof($f)) {
        $read = min($chunkSize, $toSend);
        echo fread($f, $read);
        $toSend -= $read;
        flush();
    }
    fclose($f);
} else {
    header('Content-Length: ' . $fileSize);
    readfile($filePath);
}
