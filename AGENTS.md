# AGENTS.md

## Running the App

### Development
- **PHP backend (dev)**: `cd backend && php -S localhost:8080`
- **Frontend dev**: `cd frontend && npm run dev`
- **Frontend build**: `cd frontend && npm run build` (outputs to `public/`)
- **Database**: `mysql -u root -p < database/schema.sql`

### Production (Raspberry Pi)
- Copy nginx config: `backend/nginx.conf` → `/etc/nginx/sites-available/movie` → `ln -s /etc/nginx/sites-available/movie /etc/nginx/sites-enabled/`- Start service: `bash backend/service.sh` or `sudo systemctl restart php8.1-fpm nginx`

## Project Structure

```
movie_ui/
├── backend/              # PHP 8.1 API (entry: index.php)
│   ├── api/              # API endpoints (movies, movie, random, scan)
│   ├── config/           # config.php (DB, SSH, video paths)
│   └── includes/         # Database, NfoParser, SshConnection, VideoScanner
├── frontend/             # Vue 3 + Vite
│   └── src/views/        # MovieList.vue, MovieDetail.vue
├── public/               # Built frontend (served by PHP backend)
└── database/schema.sql   # MySQL schema
```

## Key Commands

- API endpoints: `/api/movies`, `/api/movie?id=X`, `/api/random`, `/api/scan`
- Config: edit `backend/config/config.php` for DB credentials, SSH settings, video paths

## Tech Notes

- SSH mode uses system `ssh` + `sshpass` (not phpseclib)
- Frontend proxies to backend in dev mode
- NFO parsing expects Kodi XML format in video directories

## No Formal Lint/Typecheck

This repo has no ESLint, Psalm, PHP CS Fixer, or similar tooling configured.