#!/usr/bin/env bash
#
# MIKHMON v4 — GHAIB.NET Edition
# One-command setup for cPanel / Linux.
#
# Usage:
#   bash setup.sh
#   bash setup.sh --import-db /path/to/mikhmon.db
#   bash setup.sh --sync livebarokah
#   bash setup.sh --skip-composer
#   PHP_BIN=/usr/local/bin/php bash setup.sh
#
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT" || exit 1
PHP_BIN="${PHP_BIN:-php}"
IMPORT_DB=""
SYNC_SESSION=""
SKIP_COMPOSER=0
SKIP_MIGRATE=0

usage() {
  cat <<'USAGE'
Usage: bash setup.sh [options]

Options:
  --import-db PATH   Import a clean Mikhmon SQLite database (mikhmon.db)
  --sync SESSION     Run initial router sync after migration
  --skip-composer    Do not run composer install
  --skip-migrate     Do not run database migration
  -h, --help         Show this help

Environment:
  PHP_BIN            PHP CLI binary (default: php)
USAGE
}

while [ $# -gt 0 ]; do
  case "$1" in
    --import-db) IMPORT_DB="${2:-}"; shift 2 ;;
    --sync) SYNC_SESSION="${2:-}"; shift 2 ;;
    --skip-composer) SKIP_COMPOSER=1; shift ;;
    --skip-migrate) SKIP_MIGRATE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1"; usage; exit 2 ;;
  esac
done

info()  { printf '\033[1;34m[setup]\033[0m %s\n' "$*"; }
ok()    { printf '\033[1;32m[ok]\033[0m %s\n' "$*"; }
warn()  { printf '\033[1;33m[warn]\033[0m %s\n' "$*"; }
fail()  { printf '\033[1;31m[error]\033[0m %s\n' "$*" >&2; exit 1; }

info "Checking PHP CLI..."
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP CLI not found: $PHP_BIN (set PHP_BIN=/path/to/php)"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 70400 ? 0 : 1);' || fail "PHP 7.4+ is required"
PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
ok "PHP $PHP_VERSION"

info "Checking required extensions..."
"$PHP_BIN" -m | grep -qi 'pdo_sqlite' || fail "pdo_sqlite is not enabled. Enable it in cPanel → MultiPHP Manager → Extensions."
"$PHP_BIN" -m | grep -qi 'json' || fail "json extension is not enabled."
ok "pdo_sqlite and json are available"
if ! "$PHP_BIN" -m | grep -qi 'mbstring'; then
  warn "mbstring is not enabled; Mikhmon may fail on non-ASCII text."
fi

info "Creating runtime data directories..."
mkdir -p data/backups data/cache data/locks data/logs
chmod 750 data 2>/dev/null || true
chmod -R u+rwX data 2>/dev/null || true
ok "data directory ready"

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    chmod 600 .env
    ok ".env created from .env.example"
    warn "Review .env (MIKHMON_DB_PATH, timezone, PHP CLI)."
  else
    warn ".env.example not found; create .env manually."
  fi
else
  ok ".env already exists (not overwritten)"
fi

if grep -Eq '^MIKHMON_PUBLIC_URL=(https://mikhmon\.example\.com/?)?$' .env 2>/dev/null; then
  warn "MIKHMON_PUBLIC_URL is empty or still the example; webhook will stay disabled until set."
fi

if [ "$SKIP_COMPOSER" -eq 0 ]; then
  info "Installing Composer dependencies..."
  COMPOSER_BIN=""
  for candidate in composer "$HOME/bin/composer" composer.phar; do
    if command -v "$candidate" >/dev/null 2>&1; then
      COMPOSER_BIN="$candidate"; break
    fi
  done
  if [ -z "$COMPOSER_BIN" ]; then
    fail "Composer not found. Install it or upload vendor/, then rerun with --skip-composer."
  fi
  "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction || fail "composer install failed"
  ok "Composer dependencies installed"
fi

[ -f vendor/autoload.php ] || fail "vendor/autoload.php is missing. Run composer install or upload vendor/."

if [ -n "$IMPORT_DB" ]; then
  [ -f "$IMPORT_DB" ] || fail "Import database not found: $IMPORT_DB"
  if [ -f data/mikhmon.db ]; then
    BACKUP="data/backups/mikhmon-before-import-$(date +%Y%m%d-%H%M%S).db"
    cp -p data/mikhmon.db "$BACKUP"
    ok "Existing database backed up to $BACKUP"
  fi
  if printf '%s' "$IMPORT_DB" | grep -qi '\.gz$'; then
    gzip -dc "$IMPORT_DB" > data/mikhmon.db || fail "Failed to extract $IMPORT_DB"
  else
    cp -p "$IMPORT_DB" data/mikhmon.db || fail "Failed to copy $IMPORT_DB"
  fi
  chmod 640 data/mikhmon.db
  ok "Database imported to data/mikhmon.db"
fi

if [ "$SKIP_MIGRATE" -eq 0 ]; then
  info "Migrating database..."
  "$PHP_BIN" bin/mikhmon db:migrate --backup || fail "Database migration failed"
  ok "Database migrated"
  info "Checking database integrity..."
  "$PHP_BIN" bin/mikhmon db:maintenance --check || warn "Database maintenance check reported an issue"
fi

if [ -n "$SYNC_SESSION" ]; then
  info "Running initial sync for session: $SYNC_SESSION"
  "$PHP_BIN" bin/mikhmon sync:run --session="$SYNC_SESSION" || warn "Initial sync did not complete; retry from the UI or cron."
else
  info "Skipping router sync. Run: $PHP_BIN bin/mikhmon sync:run --session=<session>"
fi

echo
ok "Setup complete."
echo "  URL        : https://your-domain/"
echo "  Login      : mikhmon / 1234  (change it immediately)"
echo "  Data       : $ROOT/data/mikhmon.db"
echo "  Webhook    : set MIKHMON_PUBLIC_URL in .env, then save a hotspot profile once."
echo "  Next       : set document root to $ROOT, enable HTTPS, add router, test Report/Users."
echo "  Security   : verify /data/mikhmon.db returns 403/404."
echo "  Docs       : docs/DEPLOYMENT.md"
