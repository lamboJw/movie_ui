<?php
require_once __DIR__ . '/SshConnection.php';

class NfoParser {
    /**
     * 从本地文件系统解析NFO
     */
    public static function parse($nfoPath) {
        if (!file_exists($nfoPath)) {
            return null;
        }

        $xmlContent = file_get_contents($nfoPath);
        return self::parseXmlContent($xmlContent, $nfoPath);
    }

    /**
     * 解析XML内容（公共方法）
     */
    private static function parseXmlContent($xmlContent, $nfoPath) {
        // 检测并转换编码
        $xmlContent = self::convertEncoding($xmlContent);

        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return null;
        }

        $movie = [];

        // 基本字段
        $movie['title'] = (string)($xml->title ?? '');
        $movie['original_title'] = (string)($xml->originaltitle ?? '');
        $movie['year'] = !empty($xml->year) ? (int)$xml->year : null;
        $movie['plot'] = (string)($xml->plot ?? '');
        $movie['tagline'] = (string)($xml->tagline ?? '');
        $movie['runtime'] = !empty($xml->runtime) ? (int)$xml->runtime : null;
        $movie['rating'] = !empty($xml->rating) ? (float)$xml->rating : null;
        $movie['votes'] = !empty($xml->votes) ? (int)$xml->votes : null;
        $movie['director'] = (string)($xml->director ?? '');
        $movie['thumb'] = (string)($xml->thumb ?? '');
        $movie['fanart'] = (string)($xml->fanart ?? '');

        // 类型
        $movie['genres'] = [];
        if (isset($xml->genre)) {
            foreach ($xml->genre as $genre) {
                $movie['genres'][] = (string)$genre;
            }
        }

        // 演员
        $movie['actors'] = [];
        if (isset($xml->actor)) {
            foreach ($xml->actor as $actor) {
                $movie['actors'][] = [
                    'name' => (string)($actor->name ?? ''),
                    'role' => (string)($actor->role ?? '')
                ];
            }
        }

        $movie['nfo_path'] = $nfoPath;

        return $movie;
    }

    /**
     * 检测并转换编码为UTF-8
     */
    private static function convertEncoding($content) {
        // 尝试检测BOM并移除
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        } elseif (substr($content, 0, 2) === "\xFF\xFE") {
            $content = substr($content, 2);
        }

        // 如果已经是有效UTF-8，直接返回
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        // 尝试Big5转UTF-8
        if (mb_check_encoding($content, 'BIG5')) {
            return mb_convert_encoding($content, 'UTF-8', 'BIG5');
        }

        // 尝试GB2312/GBK转UTF-8
        if (mb_check_encoding($content, 'GB2312')) {
            return mb_convert_encoding($content, 'UTF-8', 'GB2312');
        }

        if (mb_check_encoding($content, 'GBK')) {
            return mb_convert_encoding($content, 'UTF-8', 'GBK');
        }

        // 尝试日文编码
        if (mb_check_encoding($content, 'SJIS')) {
            return mb_convert_encoding($content, 'UTF-8', 'SJIS');
        }

        return $content;
    }

    /**
     * 转换thumb路径为可访问的URL（使用nginx）
     */
    public static function convertThumbUrl($thumb, $nfoPath, $config) {
        // 如果已经是URL，直接返回
        if (filter_var($thumb, FILTER_VALIDATE_URL)) {
            return $thumb;
        }

        // 如果thumb为空，尝试查找本地图片
        if (empty($thumb)) {
            return self::findLocalThumb($nfoPath, $config);
        }

        // 使用nginx服务图片，需要将本地路径转为HTTP URL
        $imageConfig = $config['image'] ?? [];
        if (!empty($imageConfig['enabled'])) {
            // 使用路径映射（推荐）
            if (!empty($imageConfig['path_mapping']) && is_array($imageConfig['path_mapping'])) {
                foreach ($imageConfig['path_mapping'] as $localPrefix => $urlPrefix) {
                    if (strpos($thumb, $localPrefix) === 0) {
                        $relativePath = substr($thumb, strlen($localPrefix));
                        return rtrim($urlPrefix, '/') . '/' . ltrim($relativePath, '/');
                    }
                }
            }
            
            // 备用方式：仅使用url_prefix
            if (!empty($imageConfig['url_prefix'])) {
                $videoFolders = $config['video_folders'] ?? [];
                foreach ($videoFolders as $folder) {
                    if (strpos($thumb, $folder) === 0) {
                        $relativePath = substr($thumb, strlen($folder));
                        return rtrim($imageConfig['url_prefix'], '/') . '/' . ltrim($relativePath, '/');
                    }
                }
            }
        }

        return $thumb;
    }

    /**
     * 去掉thumb中的/disks/前缀（用于存储到数据库）
     */
    public static function removeDisksPrefix($thumb) {
        // 如果是完整URL，不处理
        if (filter_var($thumb, FILTER_VALIDATE_URL)) {
            return $thumb;
        }
        
        // 去掉/disks/前缀
        $prefix = '/disks/';
        if (strpos($thumb, $prefix) === 0) {
            return substr($thumb, strlen($prefix));
        }
        
        return $thumb;
    }

    /**
     * 补充/disks/前缀（用于返回给前端）
     * @param string $thumb 缩略图路径
     * @param string|null $videoPath 视频文件路径，用于确定文件夹前缀
     */
    public static function addDisksPrefix($thumb, $videoPath = null) {
        if (empty($thumb)) return '';
        if (filter_var($thumb, FILTER_VALIDATE_URL)) {
            return $thumb;
        }
        if (strpos($thumb, '/home/pi') === 0) {
            return substr($thumb, 8);
        }
        if (strpos($thumb, '/disks/') === 0) {
            return $thumb;
        }
        if (!empty($videoPath)) {
            $videoDir = dirname($videoPath);
            if (strpos($videoDir, '/home/pi') === 0) {
                $videoDir = substr($videoDir, 8);
            }
            return $videoDir . '/' . ltrim($thumb, '/');
        }
        return $thumb;
    }

    /**
     * 查找本地封面图片
     */
    private static function findLocalThumb($nfoPath, $config) {
        if (empty($nfoPath)) return null;

        $dir = dirname($nfoPath);
        $filename = pathinfo($nfoPath, PATHINFO_FILENAME);
        $imageExts = $config['image_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];

        foreach ($imageExts as $ext) {
            $imagePath = $dir . '/' . $filename . '.' . $ext;
            if (file_exists($imagePath)) {
                return self::convertThumbUrl($imagePath, $nfoPath, $config);
            }
        }

        return null;
    }
}
