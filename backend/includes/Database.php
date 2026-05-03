<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $config = $GLOBALS['config'] ?? require __DIR__ . '/../config/config.php';
        $db = $config['db'];

        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";

        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '数据库连接失败: ' . $e->getMessage()]);
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
