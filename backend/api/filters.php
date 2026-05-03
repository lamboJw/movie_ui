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

$config = require __DIR__ . '/../config/config.php';
$db = Database::getInstance()->getConnection();

$folder = $_GET['folder'] ?? '';

$where = [];
$params = [];

if (!empty($folder)) {
    $where[] = "m.video_path LIKE ?";
    $params[] = "%/" . $folder . "/%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 获取所有年份
$yearSql = "SELECT DISTINCT m.year FROM movies m $whereClause ORDER BY m.year DESC";
$stmt = $db->prepare($yearSql);
$stmt->execute($params);
$years = array_filter(array_column($stmt->fetchAll(), 'year'));

// 获取所有类型
$genreSql = "SELECT DISTINCT g.name 
             FROM genres g 
             JOIN movie_genres mg ON mg.genre_id = g.id 
             JOIN movies m ON m.id = mg.movie_id 
             $whereClause 
             ORDER BY g.name";
$stmt = $db->prepare($genreSql);
$stmt->execute($params);
$genres = array_filter(array_column($stmt->fetchAll(), 'name'));

// 获取所有导演
$directorWhere = $where;
$directorWhere[] = "m.director IS NOT NULL AND m.director != ''";
$directorWhereClause = 'WHERE ' . implode(' AND ', $directorWhere);
$directorSql = "SELECT DISTINCT m.director 
                FROM movies m 
                $directorWhereClause 
                ORDER BY m.director";
$stmt = $db->prepare($directorSql);
$stmt->execute($params);
$directors = array_filter(array_column($stmt->fetchAll(), 'director'));

// 获取所有演员（去重）
$actorSql = "SELECT DISTINCT a.name 
             FROM actors a 
             JOIN movie_actors ma ON ma.actor_id = a.id 
             JOIN movies m ON m.id = ma.movie_id 
             $whereClause 
             ORDER BY a.name";
$stmt = $db->prepare($actorSql);
$stmt->execute($params);
$actors = array_filter(array_column($stmt->fetchAll(), 'name'));

echo json_encode([
    'years' => array_values($years),
    'genres' => array_values($genres),
    'directors' => array_values($directors),
    'actors' => array_values($actors)
]);