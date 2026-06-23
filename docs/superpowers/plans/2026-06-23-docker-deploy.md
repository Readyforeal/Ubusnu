# Docker Deploy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a single FrankenPHP-based container image, a GitHub Actions workflow that publishes it to GHCR on every push to `main`, a compose stack the user copies to their Ubuntu homelab, an automatic nightly SQLite backup, and a short deploy guide.

**Architecture:** Multi-stage Dockerfile (node → composer → frankenphp). Compose stack runs one service with SQLite + backups volumes; Tailscale Serve fronts it with HTTPS. CI builds + publishes the image; the homelab does `docker compose pull && up -d` to deploy. A scheduled Artisan command writes nightly `.sqlite.gz` snapshots into a mounted dir.

**Tech Stack:** Docker, FrankenPHP 1.x (PHP 8.4), Caddy (built into FrankenPHP), GitHub Actions, GHCR, Tailscale Serve, Laravel 13.

**Reference spec:** `docs/superpowers/specs/2026-06-23-docker-deploy-design.md`

---

## File Structure

**Docker artifacts (new, repo root):**
- Create: `Dockerfile`
- Create: `.dockerignore`
- Create: `docker/entrypoint.sh`
- Create: `docker/Caddyfile`

**Compose + env:**
- Create: `compose.example.yml`
- Create: `.env.production.example`

**Backup command:**
- Create: `app/Console/Commands/BackupSqlite.php`
- Modify: `routes/console.php` (add schedule line)

**CI workflow:**
- Create: `.github/workflows/build-and-publish.yml`

**Docs:**
- Create: `docs/deploy.md`

**Tests:**
- Create: `tests/Feature/Console/BackupSqliteTest.php`
- Create: `tests/Unit/DockerArtifactsTest.php`

---

## Task 1: `.dockerignore`

**Files:**
- Create: `.dockerignore`

- [ ] **Step 1: Create the file**

```
.git
.gitignore
.github
.idea
.vscode
.editorconfig
.phpunit.cache
.phpunit.result.cache
.playwright-mcp
.superpowers
node_modules
vendor
storage/logs/*.log
storage/framework/cache/data
storage/framework/sessions/*
storage/framework/views/*
public/build
public/hot
database/database.sqlite
.env
.env.*
!.env.example
!.env.production.example
docker-compose.yml
compose.yml
tests
phpunit.xml
README.md
CHANGELOG.md
docs
.dockerignore
Dockerfile
```

- [ ] **Step 2: Commit**

```bash
git add .dockerignore
git commit -m "$(cat <<'EOF'
Add .dockerignore to exclude dev/test artifacts from image context

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: `docker/Caddyfile`

**Files:**
- Create: `docker/Caddyfile`

- [ ] **Step 1: Create the directory + file**

```bash
mkdir -p docker
```

`docker/Caddyfile`:

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

- [ ] **Step 2: Commit**

```bash
git add docker/Caddyfile
git commit -m "$(cat <<'EOF'
Add FrankenPHP Caddyfile for HTTP-only container on :8080

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: `docker/entrypoint.sh`

**Files:**
- Create: `docker/entrypoint.sh`

- [ ] **Step 1: Create**

```bash
#!/usr/bin/env bash
set -euo pipefail

# Ensure data + backup dirs are writable; create the SQLite file if missing
mkdir -p /var/www/data /var/www/backups
touch /var/www/data/database.sqlite
chown -R www-data:www-data /var/www/data /var/www/backups storage bootstrap/cache

# Run pending migrations
php artisan migrate --force

# Warm caches (idempotent)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Start the Laravel scheduler in the background (one tick per minute)
( while true; do php artisan schedule:run --no-interaction >/dev/null 2>&1; sleep 60; done ) &

# Hand off to FrankenPHP
exec frankenphp run --config /etc/frankenphp/Caddyfile
```

- [ ] **Step 2: Make executable + commit**

