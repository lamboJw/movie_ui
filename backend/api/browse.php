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
        'image_sets' => [],
        'current_path' => '',
        'can_go_back' => false
    ]);
    exit;
}

$canGoBack = !empty($path);

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
        'image_sets' => [],
        'current_path' => $path,
        'can_go_back' => $canGoBack
    ]);
    exit;
}

$folders = [];
$iterator = new DirectoryIterator($basePath);

$imageSetPaths = [];
$imageSets = [];
if (!empty($path)) {
    $stmt = $db->prepare("
        SELECT id, title, cover_image, image_count, folder_path
        FROM image_sets
        WHERE parent_path = ? OR parent_path = ?
        ORDER BY date_added DESC
    ");
    $stmt->execute([$path, '/' . $path]);
    $imageSets = $stmt->fetchAll();

    foreach ($imageSets as &$set) {
        $dirPath = $set['folder_path'];
        if (strpos($dirPath, '/home/pi') === 0) {
            $dirPath = substr($dirPath, 8);
        }
        $set['folder_path'] = $dirPath;
        $set['cover_image'] = $dirPath . '/' . $set['cover_image'];
        $imageSetPaths[] = $dirPath;
    }
    unset($set);
}

foreach ($iterator as $item) {
    if ($item->isDot()) continue;
    if ($item->isDir()) {
        $folderName = $item->getFilename();
        $folderPath = $path . '/' . $folderName;
        $fullFolderPath = $basePath . '/' . $folderName;
        
        if (!in_array($fullFolderPath, $imageSetPaths)) {
            $folders[] = [
                'name' => $folderName,
                'path' => $folderPath
            ];
        }
    }
}

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

foreach ($files as &$file) {
    if (!empty($file['thumb'])) {
        $file['thumb'] = NfoParser::addDisksPrefix($file['thumb'], $file['video_path']);
    }
}

usort($folders, fn($a, $b) => strcmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcmp($a['title'] ?? '', $b['title'] ?? ''));

echo json_encode([
    'folders' => $folders,
    'files' => $files,
    'image_sets' => $imageSets,
    'current_path' => $path,
    'can_go_back' => $canGoBack
]);