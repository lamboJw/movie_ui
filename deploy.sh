#!/bin/bash

echo "==================== 开始部署 ===================="
echo ""

RPI_HOST="192.168.31.59"
RPI_USER="pi"
RPI_PASSWORD="pi"
LOCAL_PUBLIC="frontend/dist"

echo "[1/5] 清理本地 public 文件夹..."
if [ -d "public" ]; then
    rm -rf public
fi
mkdir -p public
echo ""

echo "[2/5] 构建前端..."
cd frontend
npm run build
cd ..
echo ""

echo "[3/5] 连接树莓派并清理远程目录..."
echo "[SSH] 删除远程 public 目录..."
sshpass -p $RPI_PASSWORD ssh -o StrictHostKeyChecking=no $RPI_USER@$RPI_HOST "rm -rf /home/lambojw/movie_ui/public/*"
echo ""

echo "[4/5] 上传文件到树莓派..."
echo "[SSH] 上传 index.html..."
sshpass -p $RPI_PASSWORD scp -o StrictHostKeyChecking=no "$LOCAL_PUBLIC/index.html" $RPI_USER@$RPI_HOST:/home/lambojw/movie_ui/public/

echo "[SSH] 上传 assets 目录..."
sshpass -p $RPI_PASSWORD scp -o StrictHostKeyChecking=no -r "$LOCAL_PUBLIC/assets" $RPI_USER@$RPI_HOST:/home/lambojw/movie_ui/public/

echo ""
echo "==================== 部署完成 ===================="
echo "访问 http://$RPI_HOST:8899 查看"