<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NfoParser.php';

class VideoScanner {
    private $db;
    private $config;
    private $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct($config) {
        $this->db = Database::getInstance()->getConnection();
        $this->config = $config;
    }

    public function scanAll() {
        $results = [
            'videos' => [
                'scanned' => 0,
                'added' => 0,
                'updated' => 0,
                'errors' => []
            ],
            'image_sets' => [
                'scanned' => 0,
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
                'errors' => []
            ]
        ];

        $existingImageSetPaths = $this->getExistingImageSetPaths();
        $foundImageSetPaths = [];

        foreach ($this->config['video_folders'] as $folder) {
            try {
                $this->scanFolder($folder, $results, $foundImageSetPaths);
            } catch (Exception $e) {
                $results['videos']['errors'][] = "扫描文件夹失败: $folder - " . $e->getMessage();
            }
        }

        $toRemove = array_diff($existingImageSetPaths, $foundImageSetPaths);
        if (!empty($toRemove)) {
            $this->removeImageSets($toRemove, $results['image_sets']);
        }

        return $results;
    }

    private function getExistingImageSetPaths() {
        $stmt = $this->db->query("SELECT folder_path FROM image_sets");
        return array_column($stmt->fetchAll(), 'folder_path');
    }

    private function scanFolder($folder, &$results, &$foundImageSetPaths) {
        if (!is_dir($folder)) {
            return;
        }

        $videoExts = array_map('strtolower', $this->config['video_extensions']);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $filename = $item->getFilename();

            if ($item->isFile()) {
                $ext = strtolower($item->getExtension());
                if (in_array($ext, $videoExts)) {
                    $results['videos']['scanned']++;
                    $videoPath = $item->getPathname();
                    $nfoPath = $this->findNfoFileLocal($videoPath);
                    if (!$nfoPath) {
                        $nfoPath = $this->createNfoForVideo($videoPath);
                    }
                    if ($nfoPath) {
                        $this->processVideoLocal($videoPath, $nfoPath, $results);
                    }
                }
            } elseif ($item->isDir() && $filename !== '.' && $filename !== '..' && strpos($filename, '.') !== 0) {
                $dirPath = $item->getPathname();
                if ($this->isImageSet($dirPath)) {
                    $results['image_sets']['scanned']++;
                    $foundImageSetPaths[] = $dirPath;
                    $this->processImageSet($dirPath, $results);
                }
            }
        }
    }

    private function isImageSet($dirPath) {
        $files = scandir($dirPath);
        if (empty($files)) return false;

        $imageFiles = [];
        $hasVideo = false;
        $videoExts = array_map('strtolower', $this->config['video_extensions'] ?? ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm']);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (strpos($file, '.') === 0) continue;

            $fullPath = $dirPath . '/' . $file;
            if (is_dir($fullPath)) continue;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $this->imageExts)) {
                $imageFiles[] = $file;
            } elseif (in_array($ext, $videoExts)) {
                $hasVideo = true;
            }
        }

        return !empty($imageFiles) && !$hasVideo;
    }

    private function processImageSet($dirPath, &$results) {
        $images = $this->scanImages($dirPath);
        if (empty($images)) return;

        $title = basename($dirPath);
        $coverImage = $images[0];
        $parentPath = dirname($dirPath);
        $parentPath = NfoParser::convertRootPath($parentPath, $this->config['video_folders']);

        $stmt = $this->db->prepare("SELECT id FROM image_sets WHERE folder_path = ?");
        $stmt->execute([$dirPath]);
        $existing = $stmt->fetch();

        $imagesJson = json_encode(array_values($images));

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE image_sets SET title=?, cover_image=?, image_count=?, images=?, parent_path=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $coverImage, count($images), $imagesJson, $parentPath, $existing['id']]);
            $results['image_sets']['updated']++;
        } else {
            $stmt = $this->db->prepare("INSERT INTO image_sets (folder_path, title, cover_image, image_count, images, parent_path, date_added) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$dirPath, $title, $coverImage, count($images), $imagesJson, $parentPath]);
            $results['image_sets']['added']++;
        }
    }

    private function scanImages($dirPath) {
        $images = [];
        $files = scandir($dirPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (strpos($file, '.') === 0) continue;

            $fullPath = $dirPath . '/' . $file;
            if (is_dir($fullPath)) continue;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $this->imageExts)) {
                $images[] = $file;
            }
        }

        sort($images);
        return $images;
    }

    private function removeImageSets($paths, &$results) {
        foreach ($paths as $path) {
            $stmt = $this->db->prepare("DELETE FROM image_sets WHERE folder_path = ?");
            $stmt->execute([$path]);
            $results['removed']++;
        }
    }

    private function findNfoFileLocal($videoPath) {
        $dir = dirname($videoPath);
        $filename = pathinfo($videoPath, PATHINFO_FILENAME);

        // 判断是否在jav目录下
        $isJavDir = $this->isInJavDirectory($videoPath);

        if ($isJavDir) {
            // jav目录：搜索文件夹下所有的.nfo文件（包括隐藏文件）
            $nfoFiles = [];
            $iterator = new DirectoryIterator($dir);
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'nfo') {
                    $nfoFiles[] = $file->getPathname();
                }
            }

            if (!empty($nfoFiles)) {
                // 优先选择有thumb的nfo
                $thumbNfo = null;
                $earliestNfo = null;
                $earliestMtime = PHP_INT_MAX;

                foreach ($nfoFiles as $nfoFile) {
                    $movieData = NfoParser::parse($nfoFile);
                    if (!empty($movieData) && !empty($movieData['thumb'])) {
                        $thumbNfo = $nfoFile;
                        break;
                    }

                    // 记录修改时间最早的
                    $mtime = filemtime($nfoFile);
                    if ($mtime < $earliestMtime) {
                        $earliestMtime = $mtime;
                        $earliestNfo = $nfoFile;
                    }
                }

                return $thumbNfo ?? $earliestNfo;
            }
        } else {
            // 非jav目录：只检查同名.nfo
            $nfoFile = $dir . DIRECTORY_SEPARATOR . $filename . '.nfo';
            if (file_exists($nfoFile)) {
                return $nfoFile;
            }
        }

        return null;
    }

    /**
     * 判断视频是否在jav目录下
     */
    private function isInJavDirectory($videoPath) {
        foreach ($this->config['video_folders'] as $folder) {
            // 检查文件夹路径是否包含jav，并且视频路径以该文件夹开头
            if (strpos($folder, 'jav') !== false && strpos($videoPath, $folder) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 为没有nfo的视频自动创建最小化nfo文件
     */
    private function createNfoForVideo($videoPath) {
        $dir = dirname($videoPath);
        $filename = pathinfo($videoPath, PATHINFO_FILENAME);
        $nfoPath = $dir . DIRECTORY_SEPARATOR . $filename . '.nfo';

        $title = $this->cleanTitle($filename);

        // 寻找同目录下的图片作为thumb
        $thumb = '';
        $imageExts = $this->config['image_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($imageExts as $ext) {
            $candidates = [
                $dir . '/' . $filename . '.' . $ext,
                $dir . '/' . $filename . '-poster.' . $ext,
                $dir . '/' . 'poster.' . $ext,
                $dir . '/' . 'folder.' . $ext,
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) {
                    $thumb = basename($c);
                    break 2;
                }
            }
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><movie></movie>');
        $xml->addChild('title', htmlspecialchars($title, ENT_XML1, 'UTF-8'));
        $xml->addChild('originaltitle', htmlspecialchars($filename, ENT_XML1, 'UTF-8'));
        if ($thumb) {
            $xml->addChild('thumb', htmlspecialchars($thumb, ENT_XML1, 'UTF-8'));
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $xmlStr = $dom->saveXML();

        if (file_put_contents($nfoPath, $xmlStr) !== false) {
            return $nfoPath;
        }
        return null;
    }

    /**
     * 从文件名中清理出标题（去掉扩展名、年份、分辨率等常见杂质）
     */
    private function cleanTitle($filename) {
        // 去掉括号和方括号内的内容，如 (1080p), [WEB-DL] 等
        $title = preg_replace('/[\(\[][^\)\]]*[\)\]]/', '', $filename);
        // 去掉常见关键词
        $title = preg_replace('/\b(1080[pi]|720[pi]|2160[pi]|4K|WEB[-.]?DL|BluRay|BRRip|HDRip|DVDRip|x264|x265|HEVC|AAC|DD5\.1|HDTV|H264|H\.264)\b/i', '', $title);
        // 去掉多余空格和连接符
        $title = preg_replace('/[\._\s-]+/', ' ', $title);
        $title = trim($title);
        return $title ?: $filename;
    }

    /**
     * 处理本地视频
     */
    private function processVideoLocal($videoPath, $nfoPath, &$results) {
        $movieData = NfoParser::parse($nfoPath);
        if (!$movieData || empty($movieData['title'])) {
            return;
        }

        $filename = pathinfo($videoPath, PATHINFO_FILENAME);
        $videoDir = dirname($videoPath);
        $isMediaDir = strpos($videoDir, '/disk/.hidden/Media') !== false;

        // thumb 搜索优先级
        $thumbSearch = $isMediaDir ? [
            $filename . '-poster.jpg',
            'poster.jpg',
            $filename . '.jpg',
            $filename . '.png'
        ] : [$filename . '.png'];

        // fanart 搜索优先级
        $fanartSearch = $isMediaDir ? [
            $filename . '-poster.jpg',
            'poster.jpg',
            $filename . '.jpg',
            $filename . '.png'
        ] : [$filename . '.jpg'];

        // 搜索thumb
        if (empty($movieData['thumb'])) {
            $movieData['thumb'] = $this->findImage($videoDir, $thumbSearch) ?? $filename . '.png';
        }

        // 搜索fanart
        if (empty($movieData['fanart'])) {
            $movieData['fanart'] = $this->findImage($videoDir, $fanartSearch) ?? $filename . '.jpg';
        }

        $movieData['video_path'] = $videoPath;

        $this->saveMovie($movieData, $results);
    }

    /**
     * 在目录中搜索图片文件
     */
    private function findImage($dir, $candidates) {
        foreach ($candidates as $candidate) {
            $path = $dir . '/' . $candidate;
            if (file_exists($path)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * 保存电影信息到数据库
     */
    private function saveMovie($data, &$results) {
        // 检查是否已存在
        $stmt = $this->db->prepare("SELECT id FROM movies WHERE video_path = ?");
        $stmt->execute([$data['video_path']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $this->updateMovie($existing['id'], $data);
            $results['videos']['updated']++;
        } else {
            $this->insertMovie($data);
            $results['videos']['added']++;
        }
    }

    /**
     * 插入新电影
     */
    private function insertMovie($data) {
        $stmt = $this->db->prepare("
            INSERT INTO movies (title, original_title, year, plot, tagline, runtime, rating, votes, director, thumb, fanart, video_path, nfo_path, date_added)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['title'], $data['original_title'], $data['year'], $data['plot'],
            $data['tagline'], $data['runtime'], $data['rating'], $data['votes'],
            $data['director'], $data['thumb'], $data['fanart'],
            $data['video_path'], $data['nfo_path']
        ]);

        $movieId = $this->db->lastInsertId();
        $this->saveGenres($movieId, $data['genres']);
        $this->saveActors($movieId, $data['actors']);
    }

    /**
     * 更新电影信息
     */
    private function updateMovie($movieId, $data) {
        $stmt = $this->db->prepare("
            UPDATE movies SET title=?, original_title=?, year=?, plot=?, tagline=?, runtime=?, rating=?, votes=?, director=?, thumb=?, fanart=?, nfo_path=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $data['title'], $data['original_title'], $data['year'], $data['plot'],
            $data['tagline'], $data['runtime'], $data['rating'], $data['votes'],
            $data['director'], $data['thumb'], $data['fanart'], $data['nfo_path'], $movieId
        ]);

        // 删除旧的关联
        $this->db->prepare("DELETE FROM movie_genres WHERE movie_id = ?")->execute([$movieId]);
        $this->db->prepare("DELETE FROM movie_actors WHERE movie_id = ?")->execute([$movieId]);

        $this->saveGenres($movieId, $data['genres']);
        $this->saveActors($movieId, $data['actors']);
    }

    /**
     * 保存类型
     */
    private function saveGenres($movieId, $genres) {
        foreach ($genres as $genreName) {
            if (empty($genreName)) continue;

            $stmt = $this->db->prepare("INSERT IGNORE INTO genres (name) VALUES (?)");
            $stmt->execute([$genreName]);

            $stmt = $this->db->prepare("SELECT id FROM genres WHERE name = ?");
            $stmt->execute([$genreName]);
            $genre = $stmt->fetch();

            if ($genre) {
                $stmt = $this->db->prepare("INSERT IGNORE INTO movie_genres (movie_id, genre_id) VALUES (?, ?)");
                $stmt->execute([$movieId, $genre['id']]);
            }
        }
    }

    /**
     * 保存演员
     */
    private function saveActors($movieId, $actors) {
        foreach ($actors as $actorData) {
            if (empty($actorData['name'])) continue;

            $stmt = $this->db->prepare("INSERT IGNORE INTO actors (name) VALUES (?)");
            $stmt->execute([$actorData['name']]);

            $stmt = $this->db->prepare("SELECT id FROM actors WHERE name = ?");
            $stmt->execute([$actorData['name']]);
            $actor = $stmt->fetch();

            if ($actor) {
                $stmt = $this->db->prepare("INSERT IGNORE INTO movie_actors (movie_id, actor_id, role) VALUES (?, ?, ?)");
                $stmt->execute([$movieId, $actor['id'], $actorData['role']]);
            }
        }
    }
}
