<?php

return [
    // 数据库配置（本地MySQL）
    'db' => [
        'host' => '127.0.0.1',      // 本地MySQL
        'port' => 3306,
        'database' => 'movie_db',
        'username' => 'root',
        'password' => 'jiawei1994',
        'charset' => 'utf8mb4'
    ],

    // 扫描模式：'local'（在树莓派上运行，使用本地文件系统）
    'scan_mode' => 'local',
    
    // 视频文件夹配置（树莓派上的路径）
    'video_folders' => [
        '/home/pi/disks/disk2/.hidden/jav',   // 第一个视频文件夹
        '/home/pi/disks/disk/.hidden/Media'    // 第二个视频文件夹
    ],

    // 封面图片访问配置（使用nginx）
    'image' => [
        'enabled' => true,
        // nginx服务的URL前缀
        'url_prefix' => 'http://192.168.31.59',  // nginx默认80端口
        
        // 路径映射：本地基础路径 => URL路径前缀
        // nginx配置：location /disks/ { alias /home/pi/disks/; }
        'path_mapping' => [
            '/home/pi/disks' => '/disks'  // 本地路径前缀 => URL路径前缀
        ]
    ],

    // 支持的视频文件扩展名
    'video_extensions' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v', 'webm'],

    // 封面图片扩展名
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],

    // API基础路径
    'api_base' => '/api'
];
