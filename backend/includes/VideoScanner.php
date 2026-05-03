<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NfoParser.php';

class VideoScanner {
    private $db;
    private $config;

    public function __construct($config) {
        $this->db = Database::getInstance()->getConnection();
        $this->config = $config;
    }

    /**
     * 扫描所有视频文件夹（本地模式）
     */
    public function scanAll() {
        $results = [
            'scanned' => 0,
            'added' => 0,
            'updated' => 0,
            'errors' => []
        ];

        foreach ($this->config['video_folders'] as $folder) {
            try {
                $this->scanFolderLocal($folder, $results);
            } catch (Exception $e) {
                $results['errors'][] = "扫描文件夹失败: $folder - " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 本地文件系统扫描
     */
    private function scanFolderLocal($folder, &$results) {
        if (!is_dir($folder)) {
            $results['errors'][] = "文件夹不存在: $folder";
            return;
        }

        $videoExts = array_map('strtolower', $this->config['video_extensions']);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $videoExts)) {
                    $results['scanned']++;
                    $videoPath = $file->getPathname();
                    $nfoPath = $this->findNfoFileLocal($videoPath);

                    if ($nfoPath) {
                        $this->processVideoLocal($videoPath, $nfoPath, $results);
                    }
                }
            }
        }
    }

    /**
     * 本地查找NFO文件（优先选择有thumb的）
     */
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
     * 处理本地视频
     */
    private function processVideoLocal($videoPath, $nfoPath, &$results) {
        $movieData = NfoParser::parse($nfoPath);
        if (!$movieData || empty($movieData['title'])) {
            return;
        }

        $filename = pathinfo($videoPath, PATHINFO_FILENAME);
        $videoDir = dirname($videoPath);

        // 如果没有thumb，搜索图片
        if (empty($movieData['thumb'])) {
            // 检查是否在 /disk1/.hidden/Media 目录
            if (strpos($videoDir, '/disk1/.hidden/Media') !== false) {
                // 搜索 {文件名}-poster.jpg
                $thumbCandidate = $videoDir . '/' . $filename . '-poster.jpg';
                if (!file_exists($thumbCandidate)) {
                    // 再搜索 poster.jpg
                    $thumbCandidate = $videoDir . '/poster.jpg';
                }
                if (file_exists($thumbCandidate)) {
                    $movieData['thumb'] = $thumbCandidate;
                }
            }

            // 如果还没找到，使用视频文件名+.png
            if (empty($movieData['thumb'])) {
                $movieData['thumb'] = $filename . '.png';
            }
        }

        // 如果没有fanart，搜索图片
        if (empty($movieData['fanart'])) {
            // 检查是否在 /disk1/.hidden/Media 目录
            if (strpos($videoDir, '/disk1/.hidden/Media') !== false) {
                // 搜索 {文件名}-poster.jpg
                $fanartCandidate = $videoDir . '/' . $filename . '-poster.jpg';
                if (!file_exists($fanartCandidate)) {
                    // 再搜索 poster.jpg
                    $fanartCandidate = $videoDir . '/poster.jpg';
                }
                if (file_exists($fanartCandidate)) {
                    $movieData['fanart'] = $fanartCandidate;
                }
            }

            // 如果还没找到，使用视频文件名+.jpg
            if (empty($movieData['fanart'])) {
                $movieData['fanart'] = $filename . '.jpg';
            }
        }

        $movieData['video_path'] = $videoPath;

        $this->saveMovie($movieData, $results);
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
            $results['updated']++;
        } else {
            $this->insertMovie($data);
            $results['added']++;
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
