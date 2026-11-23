#!/usr/bin/env bash
# Builds Skimr and uploads it to the InfinityFree staging site over FTP (spec 17.2).
# Same steps as deploy-staging.ps1; uses rsync + lftp instead of robocopy + WinSCP.
#
#   export SKIMR_FTP_HOST=ftpupload.net SKIMR_FTP_USER=if0_12345678 SKIMR_FTP_PASS='...'
#   ./deploy/deploy-staging.sh            # build + upload
#   ./deploy/deploy-staging.sh --dry-run  # build only
#
# The staging .env is never uploaded by this script; put it in htdocs/.env by hand once.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/deploy/build"
TARGET="$BUILD/htdocs"
REMOTE_DIR="${SKIMR_FTP_DIR:-/htdocs}"
DRY_RUN=false
SKIP_FRONTEND=false
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=true ;;
    --skip-frontend) SKIP_FRONTEND=true ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

if [ "$DRY_RUN" = false ]; then
  : "${SKIMR_FTP_HOST:?set SKIMR_FTP_HOST}" "${SKIMR_FTP_USER:?set SKIMR_FTP_USER}" "${SKIMR_FTP_PASS:?set SKIMR_FTP_PASS}"
  command -v lftp >/dev/null || { echo "lftp is required (apt install lftp / brew install lftp)" >&2; exit 1; }
fi

if [ "$SKIP_FRONTEND" = false ] && [ -f "$ROOT/frontend/package.json" ]; then
  echo "==> Build the dashboard"
  (cd "$ROOT/frontend" && npm ci && npm run build)
fi

if [ -f "$ROOT/backend/package.json" ]; then
  echo "==> Build the public site CSS (Tailwind CLI)"
  (cd "$ROOT/backend" && npm ci && npm run build:public)
fi

echo "==> Copy backend/ to deploy/build/htdocs"
rm -rf "$BUILD"
mkdir -p "$TARGET"
rsync -a \
  --exclude vendor/ --exclude var/ --exclude tests/ --exclude .phpunit.cache/ --exclude node_modules/ \
  --exclude '.env' --exclude '.env.*' --exclude phpunit.xml.dist --exclude phpstan.neon.dist \
  --exclude .php-cs-fixer.dist.php --exclude '*.cache' \
  "$ROOT/backend/" "$TARGET/"
cp "$ROOT/deploy/htdocs.htaccess" "$TARGET/.htaccess"

echo "==> Install production PHP dependencies"
composer install --working-dir="$TARGET" --no-dev --optimize-autoloader --no-interaction --no-progress

if [ "$DRY_RUN" = true ]; then
  echo "Dry run: build is ready in $TARGET (nothing uploaded)."
  exit 0
fi

echo "==> Upload to $SKIMR_FTP_HOST$REMOTE_DIR"
# --delete mirrors the build, but the excluded paths on the server are kept:
# .env (the server's secrets), var/ (logs) and the GitHub Actions deploy state file.
# TLS is required, so the password and files are never sent in clear text.
# The password goes through the environment (--env-password), not the command line,
# so it doesn't show up in the process list.
export LFTP_PASSWORD="$SKIMR_FTP_PASS"
lftp --env-password -u "$SKIMR_FTP_USER" "ftp://$SKIMR_FTP_HOST" <<EOF
set ftp:ssl-force yes
set ftp:ssl-protect-data yes
mirror --reverse --delete --parallel=4 --exclude-glob .env --exclude-glob var/ --exclude-glob .ftp-deploy-sync-state.json "$TARGET/" "$REMOTE_DIR/"
bye
EOF
unset LFTP_PASSWORD

echo "Deployed. Check https://skimr.foliony.app/api/v1/health in a browser."
