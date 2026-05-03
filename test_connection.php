<?php
// test_connection.php
$host = '192.168.31.59';
$port = 3306;

// 1. 测试基础网络连接
$fp = @fsockopen($host, $port, $errno, $errstr, 5);
if (!$fp) {
    echo "fsockopen 失败: $errstr ($errno)\n";
} else {
    echo "fsockopen 成功: 端口 $port 是开放的\n";
    fclose($fp);
}

// 2. 测试DNS解析（虽然用的是IP）
$ip = gethostbyname($host);
echo "解析结果: $ip\n";

// 3. 测试MySQL连接
try {
    $mysqli = new mysqli($host, 'root', 'jiawei1994');
    if ($mysqli->connect_error) {
        echo "MySQLi连接失败: " . $mysqli->connect_error . "\n";
    } else {
        echo "MySQLi连接成功！\n";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "异常: " . $e->getMessage() . "\n";
}
?>
