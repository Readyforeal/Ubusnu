# Docker Deploy Implementation Design

**Phase:** 7 (final phase)

**Goal:** Ship a single FrankenPHP-based container image, a GHA workflow that publishes it to GHCR on every push to `main`, a compose stack the user copies to their Ubuntu homelab, automatic SQLite backups, and a short deploy guide.

## Foundational Decisions

| Decision | Choice |
|---|---|
| Production database | **SQLite on a mounted volume.** Keeps dev/prod parity. File-copy backups. |
| Web server / PHP runtime | **FrankenPHP single binary.** Caddy + PHP fused. No php-fpm + nginx split. |
| Image build & distribution | **GitHub Actions → GHCR.** Test job gates publish. `latest` + `sha-<short>` tags. |
| Backups | **Nightly Artisan command** writes a gzipped `VACUUM INTO` dump to a mounted backups dir. Keeps last 30 by default. |
| Networking to Ollama | **External docker network** + configurable `OLLAMA_BASE_URL` (settings page). No tight coupling. |
| HTTPS | **Tailscale Serve.** Container exposes plain HTTP on `127.0.0.1:8080`; `tailscale serve` fronts it with auto-issued certs on the tailnet. |
| Migrations on deploy | **Auto-run on container start** via entrypoint. `php artisan migrate --force` before FrankenPHP boots. |

## Architecture

One container image plus a few deploy-time artifacts. No sidecar containers, no orchestration framework.

- **Image:** built from a multi-stage Dockerfile in the repo root. Frontend assets compile in a Node stage, vendor installs in a Composer stage, runtime is FrankenPHP. Final image is lean.
- **Compose stack on homelab:** one service. Mounted volumes for the SQLite file, backups, and `.env`. External network reference so the container can reach the Ollama container by hostname.
- **CI:** one workflow at `.github/workflows/build-and-publish.yml`. Test job blocks the publish job. Push to main → image in GHCR within a few minutes.
- **HTTPS:** a `tailscale serve` one-liner on the host fronts the container. No certificates to renew, no Caddy/Traefik config to maintain.

## Image (Dockerfile)

Single multi-stage `Dockerfile` at repo root.

### Stage 1: `frontend` (node:22-alpine)

- Copies `package.json`, `package-lock.json`, `vite.config.*`, `tailwind.config.*`, `resources/`, `public/`.
- `npm ci && npm run build`.
- Output: `public/build/` (Vite manifest + bundles).

### Stage 2: `vendor` (composer:2)

- Copies `composer.json`, `composer.lock`.
- `composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction`.
- Output: `vendor/`.

### Stage 3: `runtime` (dunglas/frankenphp:1-php8.4-bookworm)

- Install any missing PHP extensions (most are present in the base; verify `pdo_sqlite`, `bcmath`, `intl`, `zip`, `opcache`).
- `WORKDIR /var/www/html`.
- Copy app source from build context.
- Copy `vendor/` from stage 2.
- Copy `public/build/` from stage 1.
- Create `/var/www/data` and `/var/www/backups` with `www-data:www-data` ownership.
- Default env in the image: `DB_CONNECTION=sqlite`, `DB_DATABASE=/var/www/data/database.sqlite`. `.env` mount overrides.
- Copy `docker/entrypoint.sh` to `/usr/local/bin/entrypoint.sh`, mark executable.
- Copy `docker/Caddyfile` to `/etc/frankenphp/Caddyfile`.
- `EXPOSE 8080`.
- `HEALTHCHECK CMD curl -fs http://127.0.0.1:8080/up || exit 1`.
- `ENTRYPOINT ["entrypoint.sh"]`.

### Entrypoint (`docker/entrypoint.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail

mkdir -p /var/www/data /var/www/backups
touch /var/www/data/database.sqlite
chown -R www-data:www-data /var/www/data /var/www/backups storage bootstrap/cache

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

( while true; do php artisan schedule:run --no-interaction >/dev/null 2>&1; sleep 60; done ) &

exec frankenphp run --config /etc/frankenphp/Caddyfile
```

### Caddyfile (`docker/Caddyfile`)

```
{
    auto_https off
    admin off
}

:8080 {
    root * /var/www/html/public
    php_server
    encode zstd gzip
}
```

### `.dockerignore`

Excludes `node_modules/`, `vendor/`, `tests/`, `.git/`, local `.env`, `database/database.sqlite`, `storage/logs/`, IDE config, and the various dotfiles that aren't needed in the image.

## Compose Stack

### `compose.example.yml` (committed)

```yaml
services:
  ubusnu:
    image: ghcr.io/<user>/ubusnu:latest
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"   # bound to loopback; Tailscale Serve fronts it
    volumes:
      - ./data:/var/www/data
      - ./backups:/var/www/backups
      - ./.env:/var/www/html/.env:ro
    networks:
      - default
      - ollama-net             # external; created by your Ollama stack
    healthcheck:
      test: ["CMD", "curl", "-fs", "http://127.0.0.1:8080/up"]
      interval: 30s
      timeout: 5s
      retries: 3

networks:
  ollama-net:
    external: true
```

The user copies this to their homelab as `compose.yml` and edits the image owner.

### `.env.production.example` (committed)

```
APP_NAME=Ubusnu
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://<host>.<tailnet>.ts.net
APP_TIMEZONE=America/Chicago
APP_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/data/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=43200
CACHE_STORE=file

