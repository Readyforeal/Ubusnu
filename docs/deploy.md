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
- **Migrations fail on boot** — the container won't start. Look at the logs to see which migration broke. To roll back: edit `compose.yml` and change the `image:` tag from `:latest` to `:sha-<previous-short-sha>` (you can find available tags at `https://github.com/<your-user>/ubusnu/pkgs/container/ubusnu`), then `docker compose pull && docker compose up -d`.
- **Ollama unreachable** — verify `ollama-net` is shared, or change `OLLAMA_BASE_URL` to a Tailscale hostname.
- **Disk filling up from backups** — drop `--keep` by overriding the schedule: edit `routes/console.php` and pass `->dailyAt('02:00')->arguments(['--keep' => '7'])` (or whatever count you want), then rebuild the image.
