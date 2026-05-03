<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/NfoParser.php';

$config = require __DIR__ . '/../config/config.php';
$db = Database::getInstance()->getConnection();

$path = $_GET['path'] ?? '';
$path = trim($path);

// 根目录映射
$rootFolders = [
    'Media' => '/home/pi/disks/disk/.hidden/Media',
    'jav' => '/home/pi/disks/disk2/.hidden/jav'
];

if (empty($path)) {
    echo json_encode([
        'folders' => [
            ['name' => 'Media', 'path' => 'Media'],
            ['name' => 'jav', 'path' => 'jav']
        ],
        'files' => [],
        'current_path' => '',
        'can_go_back' => false
    ]);
    exit;
}

// 判断是否可返回：空 path 时为 false，其他都可以返回（包括 Media 和 jav）
$canGoBack = !empty($path);

// 转换 path 到实际目录
$basePath = '';
if ($path === 'Media') {
    $basePath = '/home/pi/disks/disk/.hidden/Media';
} elseif ($path === 'jav') {
    $basePath = '/home/pi/disks/disk2/.hidden/jav';
} elseif (strpos($path, 'Media/') === 0) {
    $subPath = substr($path, 5);
    $basePath = '/home/pi/disks/disk/.hidden/Media' . $subPath;
} elseif (strpos($path, 'jav/') === 0) {
    $subPath = substr($path, 3);
    $basePath = '/home/pi/disks/disk2/.hidden/jav' . $subPath;
}

if (empty($basePath) || !is_dir($basePath)) {
    echo json_encode([
        'folders' => [],
        'files' => [],
        'current_path' => $path,
        'can_go_back' => $canGoBack
    ]);
    exit;
}

// 扫描目录获取文件夹
$folders = [];
$iterator = new DirectoryIterator($basePath);
foreach ($iterator as $item) {
    if ($item->isDot()) continue;
    if ($item->isDir()) {
        $folders[] = [
            'name' => $item->getFilename(),
            'path' => $path . '/' . $item->getFilename()
        ];
    }
}

// 从数据库获取该文件夹下的所有视频（只搜索当前层）
// 使用 / 分隔符计数，在当前层只有一个路径部分（即没有额外的 /）
$files = [];
$videoPathPrefix = rtrim($basePath, '/');
$escapedPrefix = str_replace(['%', '_'], ['\%', '\_'], $videoPathPrefix);
$stmt = $db->prepare("
    SELECT id, title, original_title, year, thumb, video_path
    FROM movies 
    WHERE video_path LIKE ?
    AND video_path NOT LIKE ?
");
$stmt->execute([$escapedPrefix . '/%', $escapedPrefix . '/%/%']);
$files = $stmt->fetchAll();

// 处理 thumb 和 video_path 路径
foreach ($files as &$file) {
    if (!empty($file['thumb'])) {
        $file['thumb'] = NfoParser::addDisksPrefix($file['thumb'], $file['video_path']);
    }
    // 去掉 /home/pi 前缀
    if (strpos($file['video_path'], '/home/pi') === 0) {
        $file['video_path'] = '/disks' . substr($file['video_path'], 8);
    }
}

usort($folders, fn($a, $b) => strcmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcmp($a['title'] ?? '', $b['title'] ?? ''));

echo json_encode([
    'folders' => $folders,
    'files' => $files,
    'current_path' => $path,
    'can_go_back' => $canGoBack
]);