# 电影库管理系统

基于 PHP 8.1 + Vue 3 + MySQL 的视频管理系统，支持NFO文件解析、多条件筛选和随机推荐功能。支持本地文件系统或SSH远程访问视频文件（如树莓派）。

**SSH模式说明**：使用系统SSH命令（非phpseclib库），通过sshpass实现密码认证。

## 项目结构

```
movie_ui/
├── backend/                    # PHP 8.1 后端
│   ├── config/
│   │   └── config.php         # 配置文件(数据库、视频路径、SSH)
│   ├── includes/
│   │   ├── Database.php       # 数据库连接
│   │   ├── NfoParser.php      # Kodi NFO解析器(支持本地/SSH)
│   │   ├── SshConnection.php  # SSH连接管理(使用SSH命令)
│   │   └── VideoScanner.php   # 视频扫描器(支持本地/SSH)
│   ├── api/
│   │   ├── movies.php         # 电影列表/搜索API
│   │   ├── movie.php          # 电影详情API
│   │   ├── random.php         # 随机视频API
│   │   └── scan.php           # 触发扫描API
│   └── index.php              # 入口文件
├── frontend/                   # Vue 3 前端
│   ├── src/
│   │   ├── views/
│   │   │   ├── MovieList.vue  # 视频列表页
│   │   │   └── MovieDetail.vue# 视频详情页
│   │   ├── components/
│   │   │   ├── MovieCard.vue  # 视频卡片组件
│   │   │   └── FilterBar.vue  # 筛选组件
│   │   ├── api/
│   │   │   └── movieApi.js    # API调用
│   │   └── router/
│   │       └── index.js       # 路由配置
│   └── package.json
├── database/
│   └── schema.sql             # 数据库表结构
├── vendor/                    # PHP依赖(Composer)
├── test_ssh.php               # SSH连接测试脚本
└── public/                    # Vue打包后的文件(PHP服务)
```

## 环境要求

- PHP 8.1+
- MySQL 5.7+ 或 MariaDB 10.2+
- Node.js 16+ (前端开发)
- Composer (PHP依赖管理，可选)
- sshpass (用于SSH密码认证)
  - macOS: `brew install sshpass`
  - Ubuntu/Debian: `sudo apt-get install sshpass`
- 树莓派或其他远程服务器(可选，用于SSH访问)

## 安装步骤

### 1. 安装sshpass（用于SSH密码认证）

```bash
# macOS
brew install sshpass

# Ubuntu/Debian
sudo apt-get install sshpass

# CentOS/RHEL
sudo yum install sshpass
```

### 2. （可选）安装PHP依赖

如果需要在本地运行PHP代码而不是通过Web服务器：

```bash
cd movie_ui
php composer.phar install
```

### 3. 创建数据库

```bash
mysql -u root -p < database/schema.sql
```

或者手动执行 `database/schema.sql` 文件中的SQL语句。

### 3. 配置后端

编辑 `backend/config/config.php`，填写正确的配置：

```php
return [
    // 数据库配置
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'movie_db',
        'username' => 'root',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],

    // 扫描模式：'local' 或 'ssh'
    'scan_mode' => 'ssh',  // 修改为 'local' 使用本地文件系统

    // SSH配置 - 树莓派连接信息（scan_mode为ssh时使用）
    'ssh' => [
        'host' => '192.168.31.100',     // 树莓派IP地址
        'port' => 22,
        'username' => 'pi',              // SSH用户名
        'password' => 'raspberry',       // SSH密码
    ],

    // 视频文件夹配置
    // scan_mode为ssh时是树莓派上的路径
    // scan_mode为local时是本地路径
    'video_folders' => [
        '/home/pi/videos/folder1',   // 第一个视频文件夹
        '/home/pi/videos/folder2'    // 第二个视频文件夹
    ],

    // Samba配置（用于访问封面图片和视频流）
    'samba' => [
        'enabled' => true,
        // 选项1: 通过HTTP访问（如果树莓派运行了Web服务）
        'thumb_url_prefix' => 'http://192.168.31.100:8080',

        // 选项2: 挂载到本地（推荐，性能最好）
        // 'mount_path' => '/mnt/pi_videos',
    ],

    // 支持的视频文件扩展名
    'video_extensions' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v', 'webm'],

    // API基础路径
    'api_base' => '/api'
];
```

### 4. 树莓派Samba/HTTP配置（可选）

如果使用SSH模式访问视频，封面图片可以通过Samba或HTTP访问：

**选项A: 在树莓派上运行简单HTTP服务**

```bash
# 在树莓派上，进入视频目录
cd /home/pi/videos
python3 -m http.server 8080
```

