<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/NfoParser.php';

$db = Database::getInstance()->getConnection();
$config = require __DIR__ . '/../config/config.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id parameter']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM image_sets WHERE id = ?");
$stmt->execute([$id]);
$imageSet = $stmt->fetch();

if (!$imageSet) {
    http_response_code(404);
    echo json_encode(['error' => 'Image set not found']);
    exit;
}

$folderPath = $imageSet['folder_path'];
$images = json_decode($imageSet['images'], true) ?: [];

$processedImages = [];
foreach ($images as $image) {
    $fullPath = $folderPath . '/' . $image;
    $processedImages[] = $fullPath;
}

$imageSet['images'] = $processedImages;
$imageSet['image_count'] = count($processedImages);

if (strpos($imageSet['folder_path'], '/home/pi') === 0) {
    $imageSet['folder_path'] = substr($imageSet['folder_path'], 8);
}

$coverImage = $imageSet['cover_image'];
$coverPath = $imageSet['folder_path'] . '/' . $coverImage;
$imageSet['cover_image'] = NfoParser::addDisksPrefix($coverPath, $imageSet['folder_path']);

$imageSet['parent_path'] = convertVideoPath($imageSet['parent_path'], $config['video_folders'] ?? []);

echo json_encode($imageSet);

function convertVideoPath($videoPath, $videoFolders) {
    if (empty($videoPath)) return '';

    foreach ($videoFolders as $folder) {
        if (strpos($videoPath, $folder) === 0) {
            $relativePath = substr($videoPath, strlen($folder));
            $parts = array_filter(explode('/', $relativePath));
            return implode('/', $parts);
        }
    }
    return ltrim($videoPath, '/');
}