```bash
chmod +x docker/entrypoint.sh
git add docker/entrypoint.sh
git update-index --chmod=+x docker/entrypoint.sh
git commit -m "$(cat <<'EOF'
Add container entrypoint that migrates, caches, and runs the scheduler

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: `Dockerfile` (multi-stage)

**Files:**
- Create: `Dockerfile`

- [ ] **Step 1: Create the file**

```dockerfile
# syntax=docker/dockerfile:1.7

# ----- Stage 1: frontend -----
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.* tailwind.config.* postcss.config.* ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ----- Stage 2: vendor -----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction

# ----- Stage 3: runtime -----
FROM dunglas/frankenphp:1-php8.4-bookworm

# Install required PHP extensions (most ship in the base; opcache + pdo_sqlite needed)
RUN install-php-extensions \
    pdo_sqlite \
    bcmath \
    intl \
    zip \
    opcache

WORKDIR /var/www/html

# App source
COPY . .

# Composer vendor from stage 2
COPY --from=vendor /app/vendor ./vendor

# Built frontend assets from stage 1
COPY --from=frontend /app/public/build ./public/build

# Caddyfile + entrypoint
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Data + backup directories
RUN mkdir -p /var/www/data /var/www/backups \
    && chown -R www-data:www-data /var/www/data /var/www/backups storage bootstrap/cache

# Default env (overridden by mounted .env)
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/data/database.sqlite \
    LOG_CHANNEL=stderr

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fs http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
```

- [ ] **Step 2: Local smoke build**

```bash
docker build -t ubusnu:dev .
```

Expected: build completes, final image around 250-350 MB. Note any errors and address before continuing.

- [ ] **Step 3: Commit**

```bash
git add Dockerfile
git commit -m "$(cat <<'EOF'
Add multi-stage Dockerfile (node → composer → frankenphp)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: `compose.example.yml`

**Files:**
- Create: `compose.example.yml`

- [ ] **Step 1: Create**

```yaml
# Copy this to `compose.yml` on your homelab. Edit the image owner.
# Initial setup:
#   1. mkdir -p data backups
#   2. cp .env.production.example .env  (then fill in APP_KEY)
#   3. docker compose pull && docker compose up -d
#   4. (optional) sudo tailscale serve --bg https / http://localhost:8080

services:
  ubusnu:
    image: ghcr.io/CHANGEME/ubusnu:latest
    restart: unless-stopped
    ports:
      # Bound to loopback; Tailscale Serve fronts it with HTTPS.
      # If you skip Tailscale, change to "8080:8080" to expose on the LAN directly.
      - "127.0.0.1:8080:8080"
    volumes:
      - ./data:/var/www/data
      - ./backups:/var/www/backups
      - ./.env:/var/www/html/.env:ro
    networks:
      - default
      - ollama-net   # external; create on your Ollama stack
    healthcheck:
      test: ["CMD", "curl", "-fs", "http://127.0.0.1:8080/up"]
      interval: 30s
      timeout: 5s
      retries: 3

networks:
  ollama-net:
    external: true
```

- [ ] **Step 2: Commit**

```bash
git add compose.example.yml
git commit -m "$(cat <<'EOF'
Add compose.example.yml reference for homelab deploy

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: `.env.production.example`

**Files:**
- Create: `.env.production.example`

- [ ] **Step 1: Create**

```
APP_NAME=Ubusnu
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://CHANGEME.ts.net
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/data/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=43200
CACHE_STORE=file
QUEUE_CONNECTION=sync

BCRYPT_ROUNDS=12

# Optional fallback for Ollama. Preferred path is the settings page at /settings/coach.
# OLLAMA_BASE_URL=http://ollama:11434
# OLLAMA_MODEL=llama3.1:8b
```

- [ ] **Step 2: Commit**

```bash
git add .env.production.example
git commit -m "$(cat <<'EOF'
Add .env.production.example template

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: `BackupSqlite` Artisan command

