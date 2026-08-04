#!/bin/bash
# Build the LimeSurvey image for surveys.swe.com.ly from this fork.
#   ./build.sh [BRANCH] [TAG]
# Defaults build the currently-deployed Phase-1 image.
set -euo pipefail
BRANCH="${1:-feat/phase1-tenancy}"
TAG="${2:-limesurvey-swe:phase1}"
cd "$(dirname "$0")"

echo "[build] cloning ${BRANCH} ..."
rm -rf src
git clone --depth 1 -b "${BRANCH}" https://github.com/swe-sanad/LimeSurvey.git src

echo "[build] docker build ${TAG} (takes a few minutes) ..."
docker build -t "${TAG}" -f Dockerfile .
echo "BUILD_DONE_OK ${TAG}"
