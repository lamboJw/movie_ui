#!/bin/bash

# 启动/重启 movie_ui 服务

# 重启 PHP-FPM
sudo systemctl restart php8.1-fpm

# 重启 Nginx
sudo systemctl restart nginx

echo "movie_ui 服务已启动"

# 查看状态
sudo systemctl status php8.1-fpm nginx --no-pager