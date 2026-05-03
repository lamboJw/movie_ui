Write-Host "==================== Start Deploy ===================="
Write-Host ""

$config = @{
    RPI_HOST = "192.168.31.59"
    RPI_USER = "pi"
    RPI_PASSWORD = "pi"
    LocalPublic = "frontend\dist"
}

Write-Host "[1/5] Clean local public folder..."
if (Test-Path "public") {
    Remove-Item "public" -Recurse -Force
}
New-Item -ItemType Directory -Path "public" -Force | Out-Null

Write-Host "[2/5] Build frontend..."
Set-Location "frontend"
npm run build
Set-Location ".."

Write-Host "[3/5] Clean remote public folder..."
Write-Host "[SSH] Removing remote files..."
sshpass -p $config.RPI_PASSWORD ssh -o StrictHostKeyChecking=no "$($config.RPI_USER)@$($config.RPI_HOST)" "rm -rf /home/lambojw/movie_ui/public/*"

Write-Host "[4/5] Upload files to Raspberry Pi..."
Write-Host "[SSH] Uploading index.html..."
sshpass -p $config.RPI_PASSWORD scp -o StrictHostKeyChecking=no "$($config.LocalPublic)\index.html" "$($config.RPI_USER)@$($config.RPI_HOST):/home/lambojw/movie_ui/public/"

Write-Host "[SSH] Uploading assets..."
sshpass -p $config.RPI_PASSWORD scp -o StrictHostKeyChecking=no -r "$($config.LocalPublic)\assets" "$($config.RPI_USER)@$($config.RPI_HOST):/home/lambojw/movie_ui/public/"

Write-Host ""
Write-Host "==================== Deploy Complete ===================="
Write-Host "Access http://$($config.RPI_HOST):8899"