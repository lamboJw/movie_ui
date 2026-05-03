# 部署到树莓派指南（使用nginx）

## 环境准备（在树莓派上执行）

### 1. 安装nginx
```bash
sudo apt update
sudo apt install -y nginx
```

### 2. 安装PHP 8.1+（使用PHP-FPM，推荐）
```bash
sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl php8.1-mbstring
sudo systemctl start php8.1-fpm
sudo systemctl enable php8.1-fpm
```

或使用PHP内置服务器（简单但不推荐生产环境）：
```bash
sudo apt install -y php8.1 php8.1-mysql php8.1-xml php8.1-curl
```

### 3. 安装MySQL/MariaDB
```bash
sudo apt install -y mariadb-server
sudo mysql_secure_installation
```

### 5. 安装Node.js（用于构建前端，可选）
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

## 配置nginx

### 1. 上传nginx配置文件到树莓派
```bash
# 在Mac上
scp /Users/lambojw/Documents/movie_ui/nginx/movie_ui.conf pi@192.168.31.59:/home/pi/

# 在树莓派上
sudo mv /home/pi/movie_ui.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/movie_ui.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default  # 禁用默认站点
```

### 2. 修改nginx配置（如果需要）
```bash
sudo nano /etc/nginx/sites-available/movie_ui.conf
# 修改 server_name 为你的树莓派IP（如 192.168.31.59）
# 保存后测试配置
sudo nginx -t
```

### 3. 启动nginx
```bash
sudo systemctl start nginx
sudo systemctl enable nginx  # 开机自启
```

## 上传和部署项目

### 1. 打包项目（在Mac上）
```bash
cd /Users/lambojw/Documents/movie_ui
tar -czf movie_ui.tar.gz --exclude='node_modules' --exclude='vendor' --exclude='.git' .

# 上传到树莓派
scp movie_ui.tar.gz pi@192.168.31.59:/home/pi/
```

### 2. 解压并配置（在树莓派上）
```bash
ssh pi@192.168.31.59
mkdir -p ~/movie_ui
cd ~/movie_ui
tar -xzf ~/movie_ui.tar.gz

# 修改配置（如果需要）
nano ~/movie_ui/backend/config/config.php
```

### 3. 创建数据库
```bash
mysql -u root -p < ~/movie_ui/database/schema.sql
```

### 4. 构建前端（可选，也可在Mac上构建后上传）
```bash
cd ~/movie_ui/frontend
npm install
npm run build
```

### 5. 启动服务

**方式A：使用PHP内置服务器 + nginx（简单）**
```bash
# 终端1：启动PHP服务
cd ~/movie_ui/backend
php -S 127.0.0.1:8081 index.php &

# nginx已经配置为反向代理，转发/api到8081端口
```

**方式B：使用PHP-FPM + nginx（推荐生产环境）**
```bash
sudo systemctl start php8.1-fpm
sudo systemctl enable php8.1-fpm
# nginx配置文件中取消注释PHP-FPM部分
```

### 6. 扫描视频
```bash
# 通过浏览器访问
curl http://192.168.31.59/api/scan

# 或者直接在树莓派上
cd ~/movie_ui/backend
php -r "require 'includes/Database.php'; require 'includes/NfoParser.php'; require 'includes/VideoScanner.php'; \$c=require 'config/config.php'; \$s=new VideoScanner(\$c); print_r(\$s->scanAll());"
```

## 访问应用

- **前端页面**：`http://192.168.31.59/`
- **API接口**：`http://192.168.31.59/api/movies`
- **图片访问**：`http://192.168.31.59/disks/...` (通过nginx直接读取)

## 故障排查

### 图片无法显示
```bash
# 检查nginx是否运行
sudo systemctl status nginx

# 检查nginx配置
sudo nginx -t

# 查看错误日志
tail -f /var/log/nginx/movie_error.log
```

### API无法访问
```bash
# 检查PHP服务
ps aux | grep php

# 测试API
curl http://127.0.0.1:8081/api/movies
```

## 部署步骤

### 1. 上传代码到树莓派

在Mac上执行：
```bash
# 打包项目
cd /Users/lambojw/Documents/movie_ui
tar -czf movie_ui.tar.gz --exclude='node_modules' --exclude='vendor' --exclude='.git' .

# 上传到树莓派
scp movie_ui.tar.gz pi@192.168.31.59:/home/pi/

# 在树莓派上解压
ssh pi@192.168.31.59
mkdir -p ~/movie_ui
cd ~/movie_ui
tar -xzf ~/movie_ui.tar.gz
```

### 2. 创建数据库

```bash
# 在树莓派上执行
mysql -u root -p < /home/pi/movie_ui/database/schema.sql
```

### 3. 配置项目

编辑 `/home/pi/movie_ui/backend/config/config.php`，确认配置：

```php
<?php
return [
    // 数据库配置（本地MySQL）
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'movie_db',
        'username' => 'root',
        'password' => 'jiawei1994',  // 修改为你的MySQL密码
        'charset' => 'utf8mb4'
    ],

    // 扫描模式：使用本地文件系统
    'scan_mode' => 'local',
    
    // 视频文件夹配置（树莓派上的路径）
    'video_folders' => [
        '/home/pi/disks/disk2/.hidden/jav',
        '/home/pi/disks/disk/.hidden/Media'
    ],

    // 封面图片访问配置
    'image' => [
        'enabled' => true,
        'url_prefix' => 'http://192.168.31.59:8080',  // 树莓派IP + HTTP服务端口
    ],

    // 支持的视频文件扩展名
    'video_extensions' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v', 'webm'],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'api_base' => '/api'
];
```

### 4. 构建前端（在树莓派上或Mac上）

**选项A：在树莓派上构建**
```bash
cd /home/pi/movie_ui/frontend
npm install
npm run build
```

**选项B：在Mac上构建后上传**
```bash
# 在Mac上
cd /Users/lambojw/Documents/movie_ui/frontend
npm install
npm run build

# 上传构建结果
scp -r frontend/dist pi@192.168.31.59:/home/pi/movie_ui/public
```

### 5. 启动服务

**终端1：启动HTTP服务（用于封面图片）**
```bash
ssh pi@192.168.31.59
cd /home/pi/disks
python3 -m http.server 8080
```

**终端2：启动PHP服务**
```bash
ssh pi@192.168.31.59
cd /home/pi/movie_ui/backend
php -S 0.0.0.0:8081 index.php
```

### 6. 扫描视频

在浏览器访问：`http://192.168.31.59:8081/api/scan`

或者命令行：
```bash
curl http://192.168.31.59:8081/api/scan
```

### 7. 访问前端

在浏览器访问：`http://192.168.31.59:8081/`

## 使用Systemd管理服务（可选）

创建systemd服务文件，让服务自动启动...

## 故障排查

1. **PHP服务无法启动**
   - 检查端口是否被占用：`sudo netstat -tlnp | grep 8081`
   - 查看错误日志：`journalctl -u php-movie.service`

2. **无法访问前端**
   - 检查防火墙：`sudo ufw status`
   - 开放端口：`sudo ufw allow 8081`

3. **封面图片无法显示**
   - 确认HTTP服务正在运行：`curl http://192.168.31.59:8080/`
   - 检查路径映射配置

4. **数据库错误**
   - 确认MySQL正在运行：`sudo systemctl status mariadb`
   - 测试连接：`mysql -u root -p`

## 快速测试

```bash
# 在树莓派上快速测试
cd /home/pi/movie_ui/backend
php -S 0.0.0.0:8081 index.php &

# 测试API
curl http://localhost:8081/api/movies

# 触发扫描
curl http://localhost:8081/api/scan
```
