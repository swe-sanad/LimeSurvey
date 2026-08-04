# Deploy — surveys.swe.com.ly

Deployment artifacts for running this LimeSurvey fork on the SWE-Pioneers VPS behind the
shared Traefik platform. Full step-by-step runbook (backup → migrate → swap → verify, with
the rollback path): [`../docs/multitenancy/DEPLOY.md`](../docs/multitenancy/DEPLOY.md).

## Files

| File | Purpose |
|------|---------|
| `Dockerfile` | `php:8.1-apache` + the required extensions (pdo_pgsql, gd, zip, intl, exif, gettext, mbstring); `COPY src/`. |
| `build.sh` | Clone a branch of this fork into `src/` and build the image. `./build.sh [BRANCH] [TAG]` (defaults: `feat/phase1-tenancy` → `limesurvey-swe:phase1`). |
| `docker-compose.yml` | The `limesurvey-app` service: Traefik router for `surveys.swe.com.ly`, `proxy`+`backend` networks, mounted `config.php`, `upload` volume. |
| `config.php.example` | Config template. Copy to `/opt/apps/limesurvey/app/config.php` and fill the `CHANGE_ME` values. **Never commit the real file.** |
| `.dockerignore` | Excludes `src/.git` from the build context. |

## Platform contract (owned by `SWE-Pioneers/vps-infra`)

This deploy consumes the platform; it does not define it:

- **Networks** `proxy` (edge) and `backend` (shared data plane) must already exist (external).
- **Shared `platform-postgres`** holds the DB. Provision it first:
  `sudo bash /opt/platform/scripts/provision-platform-app.sh limesurvey`.
- **Traefik** (edge, Let's Encrypt) routes `surveys.swe.com.ly` via the labels above.

## Deploy (short form)

```bash
# 1. build the image from a branch
./build.sh feat/phase1-tenancy limesurvey-swe:phase1
# 2. create /opt/apps/limesurvey/app/config.php from config.php.example, fill secrets
# 3. run migrations (idempotent) before swapping:
docker run --rm --network backend \
  -v /opt/apps/limesurvey/app/config.php:/var/www/html/application/config/config.php:ro \
  limesurvey-swe:phase1 php application/commands/console.php updatedb
# 4. bring it up
cd /opt/apps/limesurvey/app && docker compose up -d
```

## ⚠️ config.php permissions gotcha

The host `config.php` MUST be group-readable by the container's `www-data` (**GID 33**), i.e.
`sudo chgrp 33 config.php && chmod 640 config.php` (shows as owner `deploy` / group `www-data`).
If it is group `deploy` (GID 1001) instead, every page fatals with
`include(.../config.php): Failed to open stream: Permission denied` and the whole site goes down.
Verify: `docker exec -u www-data limesurvey-app head -c1 /var/www/html/application/config/config.php`.
