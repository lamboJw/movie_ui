# 电影库管理系统

基于 PHP 8.1 + Vue 3 + MySQL 的视频和套图管理系统，部署于树莓派。支持目录浏览、视频播放（零拷贝流式传输）、图片套图浏览、NFO 元数据解析。

## 项目结构

```
movie_ui/
├── backend/                      # PHP 8.1 后端
│   ├── config/
│   │   └── config.php           # 配置文件（数据库、视频路径）
│   ├── includes/
│   │   ├── Database.php         # 数据库连接
│   │   ├── NfoParser.php        # Kodi NFO 解析器
│   │   └── VideoScanner.php     # 视频扫描器
│   ├── api/
│   │   ├── browse.php           # 目录浏览 API
│   │   ├── movies.php           # 电影列表/搜索/筛选 API
│   │   ├── movie.php            # 电影详情 API
│   │   ├── random.php           # 随机视频 API
│   │   ├── scan.php             # 触发扫描 API
│   │   ├── stream.php           # 视频流（支持 X-Accel-Redirect 零拷贝）
│   │   ├── image_sets.php       # 套图列表 API
│   │   └── image_set.php        # 套图详情 API
│   └── index.php                # 入口文件（路由）
├── frontend/                     # Vue 3 + Vite 前端
│   ├── src/
│   │   ├── views/
│   │   │   ├── MovieList.vue    # 首页（文件夹浏览/列表模式）
│   │   │   ├── MovieDetail.vue  # 视频详情 + 内联播放器
│   │   │   └── ImageSetDetail.vue # 套图详情（轮播/瀑布流）
│   │   ├── components/
│   │   │   ├── MovieCard.vue    # 视频卡片
│   │   │   ├── ImageSetCard.vue # 套图卡片
│   │   │   ├── VideoPlayer.vue  # 自建视频播放器
│   │   │   └── FilterBar.vue    # 筛选组件
│   │   ├── api/
│   │   │   ├── movieApi.js      # 视频 API
│   │   │   └── imageSetApi.js   # 套图 API
│   │   └── router/
│   │       └── index.js         # 路由配置
│   └── package.json
├── database/
│   └── schema.sql               # 数据库表结构
├── nginx/
│   └── movie_ui.conf            # Nginx 配置参考
└── public/                      # 前端构建产物（gitignore）
```

## 环境要求

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.2+
- Node.js 16+
- Nginx（生产环境，需配置 X-Accel-Redirect 零拷贝）

## 快速开始

### 1. 创建数据库

```bash
mysql -u root -p < database/schema.sql
```

### 2. 配置后端

编辑 `backend/config/config.php`：

```php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'movie_db',
        'username' => 'root',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'scan_mode' => 'local',
    'video_folders' => [
        '/path/to/videos',
    ],
    'image' => [
        'enabled' => true,
        'url_prefix' => 'http://your-server',
        'path_mapping' => [
            '/path/to/disks' => '/disks'
        ]
    ],
    'video_extensions' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v', 'webm'],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'api_base' => '/api'
];
```

### 3. 扫描视频

```bash
# 通过 API
curl http://localhost:8080/api/scan

# 或 CLI 脚本
php backend/api/scan_cli.php
```

### 4. 启动开发服务器

```bash
# 启动 PHP 内置服务器（同时服务 API 和前端）
cd backend && php -S localhost:8080

# 或启动 Vite 开发服务器（前端热更新）
cd frontend && npm run dev
```

### 5. 生产部署（Nginx）

参考 `nginx/movie_ui.conf` 配置 Nginx，关键特性：

- **X-Accel-Redirect 零拷贝**：PHP 只做权限校验和路径解析，视频文件由 Nginx 内核态直接发送，大幅降低树莓派 CPU 负载
- `/disks/` 别名：直接通过 Nginx 提供封面图片和静态资源

```bash
sudo cp nginx/movie_ui.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/movie_ui.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo nginx -s reload
```

构建前端：

```bash
cd frontend && npm run build
```

## 功能特性

- ✅ 目录浏览（文件夹模式/列表模式）
- ✅ 面包屑导航（含根目录快速返回）
- ✅ 视频播放（自建播放器，支持倍速、手势、全屏自动旋转）
- ✅ 零拷贝流式传输（X-Accel-Redirect）
- ✅ 图片套图浏览（轮播 + 瀑布流模式）
- ✅ 轮播图支持跟手拖拽
- ✅ NFO 文件解析（Kodi 格式）
- ✅ 多条件筛选：名称、年份、类型、导演、演员、评分
- ✅ 分页浏览
- ✅ 随机推荐
- ✅ 响应式移动端适配

## API 接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/browse?path=` | GET | 目录浏览 |
| `/api/movies` | GET | 电影列表（支持分页和筛选） |
| `/api/movie?id=` | GET | 电影详情 |
| `/api/random` | GET | 随机推荐 |
| `/api/scan` | GET | 触发扫描 |
| `/api/stream?id=` | GET | 视频流（支持 HTTP Range） |
| `/api/image_sets?path=` | GET | 套图列表 |
| `/api/image_set?id=` | GET | 套图详情 |