**选项B: 挂载Samba到本地**

```bash
# 在服务器上挂载树莓派的Samba共享
sudo mkdir -p /mnt/pi_videos
sudo mount -t cifs //192.168.31.100/videos /mnt/pi_videos -o username=pi,password=raspberry

# 然后在config.php中设置
'samba' => [
    'enabled' => true,
    'mount_path' => '/mnt/pi_videos'
]
```

### 5. 启动PHP服务

```bash
cd backend
php -S localhost:8080
```

### 6. 启动前端开发服务器(可选)

如果需要在开发模式下运行前端：

```bash
cd frontend
npm run dev
```

前端开发服务器会通过代理访问后端API。

### 7. 扫描视频文件

访问 `http://localhost:8080/api/scan` 或在前端页面点击"扫描视频文件夹"按钮，开始扫描视频和NFO文件。

## 功能特性

- ✅ 自动扫描视频文件夹，解析Kodi格式的NFO文件
- ✅ 支持本地文件系统或SSH远程访问（如树莓派）
- ✅ 支持多层级目录结构
- ✅ 视频封面展示（通过HTTP、Samba或本地挂载访问）
- ✅ 多条件筛选：名称、年份、类型、导演、演员、评分
- ✅ 分页浏览
- ✅ 视频详情查看
- ✅ 随机推荐一部视频
- ✅ 响应式设计

## NFO文件格式

项目支持标准的Kodi电影NFO格式（XML）：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<movie>
    <title>电影标题</title>
    <originaltitle>原始标题</originaltitle>
    <year>2023</year>
    <rating>8.5</rating>
    <plot>剧情简介</plot>
    <runtime>120</runtime>
    <genre>动作</genre>
    <genre>科幻</genre>
    <director>导演名</director>
    <actor>
        <name>演员名</name>
        <role>角色名</role>
    </actor>
    <thumb>封面图片路径或URL</thumb>
</movie>
```

NFO文件应与视频文件同名（如 `movie.mp4` 对应 `movie.nfo`），或命名为 `movie.nfo` 放在视频同目录。

**thumb字段说明：**
- 如果是完整URL（如 `http://...`），直接显示
- 如果是本地路径（如 `/home/pi/videos/movie/poster.jpg`），会根据 `config.php` 中的Samba配置自动转换

## API接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/movies` | GET | 获取电影列表（支持分页和筛选） |
| `/api/movie?id={id}` | GET | 获取电影详情 |
| `/api/random` | GET | 随机获取一部电影 |
| `/api/scan` | GET | 触发扫描视频文件夹（本地或SSH） |

### 筛选参数示例

```
/api/movies?search=复仇者&year=2012&genre=动作&director=乔斯&min_rating=7
```

### 扫描模式

扫描时会根据 `config.php` 中的 `scan_mode` 选择扫描方式：
- `local`: 直接读取本地文件系统
- `ssh`: 通过SSH命令连接远程服务器（如树莓派）

## 生产部署

1. 构建前端：

```bash
cd frontend
npm run build
```

这会将Vue项目构建到 `public/` 目录。

2. 配置Web服务器（Apache/Nginx）指向 `backend/` 目录，或直接用PHP内置服务器：

```bash
php -S 0.0.0.0:80 -t backend/
```

**SSH模式注意事项：**
- 确保PHP服务器可以访问树莓派的SSH端口（默认22）
- 如果使用密码认证，密码明文存储在config.php中，建议生产环境使用SSH密钥认证

## 常见问题

**Q: SSH连接失败？**
- 检查 `config.php` 中的SSH配置（host、port、username、password）
- 确保树莓派SSH服务已启动：`systemctl status ssh`
- 尝试手动SSH连接测试：`ssh pi@192.168.x.x`

**Q: SSH扫描很慢？**
- SSH命令扫描大量文件时会比较慢，建议首次扫描后定期增量扫描
- 或者考虑将视频文件夹挂载到本地（通过Samba或NFS）

**Q: 封面图片无法显示？**
- 检查 `config.php` 中的Samba配置
- 如果NFO中的thumb是本地路径，确保已配置正确的转换规则
- 可以在浏览器开发者工具中查看图片URL是否正确

**Q: 数据库连接失败？**
- 检查 `config.php` 中的数据库配置
- 确保已执行 `database/schema.sql` 创建数据库

**Q: 扫描后没有数据？**
- 检查 `config.php` 中的视频路径是否正确（SSH模式是远程路径）
- 确保视频文件同级目录有 `.nfo` 文件
- 检查NFO文件格式是否正确
- 查看API响应中的errors字段获取详细错误
