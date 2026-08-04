# LimeSurvey Multi-Tenancy — Production Deploy Runbook

Repeatable sequence for shipping this fork (`feat/phase1-tenancy` and beyond) to
`https://surveys.swe.com.ly`. See [`README.md`](README.md) for feature status and
[`PHASE1-DESIGN.md`](PHASE1-DESIGN.md) for design context.

## 1. Build the image

Clone the branch and build the prod image on the box (or wherever the registry push
happens):

```bash
git clone -b feat/phase1-tenancy <repo-url> limesurvey-src
cd limesurvey-src
docker build -t limesurvey-swe:<tag> .
```

Base: `php:8.1-apache` with `pdo_pgsql`, `gd`, `zip`, `intl`, `exif`, `gettext`,
`mbstring`. `COPY src` into the image; `chown www-data` on `tmp/`, `upload/`,
`application/config/`, `application/logs/` (LimeSurvey writes to these at runtime).

## 2. Pre-deploy — always back up first

```bash
docker exec platform-postgres pg_dump -U postgres -Fc -d limesurvey_db > backup.dump
```

Never skip this. It's the rollback path in step 5.

## 3. Migrate the DB via the new image, before swapping

Run the migration through the phase1 image against the live config, while the
*old* container is still serving traffic:

```bash
docker run --rm --network backend \
  -v /opt/apps/limesurvey/app/config.php:/var/www/html/application/config/config.php:ro \
  limesurvey-swe:<tag> php application/commands/console.php updatedb
```

Idempotent — safe to re-run. `709 → 710` adds the `organizations` and
`org_auditor_grants` tables and the `owner_org_id` columns on `surveys`/`users`,
and backfills every existing row into the seeded Default org.

## 4. Swap the running container

```bash
# edit the image line in /opt/apps/limesurvey/app/docker-compose.yml
cd /opt/apps/limesurvey/app
docker compose up -d
```

## 5. GOTCHA — config.php must be group-readable by www-data (learned the hard way)

The host-mounted `/opt/apps/limesurvey/app/config.php` **must** be readable by the
container's `www-data` (GID 33):

```bash
sudo chgrp 33 /opt/apps/limesurvey/app/config.php
chmod 640 /opt/apps/limesurvey/app/config.php
```

It should show as owner `deploy` / group `www-data`. If the group is instead
`deploy` (GID 1001), **every page fatals** with:

```
include(.../config.php): Failed to open stream: Permission denied
```

and the whole site goes down. Verify before/after any deploy:

```bash
docker exec -u www-data limesurvey-app head -c1 /var/www/html/application/config/config.php
```

If this returns a permission error instead of a byte of output, fix the group and
re-check.

## 6. Verify

- `/` — landing hero reads "Surveys that stay yours".
- `/index.php/signup` — signup form loads. (As of the first phase1 deploy this
  route 500s on submit; a fix is tracked separately — see `README.md`.)
- `/index.php/admin` — admin login still works.

## 7. Rollback

```bash
# restore the pre-deploy dump
docker exec -i platform-postgres pg_restore -U postgres -d limesurvey_db --clean < backup.dump

# revert the compose image line to the previous tag
cd /opt/apps/limesurvey/app
docker compose up -d
```

> **Change log**
> | Updated on | Feature / change | Reason |
> |---|---|---|
> | 2026-08-04 | Initial runbook | Phase 1 front door deployed to production (`limesurvey-swe:phase1`, db 709→710) |
