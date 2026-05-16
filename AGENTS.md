# movie_ui — agent guide

## Stack
- **Backend**: PHP 8.1+ (no framework), entrypoint `backend/index.php` does routing
- **Frontend**: Vue 3 + Vue Router 4 + Axios, built with Vite
- **Database**: MySQL 5.7+ / MariaDB 10.2+, schema in `database/schema.sql`
- **Deploy target**: Raspberry Pi (192.168.31.59), nginx reverse-proxying PHP-FPM

## Commands

```bash
# dev: start PHP built-in server (serves both API and static frontend)
cd backend && php -S localhost:8080

# dev: start Vite dev server (proxies /api to PHP on port 8899)
cd frontend && npm run dev

# build frontend (output goes to public/)
cd frontend && npm run build

# scan videos (trigger via API)
curl http://localhost:8080/api/scan
# or via CLI script
php backend/api/scan_cli.php
```

## Key architecture

- `backend/index.php` is the single PHP entrypoint. It routes paths under `/api/` to files in `backend/api/`. All other paths serve static files from `public/`.
- `backend/config/config.php` has DB creds, scan mode (`local`|`ssh`), video folder paths, and image URL mapping.
- SSH mode uses system `ssh` + `sshpass` (NOT phpseclib). `backend/includes/SshConnection.php`.
- The frontend build output lands in `public/`. `public/` is gitignored.
- Nginx config (`nginx/movie_ui.conf`) serves on port 8899. Vite dev server proxies `/api` to `http://192.168.31.59:8899/api`.
- **Video streaming**: `backend/api/stream.php` supports HTTP Range requests (seek/scrub). Route `/api/stream?id=X`. Frontend component at `frontend/src/components/VideoPlayer.vue`, route `/play/:id`.
- **Image sets**: `/api/image_set?id=X` and `/api/image_sets` endpoints. Frontend at `/image_set/:id`.
- **Mobile responsive**: All frontend views have `@media (max-width: 768px)` breakpoints.

## Database

```bash
mysql -u root -p < database/schema.sql
```

Creates `movie_db` with tables: `movies`, `genres`, `movie_genres`, `actors`, `movie_actors`.

## Quirks

- **No test framework, no linter, no typecheck**. None configured.
- **Composer is optional**. The backend uses `require_once` for includes; `composer.json` only defines classmap autoloading for `backend/includes/` and `backend/config/`.
- **`public/` is gitignored** (build artifact). If it's empty, the frontend won't load until `npm run build` is run.
- **Build script** (`frontend/package.json`) auto-copies `dist/*` to `../public/` after Vite build (Unix `rm -rf ../public/assets && cp -r dist/* ../public/`).
- **config.php contains real credentials** for a local/RPi dev setup. Don't commit secrets.
- **DEPLOY.md** has the full Raspberry Pi deployment procedure (PHP-FPM + nginx + Python HTTP server for covers).
