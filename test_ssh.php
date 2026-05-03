<?php
/**
 * SSH连接测试脚本（使用SSH命令模式）
 * 使用方法: php test_ssh.php
 */

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/includes/SshConnection.php';

$config = require __DIR__ . '/backend/config/config.php';

echo "=== SSH连接测试（命令模式）===\n\n";

if (($config['scan_mode'] ?? 'local') !== 'ssh') {
    echo "⚠️  当前扫描模式不是SSH模式\n";
    echo "请在 config.php 中设置 'scan_mode' => 'ssh'\n\n";
}

$sshConfig = $config['ssh'] ?? [];

if (empty($sshConfig['host'])) {
    echo "❌ SSH配置缺失，请检查 config.php\n";
    exit(1);
}

echo "配置信息:\n";
echo "  主机: {$sshConfig['host']}\n";
echo "  端口: " . ($sshConfig['port'] ?? 22) . "\n";
echo "  用户: {$sshConfig['username']}\n\n";

try {
    echo "正在连接...";
    $ssh = SshConnection::getInstance($config);
    echo " ✅ 连接成功!\n\n";

    // 测试执行简单命令
    echo "测试执行命令 (ls)...";
    $result = $ssh->exec("echo 'test'");
    if (trim($result) === 'test') {
        echo " ✅ 命令执行成功!\n\n";
    }

    // 测试列出目录
    echo "测试访问视频文件夹:\n";
    foreach ($config['video_folders'] as $folder) {
        echo "  检查: $folder ... ";
        if ($ssh->fileExists($folder)) {
            echo "✅ 存在\n";

            // 尝试列出文件
            try {
                $files = $ssh->listFiles($folder);
                echo "    文件数: " . count($files) . "\n";
            } catch (Exception $e) {
                echo "    ⚠️  无法列出: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ 不存在\n";
        }
    }

    echo "\n✅ 所有测试通过！可以开始扫描了。\n";
    echo "访问: http://localhost:8080/api/scan\n";

} catch (Exception $e) {
    echo "\n❌ 连接失败: " . $e->getMessage() . "\n\n";
    echo "请检查:\n";
    echo "  1. 树莓派是否开机\n";
    echo "  2. SSH服务是否启动 (systemctl status ssh)\n";
    echo "  3. IP地址、用户名、密码是否正确\n";
    echo "  4. 是否安装了sshpass（用于密码认证）\n";
    echo "      macOS: brew install sshpass\n";
    echo "      Ubuntu: sudo apt-get install sshpass\n";
    exit(1);
}