**Files:**
- Create: `app/Console/Commands/BackupSqlite.php`
- Test: `tests/Feature/Console/BackupSqliteTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest Console/BackupSqliteTest --no-interaction
```

- [ ] **Step 2: Write failing tests**

Replace `tests/Feature/Console/BackupSqliteTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ubusnu-backups-'.uniqid();
    @mkdir($this->backupDir, 0755, true);
    putenv('BACKUPS_DIR='.$this->backupDir);

    $this->sourceDb = sys_get_temp_dir().'/ubusnu-test-'.uniqid().'.sqlite';
    // Seed a tiny SQLite file so VACUUM INTO has something to copy
    $pdo = new PDO('sqlite:'.$this->sourceDb);
    $pdo->exec('CREATE TABLE marker (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO marker (name) VALUES ('hello')");
    unset($pdo);

    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => $this->sourceDb]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDir);
    @unlink($this->sourceDb);
    putenv('BACKUPS_DIR');
});

it('writes a timestamped .sqlite.gz file into the backup dir', function () {
    $this->artisan('app:backup-sqlite')->assertExitCode(0);

    $files = glob($this->backupDir.'/ubusnu-*.sqlite.gz');
    expect($files)->toHaveCount(1);
});

it('produces a valid gzip containing a SQLite header', function () {
    $this->artisan('app:backup-sqlite')->assertExitCode(0);

    $file = glob($this->backupDir.'/ubusnu-*.sqlite.gz')[0];
    $decompressed = gzdecode((string) file_get_contents($file));

    expect(substr($decompressed, 0, 16))->toStartWith('SQLite format 3');
});

it('prunes older files past --keep', function () {
    // Pre-seed 4 stale backup files with old mtimes
    for ($i = 0; $i < 4; $i++) {
        $path = $this->backupDir.'/ubusnu-old-'.$i.'.sqlite.gz';
        file_put_contents($path, gzencode('SQLite format 3'."\0".str_repeat("\0", 16)));
        touch($path, time() - ($i + 1) * 3600);
    }

    $this->artisan('app:backup-sqlite', ['--keep' => 2])->assertExitCode(0);

    $files = glob($this->backupDir.'/ubusnu-*.sqlite.gz');
    expect($files)->toHaveCount(2);
});

it('exits 0 with a friendly note when the connection is not sqlite', function () {
    config(['database.default' => 'pgsql']);

    $this->artisan('app:backup-sqlite')
        ->expectsOutputToContain('not a sqlite')
        ->assertExitCode(0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=BackupSqliteTest
```

Expected: FAIL — command `app:backup-sqlite` does not exist.

- [ ] **Step 4: Implement the command**

Create the directory if needed:

```bash
mkdir -p app/Console/Commands
```

Create `app/Console/Commands/BackupSqlite.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupSqlite extends Command
{
    protected $signature = 'app:backup-sqlite {--keep=30}';

    protected $description = 'Snapshot the SQLite database to a gzipped file in the backups directory.';

    public function handle(): int
    {
        $driver = config('database.default');
        if ($driver !== 'sqlite') {
            $this->info("Skipping backup — configured database is not a sqlite connection (got: {$driver}).");

            return self::SUCCESS;
        }

        $source = (string) config('database.connections.sqlite.database');
        if (! is_file($source)) {
            $this->warn("Skipping backup — sqlite file not found at {$source}.");

            return self::SUCCESS;
        }

        $backupsDir = getenv('BACKUPS_DIR') ?: '/var/www/backups';
        if (! is_dir($backupsDir)) {
            @mkdir($backupsDir, 0755, true);
        }
        if (! is_dir($backupsDir) || ! is_writable($backupsDir)) {
            $this->error("Backups dir is not writable: {$backupsDir}");

            return self::FAILURE;
        }

        $timestamp = date('Y-m-d-His');
        $tempPath = $backupsDir.'/.tmp-'.$timestamp.'.sqlite';
        $finalPath = $backupsDir.'/ubusnu-'.$timestamp.'.sqlite.gz';

        try {
            // VACUUM INTO produces a consistent snapshot without disturbing the live WAL.
            $pdo = new \PDO('sqlite:'.$source);
            $pdo->exec("VACUUM INTO '".addslashes($tempPath)."'");
            unset($pdo);

            $bytes = (string) file_get_contents($tempPath);
            file_put_contents($finalPath, gzencode($bytes, 6));
            @unlink($tempPath);

            $size = round(filesize($finalPath) / 1024 / 1024, 2);

            $keep = max(1, (int) $this->option('keep'));
            $pruned = $this->prune($backupsDir, $keep);

            $this->info("Backup ".basename($finalPath)." written ({$size} MB). Kept {$keep}; pruned {$pruned}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            @unlink($tempPath);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function prune(string $dir, int $keep): int
    {
        $files = glob($dir.'/ubusnu-*.sqlite.gz') ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $toPrune = array_slice($files, $keep);
        foreach ($toPrune as $path) {
            @unlink($path);
        }

        return count($toPrune);
    }
}
```

