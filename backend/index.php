<?php
// API入口文件
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// 路由处理
if (strpos($requestUri, '/api/movies') !== false) {
    require_once __DIR__ . '/api/movies.php';
} elseif (strpos($requestUri, '/api/movie') !== false) {
    require_once __DIR__ . '/api/movie.php';
} elseif (strpos($requestUri, '/api/random') !== false) {
    require_once __DIR__ . '/api/random.php';
} elseif (strpos($requestUri, '/api/scan') !== false) {
    require_once __DIR__ . '/api/scan.php';
} elseif (strpos($requestUri, '/api/browse') !== false) {
    require_once __DIR__ . '/api/browse.php';
} elseif (strpos($requestUri, '/api/filters') !== false) {
    require_once __DIR__ . '/api/filters.php';
} else {
    // 尝试提供前端静态文件
    $path = __DIR__ . '/public' . ($requestUri === '/' ? '/index.html' : $requestUri);
    if (file_exists($path) && is_file($path)) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mimes = ['js' => 'application/javascript', 'css' => 'text/css', 'html' => 'text/html', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
        if (isset($mimes[$ext])) {
            header("Content-Type: {$mimes[$ext]}");
        }
        readfile($path);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}
