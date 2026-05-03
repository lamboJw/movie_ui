<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/NfoParser.php';

$config = require __DIR__ . '/../config/config.php';
$db = Database::getInstance()->getConnection();

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => '缺少电影ID']);
    exit;
}

// 查询电影详情
$stmt = $db->prepare("
    SELECT m.*,
           GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genre_names
    FROM movies m
    LEFT JOIN movie_genres mg ON mg.movie_id = m.id
    LEFT JOIN genres g ON g.id = mg.genre_id
    WHERE m.id = ?
    GROUP BY m.id
");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    echo json_encode(['error' => '电影不存在']);
    exit;
}

// 查询演员
$stmt = $db->prepare("
    SELECT a.name, ma.role
    FROM movie_actors ma
    JOIN actors a ON a.id = ma.actor_id
    WHERE ma.movie_id = ?
    ORDER BY ma.role
");
$stmt->execute([$id]);
$movie['actors'] = $stmt->fetchAll();

// 查询类型列表
$stmt = $db->prepare("
    SELECT g.name
    FROM movie_genres mg
    JOIN genres g ON g.id = mg.genre_id
    WHERE mg.movie_id = ?
");
$stmt->execute([$id]);
$movie['genres'] = array_column($stmt->fetchAll(), 'name');

// 补充/disks/前缀
$movie['thumb'] = NfoParser::addDisksPrefix($movie['thumb'], $movie['video_path'] ?? null);
// 转换 video_path
if (strpos($movie['video_path'] ?? '', '/home/pi') === 0) {
    $movie['video_path'] = substr($movie['video_path'], 8);
}
// 提取文件夹层级
$movie['folder'] = extractFolder($movie['video_path'] ?? '', $config['video_folders'] ?? []);

echo json_encode($movie);

// 提取文件夹层级的函数
function extractFolder($videoPath, $videoFolders) {
    foreach ($videoFolders as $folder) {
        if (strpos($videoPath, $folder) === 0) {
            $relativePath = substr($videoPath, strlen($folder));
            $parts = array_filter(explode('/', $relativePath));
            return implode('/', array_slice($parts, 0, -1));
        }
    }
    return '';
}
