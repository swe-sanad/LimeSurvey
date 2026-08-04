#!/bin/bash
# On-box build + deploy for surveys.swe.com.ly.
#
# First-party app: built ON the box straight from the fork's code — no container registry, no GHCR,
# no deploy token, no webhook. (Those are the customer/guest self-serve path, not ours.) Keeps a
# persistent shallow checkout and fetches only the delta, because a fresh full clone of this repo is
# pathologically slow (committed vendor/ ~hundreds of MB).
#
# Usage (on the box, as the deploy user — no sudo needed):
#   bash /opt/apps/limesurvey/build/deploy.sh [git-ref]
# git-ref = a branch or a tag (default: feat/phase1-tenancy). So the flow is:
#   commit -> `git tag deploy-v3 && git push origin deploy-v3` -> on the box: `deploy.sh deploy-v3`.
set -euo pipefail
REF="${1:-feat/phase1-tenancy}"
BUILD=/opt/apps/limesurvey/build
SRC="$BUILD/src"
APP=/opt/apps/limesurvey/app
REPO=https://github.com/swe-sanad/LimeSurvey.git

echo "== [1/5] pull $REF =="
if [ -d "$SRC/.git" ]; then
  git -C "$SRC" fetch --depth 1 origin "$REF"
  git -C "$SRC" checkout -qf FETCH_HEAD
else
  rm -rf "$SRC"
  git clone --depth 1 -b "$REF" "$REPO" "$SRC"   # one-time slow clone; later runs fetch the delta
fi
echo "   HEAD $(git -C "$SRC" rev-parse --short HEAD) ($REF)"

echo "== [2/5] build image on the box =="
# Dockerfile comes from the checkout (always current); it COPYs src/ so the context is $BUILD.
docker build -t limesurvey-swe:phase1 -f "$SRC/deploy/Dockerfile" "$BUILD" 2>&1 | tail -3

echo "== [3/5] backup DB + run migrations (idempotent; no-op if already current) =="
TS=$(date +%Y%m%d-%H%M%S)
docker exec platform-postgres pg_dump -U postgres -Fc -d limesurvey_db > "/var/tmp/limesurvey_db-$TS.dump"
echo "   backup: /var/tmp/limesurvey_db-$TS.dump ($(du -h "/var/tmp/limesurvey_db-$TS.dump" | cut -f1))"
docker run --rm --network backend \
  -v "$APP/config.php:/var/www/html/application/config/config.php:ro" \
  limesurvey-swe:phase1 php application/commands/console.php updatedb 2>&1 | tail -2

echo "== [4/5] recreate container =="
cd "$APP" && docker compose up -d --force-recreate 2>&1 | tail -3
sleep 6

echo "== [5/5] verify =="
docker ps --filter name=limesurvey-app --format '   RUNNING: {{.Image}} | {{.Status}}'
echo -n "   landing / -> "; curl -sk -o /dev/null -w '%{http_code}\n' https://surveys.swe.com.ly/
echo "DEPLOY_DONE $REF"