- [ ] **Step 5: Run tests, expect PASS**

```bash
php artisan test --compact --filter=BackupSqliteTest
```

Expected: 4 tests passing.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/BackupSqlite.php tests/Feature/Console/BackupSqliteTest.php
git commit -m "$(cat <<'EOF'
Add app:backup-sqlite command (VACUUM INTO + gzip + prune)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Schedule the backup

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Append to `routes/console.php`**

Read the current file and add the schedule below the existing Artisan command:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:backup-sqlite')->dailyAt('02:00');
```

(If `Schedule` is already imported at the top, drop the `use`.)

- [ ] **Step 2: Verify schedule is registered**

```bash
php artisan schedule:list
```

Expected: output includes `app:backup-sqlite` at `0 2 * * *`.

- [ ] **Step 3: Run full suite — no regressions**

```bash
php artisan test --compact
```

Expected: 432 tests + 4 new backup tests = 436 passing, 2 skipped.

- [ ] **Step 4: Commit**

```bash
git add routes/console.php
git commit -m "$(cat <<'EOF'
Schedule app:backup-sqlite to run nightly at 02:00

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Docker-artifacts smoke test

**Files:**
- Test: `tests/Unit/DockerArtifactsTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit DockerArtifactsTest --no-interaction
```

- [ ] **Step 2: Replace `tests/Unit/DockerArtifactsTest.php`**

```php
<?php

it('ships all docker deploy artifacts', function () {
    $paths = [
        'Dockerfile',
        '.dockerignore',
        'docker/Caddyfile',
        'docker/entrypoint.sh',
        'compose.example.yml',
        '.env.production.example',
        'docs/deploy.md',
        '.github/workflows/build-and-publish.yml',
    ];

    foreach ($paths as $rel) {
        expect(is_file(base_path($rel)))
            ->toBeTrue("Missing required deploy artifact: {$rel}");
    }
});

it('Dockerfile references the entrypoint at the expected path', function () {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh');
    expect($dockerfile)->toContain('ENTRYPOINT ["entrypoint.sh"]');
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=DockerArtifactsTest
```

