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

$config = require __DIR__ . '/../config/config.php';
$db = Database::getInstance()->getConnection();

// 获取查询参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$year = $_GET['year'] ?? '';
$genre = trim($_GET['genre'] ?? '');
$director = trim($_GET['director'] ?? '');
$actor = trim($_GET['actor'] ?? '');
$minRating = $_GET['min_rating'] ?? '';
$maxRating = $_GET['max_rating'] ?? '';
$folder = $_GET['folder'] ?? '';

// 构建查询
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(m.title LIKE ? OR m.original_title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($year)) {
    $where[] = "m.year = ?";
    $params[] = $year;
}

if (!empty($genre)) {
    $where[] = "EXISTS (SELECT 1 FROM movie_genres mg JOIN genres g ON g.id = mg.genre_id WHERE mg.movie_id = m.id AND g.name LIKE ?)";
    $params[] = "%$genre%";
}

if (!empty($director)) {
    $where[] = "m.director LIKE ?";
    $params[] = "%$director%";
}

if (!empty($actor)) {
    $where[] = "EXISTS (SELECT 1 FROM movie_actors ma JOIN actors a ON a.id = ma.actor_id WHERE ma.movie_id = m.id AND a.name LIKE ?)";
    $params[] = "%$actor%";
}

if (!empty($minRating)) {
    $where[] = "m.rating >= ?";
    $params[] = $minRating;
}

if (!empty($maxRating)) {
    $where[] = "m.rating <= ?";
    $params[] = $maxRating;
}

if (!empty($folder)) {
    $where[] = "m.video_path LIKE ?";
    $params[] = "%/" . $folder . "/%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 查询总数
$countSql = "SELECT COUNT(DISTINCT m.id) as total FROM movies m $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// 查询电影列表
$sql = "SELECT m.id, m.title, m.original_title, m.year, m.rating, m.thumb, m.video_path, m.date_added,
        GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genres
        FROM movies m
        LEFT JOIN movie_genres mg ON mg.movie_id = m.id
        LEFT JOIN genres g ON g.id = mg.genre_id
        $whereClause
        GROUP BY m.id
        ORDER BY m.date_added DESC
        LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$execParams = array_merge($params, [$limit, $offset]);
$stmt->execute($execParams);
$movies = $stmt->fetchAll();

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

// 处理日期格式和thumb前缀和文件夹
foreach ($movies as &$movie) {
    $movie['date_added'] = date('Y-m-d H:i', strtotime($movie['date_added']));
    // 补充/disks/前缀
    $movie['thumb'] = NfoParser::addDisksPrefix($movie['thumb'], $movie['video_path'] ?? null);
    // 转换 video_path
    if (strpos($movie['video_path'] ?? '', '/home/pi') === 0) {
        $movie['video_path'] = '/disks' . substr($movie['video_path'], 8);
    }
    // 提取文件夹层级
    $movie['folder'] = extractFolder($movie['video_path'] ?? '', $config['video_folders'] ?? []);
}

// 返回结果
echo json_encode([
    'movies' => $movies,
    'total' => (int)$total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($total / $limit)
]);
