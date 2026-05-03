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

// 随机获取一部电影
$stmt = $db->prepare("
    SELECT m.id, m.title, m.original_title, m.year, m.rating, m.thumb, m.plot, m.runtime, m.director, m.date_added,
           GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genres
    FROM movies m
    LEFT JOIN movie_genres mg ON mg.movie_id = m.id
    LEFT JOIN genres g ON g.id = mg.genre_id
    ORDER BY RAND()
    LIMIT 1
");
$stmt->execute();
$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    echo json_encode(['error' => '没有找到电影']);
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
$stmt->execute([$movie['id']]);
$movie['actors'] = $stmt->fetchAll();

$movie['genres'] = explode(', ', $movie['genres'] ?? '');

// 补充/disks/前缀
$movie['thumb'] = NfoParser::addDisksPrefix($movie['thumb']);

echo json_encode($movie);