Expected: FAIL on the first test for `docs/deploy.md` and `.github/workflows/build-and-publish.yml` (those don't exist yet).

The second test should pass already (Dockerfile exists from Task 4).

- [ ] **Step 4: Leave the test in place — failing**

The remaining tasks (10-12) create the missing files. Re-running this test at the end of Task 12 should pass.

- [ ] **Step 5: Commit the test**

```bash
git add tests/Unit/DockerArtifactsTest.php
git commit -m "$(cat <<'EOF'
Add smoke test for docker deploy artifacts

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

(Yes, this commits a failing test. The remaining tasks make it pass. If your linter blocks committing failing tests, mark the test `->skip()` temporarily and remove the skip in Task 12.)

---

## Task 10: GitHub Actions workflow

**Files:**
- Create: `.github/workflows/build-and-publish.yml`

- [ ] **Step 1: Create**

```yaml
name: build-and-publish

on:
  push:
    branches:
      - main
  workflow_dispatch:

permissions:
  contents: read
  packages: write

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer:v2
          extensions: pdo_sqlite, bcmath, intl, zip
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Install PHP deps
        run: composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress

      - name: Install Node deps
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Prepare env
        run: |
          cp .env.example .env
          php artisan key:generate
          mkdir -p database
          touch database/database.sqlite

      - name: Migrate
        run: php artisan migrate --force

      - name: Pint
        run: vendor/bin/pint --test --format agent

      - name: Test
        run: php artisan test --compact

  publish:
    needs: test
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Set short SHA
        id: short_sha
        run: echo "sha=${GITHUB_SHA::7}" >> "$GITHUB_OUTPUT"

      - name: Lowercase image owner
        id: image_owner
        run: echo "owner=$(echo '${{ github.repository_owner }}' | tr '[:upper:]' '[:lower:]')" >> "$GITHUB_OUTPUT"

      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v6
        with:
          context: .
          file: Dockerfile
          platforms: linux/amd64
          push: true
          tags: |
            ghcr.io/${{ steps.image_owner.outputs.owner }}/ubusnu:latest
            ghcr.io/${{ steps.image_owner.outputs.owner }}/ubusnu:sha-${{ steps.short_sha.outputs.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/build-and-publish.yml
git commit -m "$(cat <<'EOF'
Add GHA workflow: test gate + push image to GHCR on main

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Deploy guide

**Files:**
- Create: `docs/deploy.md`

- [ ] **Step 1: Create**

````markdown
# Deploying Ubusnu

Targets a single Ubuntu host with Docker, Docker Compose v2, and Tailscale installed.

## One-time setup

1. **Create the deploy directory and `cd` in:**
   ```bash
   mkdir -p ~/ubusnu/{data,backups} && cd ~/ubusnu
   ```

2. **Copy the compose and env files** from this repo:
   - `compose.example.yml` → `compose.yml`
   - `.env.production.example` → `.env`

3. **Edit `compose.yml`** — replace `CHANGEME` in the image line with your GitHub username (lowercase).

4. **Generate an `APP_KEY`** and paste it into `.env`:
   ```bash
   docker run --rm ghcr.io/<your-user>/ubusnu php artisan key:generate --show
   ```

5. **Set the Ollama network up.** Your Ollama compose stack should declare an `ollama-net` external network so Ubusnu can resolve `ollama:11434`. If you'd rather not share a network, set `OLLAMA_BASE_URL` later via the settings page to whatever hostname works on your tailnet.

6. **Boot the stack:**
   ```bash
   docker compose pull && docker compose up -d
   ```

7. **Front it with Tailscale Serve** for HTTPS:
   ```bash
   sudo tailscale serve --bg https / http://localhost:8080
   ```

8. **Open `https://<host>.<tailnet>.ts.net`** in any tailnet-connected browser and register your first user.

## Day-to-day

- **Update:** `docker compose pull && docker compose up -d`
- **Logs:** `docker compose logs -f ubusnu`
- **Tinker:** `docker compose exec ubusnu php artisan tinker`
- **Restart:** `docker compose restart ubusnu`

## Backups

A scheduled job inside the container writes a gzipped snapshot to `./backups/` every night at 02:00 (container local time). Files are named `ubusnu-YYYY-MM-DD-HHMMSS.sqlite.gz` and the last 30 are kept.

**Manual backup:**
```bash
docker compose exec ubusnu php artisan app:backup-sqlite
```

**Restore from a backup:**
```bash
docker compose stop ubusnu
gunzip < backups/ubusnu-2026-06-23-020000.sqlite.gz > data/database.sqlite
docker compose start ubusnu
```

Off-site copies: use `rclone`, `restic`, `rsync`, or the homelab-level snapshotting you already trust. The `./backups/` dir is a normal host directory.

## Image visibility

By default the image is published as **public** on GHCR. If you'd rather keep it private:

1. Open `https://github.com/<your-user>/ubusnu/pkgs/container/ubusnu`
2. Settings → Change visibility → Private

Then on the homelab, do a one-time login:
```bash
docker login ghcr.io -u <your-user> -p <a PAT with read:packages>
```

## Troubleshooting

- **`/up` returns 500** — check `docker compose logs ubusnu`. Most likely `.env` is missing `APP_KEY` or the data volume isn't writable.
- **Migrations fail on boot** — the container won't start. Look at the logs to see which migration broke; downgrade with `docker compose pull ghcr.io/...:sha-<previous>` and `up -d`.
- **Ollama unreachable** — verify `ollama-net` is shared, or change `OLLAMA_BASE_URL` to a Tailscale hostname.
- **Disk filling up from backups** — drop `--keep` by overriding the schedule: edit `routes/console.php` and pass `->dailyAt('02:00')->arguments(['--keep' => '7'])` (or whatever count you want), then rebuild the image.
````

- [ ] **Step 2: Commit**

```bash
git add docs/deploy.md
git commit -m "$(cat <<'EOF'
Add deploy guide for Ubuntu homelab + Tailscale Serve

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: Final integration check

**Files:** none modified; this is verification.

- [ ] **Step 1: Run the artifacts smoke test (from Task 9) — now should PASS**

```bash
php artisan test --compact --filter=DockerArtifactsTest
```

Expected: 2 tests passing.

- [ ] **Step 2: Run the full suite**

```bash
php artisan test --compact
```

Expected: 432 (prior) + 4 (backup) + 2 (artifacts) = 438 passing, 2 skipped.

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: clean.

- [ ] **Step 4: Local image build**

```bash
docker build -t ubusnu:dev .
```

Expected: build succeeds. Note the final image size with `docker images ubusnu:dev`.

- [ ] **Step 5: Local image migration smoke**

```bash
APP_KEY="base64:$(openssl rand -base64 32)"
docker run --rm -e APP_KEY="$APP_KEY" ubusnu:dev php artisan migrate --force --no-interaction 2>&1 | tail -20
```

Expected: migrations run; exit cleanly. (The container will exit because we override the entrypoint with a one-shot command.)

- [ ] **Step 6: Sanity-check the workflow file syntax**

```bash
# If `yq` or `gh workflow view` is handy:
yq '.jobs.publish.steps' .github/workflows/build-and-publish.yml >/dev/null
```

Expected: valid YAML, no errors. (If you don't have `yq`, skip this step — the workflow will run when pushed.)

- [ ] **Step 7: Commit anything that changed during verification**

If Pint or anything else touched files:

```bash
git add -A
git commit -m "$(cat <<'EOF'
Final integration cleanup for Phase 7

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

If nothing changed, skip this commit.

---

## Self-Review Notes

- **Spec coverage:**
  - Dockerfile: Task 4
  - .dockerignore: Task 1
  - Caddyfile: Task 2
  - Entrypoint: Task 3
  - compose.example.yml: Task 5
  - .env.production.example: Task 6
  - BackupSqlite command: Task 7
  - Schedule: Task 8
  - Artifacts smoke test: Task 9, verified in Task 12
  - GHA workflow: Task 10
  - Deploy guide: Task 11
  - Final smoke: Task 12

- **No placeholders.** All steps have runnable code or commands. `CHANGEME` in the compose/env templates is intentional — those are user-edit points, documented in `docs/deploy.md`.

- **Type consistency.** No cross-task type concerns — this is mostly config files and one self-contained Artisan command.

- **Test totals.** ~6 new tests (4 backup + 2 artifacts).

- **One asymmetry worth flagging:** Task 9 commits a failing test that passes once Task 12 runs. If your CI runs per-commit (it doesn't here — only on push to main, and these tasks all roll up before that push), this would be a problem. For this phase it's fine.
