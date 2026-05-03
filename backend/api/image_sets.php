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

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$folder = $_GET['folder'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "title LIKE ?";
    $params[] = "%$search%";
}

if (!empty($folder)) {
    $where[] = "parent_path LIKE ?";
    $params[] = "%/" . $folder . "/%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) as total FROM image_sets $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

$sql = "SELECT id, title, cover_image, image_count, folder_path, parent_path, date_added
        FROM image_sets
        $whereClause
        ORDER BY date_added DESC
        LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$execParams = array_merge($params, [$limit, $offset]);
$stmt->execute($execParams);
$imageSets = $stmt->fetchAll();

foreach ($imageSets as &$set) {
    $dirPath = $set['folder_path'];
    $coverImage = $set['cover_image'];
    $coverPath = $dirPath . '/' . $coverImage;
    $set['cover_image'] = NfoParser::addDisksPrefix($coverPath, $dirPath);
    
    if (strpos($set['folder_path'], '/home/pi') === 0) {
        $set['folder_path'] = substr($set['folder_path'], 8);
    }
    
    $set['parent_path'] = convertVideoPath($set['parent_path'], $config['video_folders'] ?? []);
}

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

echo json_encode([
    'image_sets' => $imageSets,
    'total' => (int)$total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($total / $limit)
]);