# Optional: Ollama via the settings page is preferred. This env is read only if the
# settings page has no value stored.
# OLLAMA_BASE_URL=http://ollama:11434
# OLLAMA_MODEL=llama3.1:8b
```

The user copies this to `.env` and generates an `APP_KEY` with a one-off `docker run --rm` call.

## Backup Command

`app/Console/Commands/BackupSqlite.php`. Single invokable command.

### Signature

```
app:backup-sqlite {--keep=30}
```

### Behavior

1. Reads `database.default` and the corresponding `database.connections.<driver>.database` config. Bails (exit 0 with a friendly note) when the driver isn't `sqlite`.
2. Bails when the source `.sqlite` file is missing.
3. Issues `VACUUM INTO '<temp_path>'` against the source DB. This produces a consistent snapshot without disturbing the live WAL.
4. Gzips the temp file to `<BACKUPS_DIR>/ubusnu-YYYY-MM-DD-HHMMSS.sqlite.gz`.
5. Deletes the temp file.
6. Lists `.sqlite.gz` files in the backups dir, sorts by mtime descending, deletes everything past `--keep`.
7. Returns a one-line summary.

### Env keys

- `BACKUPS_DIR` — default `/var/www/backups`. Used so dev tests can point to a tmp dir.

### Schedule

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:backup-sqlite')->dailyAt('02:00');
```

## CI (GitHub Actions)

Single workflow at `.github/workflows/build-and-publish.yml`.

### Triggers

- `push` to `main`
- `workflow_dispatch`

### Job 1: `test`

- `runs-on: ubuntu-latest`
- Sets up PHP 8.4 with the project's required extensions
- `composer install --prefer-dist --no-progress`
- `cp .env.example .env`
- `php artisan key:generate`
- `touch database/database.sqlite`
- `php artisan migrate --force`
- `vendor/bin/pint --test --format agent`
- `php artisan test --compact`

### Job 2: `publish`

`needs: test`. Runs only if `test` passes.

- `actions/checkout@v4`
- `docker/setup-buildx-action@v3`
- `docker/login-action@v3` against `ghcr.io` using `${{ secrets.GITHUB_TOKEN }}`
- `docker/build-push-action@v6`:
  - `platforms: linux/amd64`
  - tags: `ghcr.io/<owner>/ubusnu:latest`, `ghcr.io/<owner>/ubusnu:sha-${{ steps.short.outputs.sha }}` (computed via a small `run` step that exports `sha=${GITHUB_SHA::7}`)
  - `cache-from: type=gha` / `cache-to: type=gha,mode=max`

### Image visibility

Default is public on first publish (lower-friction for `docker pull`). User can flip the package to private in GHCR's package settings; the homelab then needs a one-time `docker login ghcr.io` with a PAT. We document both paths in `docs/deploy.md`.

## Deploy Guide (`docs/deploy.md`)

Short, copy-pasteable.

### One-time host setup

1. `mkdir -p ~/ubusnu/{data,backups} && cd ~/ubusnu`
2. Copy `compose.example.yml` from the repo as `compose.yml`. Edit the image owner.
3. Copy `.env.production.example` as `.env`. Generate the app key:
   ```bash
   docker run --rm ghcr.io/<user>/ubusnu php artisan key:generate --show
   ```
   Paste the output as `APP_KEY`.
4. `docker compose pull && docker compose up -d`
5. Front it with Tailscale: `sudo tailscale serve --bg https / http://localhost:8080`
6. Open `https://<host>.<tailnet>.ts.net` and register the first user.

### Day-to-day

- **Update:** `docker compose pull && docker compose up -d`
- **Logs:** `docker compose logs -f ubusnu`
- **Backups:** files appear in `./backups/`. To restore: `gunzip < <file>.sqlite.gz > data/database.sqlite`. Stop the container first; restart after.
- **Database shell:** `docker compose exec ubusnu php artisan tinker`

### Ollama wiring

- Make sure your Ollama compose declares the `ollama-net` network as well, OR point `OLLAMA_BASE_URL` at the Tailscale hostname.
- Configure in the app at `/settings/coach`.

## Testing

- `tests/Feature/Console/BackupSqliteTest.php` (~4 tests):
  - writes a gz file into the backup dir
  - the gz is valid and decompresses to a file with a SQLite header
  - `--keep` prunes old files when exceeded
  - exits 0 (non-error) when the configured driver isn't sqlite
- `tests/Unit/DockerArtifactsTest.php` (1 test):
  - asserts `Dockerfile`, `docker/entrypoint.sh`, `docker/Caddyfile`, `compose.example.yml`, `.env.production.example`, `.dockerignore`, and the workflow file all exist
  - asserts the Dockerfile references the entrypoint at the expected path

**~5 new tests.**

### Manual verification (in the plan, not automated)

- `docker build -t ubusnu:dev .` succeeds locally
- `docker run --rm -e APP_KEY="base64:..." ubusnu:dev php artisan migrate --force` succeeds
- GHA workflow runs green on the first push to main after merge
- Pulling and running the image on the homelab reaches `/up` over Tailscale

## Out of Scope

- Multi-platform images (linux/arm64) — add when needed
- Semver release tags / changelogs
- Database migration tooling SQLite ↔ Postgres
- Cloudflare Tunnel / custom-domain TLS — Tailscale Serve covers it
- Image signing (cosign)
- Queue worker container — app doesn't use queued jobs yet; the in-entrypoint scheduler covers the one nightly job
- Litestream / S3 replication — defer until you want off-site copies; current backup story is local-only
