<?php
class SshConnection {
    private $config;
    private static $instance = null;
    private $sshPassAvailable = null;

    private function __construct($config) {
        $this->config = $config;
    }

    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                $config = require __DIR__ . '/../config/config.php';
            }
            self::$instance = new self($config['ssh']);
        }
        return self::$instance;
    }

    /**
     * 检查sshpass是否可用
     */
    private function hasSshPass() {
        if ($this->sshPassAvailable === null) {
            exec('which sshpass 2>/dev/null', $output, $returnCode);
            $this->sshPassAvailable = ($returnCode === 0);
        }
        return $this->sshPassAvailable;
    }

    /**
     * 构建SSH连接字符串
     */
    private function getSshOptions() {
        $ssh = $this->config;
        $port = $ssh['port'] ?? 22;
        return "-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p $port";
    }

    /**
     * 构建SSH命令前缀（带认证）
     */
    private function getSshCommandPrefix() {
        $ssh = $this->config;
        $host = $ssh['host'];
        $user = $ssh['username'];
        $options = $this->getSshOptions();

        if ($this->hasSshPass()) {
            // 使用sshpass
            return "sshpass -p " . escapeshellarg($ssh['password']) . " ssh $options $user@$host";
        } else {
            // 尝试使用SSH密钥或交互式（需要password配置）
            return "ssh $options $user@$host";
        }
    }

    /**
     * 执行远程SSH命令
     */
    public function exec($command) {
        $sshCmd = $this->getSshCommandPrefix() . ' ' . escapeshellarg($command);
        exec($sshCmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("SSH命令执行失败 (code $returnCode): " . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * 列出目录中的文件
     */
    public function listFiles($path) {
        // 使用ls命令列出文件，排除.和..
        $command = "ls -A1 " . escapeshellarg($path) . " 2>/dev/null";
        $output = $this->exec($command);

        if (empty($output)) {
            return [];
        }

        return explode("\n", trim($output));
    }

    /**
     * 递归扫描目录，返回所有视频文件及其NFO路径
     */
    public function scanDirectory($basePath, $videoExts) {
        $results = [];
        $this->recursiveScan($basePath, '', $videoExts, $results);
        return $results;
    }

    /**
     * 递归扫描辅助函数
     */
    private function recursiveScan($basePath, $currentPath, $videoExts, &$results) {
        $fullPath = $basePath . ($currentPath ? '/' . $currentPath : '');
        $fullPath = rtrim($fullPath, '/');

        try {
            $items = $this->listFiles($fullPath);
        } catch (Exception $e) {
            return; // 跳过无法访问的目录
        }

        foreach ($items as $item) {
            if (empty($item)) continue;
            if ($item === '.' || $item === '..') continue;

            $itemPath = $currentPath ? $currentPath . '/' . $item : $item;
            $fullItemPath = $basePath . '/' . $itemPath;

            try {
                // 检查是否是目录
                if ($this->isDir($fullItemPath)) {
                    $this->recursiveScan($basePath, $itemPath, $videoExts, $results);
                } else {
                    // 检查是否是视频文件
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($ext, $videoExts)) {
                        $results[] = [
                            'video_path' => $fullItemPath,
                            'nfo_path' => $this->findNfoFile($fullItemPath)
                        ];
                    }
                }
            } catch (Exception $e) {
                // 忽略单个文件错误
            }
        }
    }

    /**
     * 检查路径是否是目录
     */
    private function isDir($path) {
        try {
            $command = "test -d " . escapeshellarg($path) . " && echo 'YES' || echo 'NO'";
            $result = $this->exec($command);
            return trim($result) === 'YES';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 查找对应的NFO文件
     */
    private function findNfoFile($videoPath) {
        $dir = dirname($videoPath);
        $filename = pathinfo($videoPath, PATHINFO_FILENAME);

        // 尝试相同文件名的nfo
        $nfoPath = $dir . '/' . $filename . '.nfo';
        if ($this->fileExists($nfoPath)) {
            return $nfoPath;
        }

        // 尝试movie.nfo
        $nfoPath = $dir . '/movie.nfo';
        if ($this->fileExists($nfoPath)) {
            return $nfoPath;
        }

        return null;
    }

    /**
     * 检查文件是否存在
     */
    public function fileExists($path) {
        try {
            $command = "test -e " . escapeshellarg($path) . " && echo 'YES' || echo 'NO'";
            $result = $this->exec($command);
            return trim($result) === 'YES';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 读取文件内容
     */
    public function readFile($path) {
        $command = "cat " . escapeshellarg($path);
        return $this->exec($command);
    }

    /**
     * 获取SSH配置（供外部使用）
     */
    public function getConfig() {
        return $this->config;
    }
}
