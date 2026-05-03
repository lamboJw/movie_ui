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
     */
    public static function addDisksPrefix($thumb) {
        // 如果是完整URL，不处理
        if (filter_var($thumb, FILTER_VALIDATE_URL)) {
            return $thumb;
        }
        
        // 如果已经有/disks/前缀，不重复添加
        if (strpos($thumb, '/disks/') === 0) {
            return $thumb;
        }
        
        // 补充/disks/前缀
        if (!empty($thumb)) {
            return '/disks/' . ltrim($thumb, '/');
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
