#!/usr/bin/env bash
# Deploy Laravel to staging server
# Usage: run this script from the project root on the SERVER:
#   bash deploy.sh
# Customize the variables below as needed.

set -euo pipefail
IFS=$'\n\t'

# ===== Configuration =====
# Project base (current directory assumed to be the Laravel project root)
PROJECT_DIR="$(pwd)"
PHP="php"
COMPOSER_BIN="composer"

# Maintenance secret (used to bypass maintenance mode)
# If empty, a random one will be generated and printed.
MAINT_SECRET=""

# Unix user/group for web server (adjust for your distro / cPanel)
WWW_USER="www-data"
WWW_GROUP="www-data"

# ===== Helpers =====
rand_secret() {
  ${PHP} -r 'echo bin2hex(random_bytes(16));'
}

note() { printf "\n[deploy] %s\n" "$*"; }
run() { echo "+ $*"; eval "$*"; }

# ===== Steps =====
note "Starting deployment in: ${PROJECT_DIR}"
cd "${PROJECT_DIR}"

if ! command -v ${COMPOSER_BIN} >/dev/null 2>&1; then
  echo "Composer not found. Install composer before running this script." >&2
  exit 1
fi

# 1) Put app in maintenance mode with secret
if [ -z "${MAINT_SECRET}" ]; then
  MAINT_SECRET=$(rand_secret)
fi
note "Enabling maintenance mode with secret"
run "${PHP} artisan down --secret=\"${MAINT_SECRET}\" || true"

# 2) Install PHP dependencies (no dev) and optimize autoloader
note "Installing composer dependencies"
run "${COMPOSER_BIN} install --no-interaction --prefer-dist --no-dev --optimize-autoloader"

# 3) Ensure .env exists
if [ ! -f .env ]; then
  note ".env not found. Creating from .env.example"
  run "cp .env.example .env"
fi

# 4) Generate app key if missing
if ! grep -qE '^APP_KEY=base64:' .env; then
  note "Generating APP_KEY"
  run "${PHP} artisan key:generate --force"
fi

# 5) Run migrations
note "Running database migrations"
run "${PHP} artisan migrate --force"

# 6) Storage link
note "Ensuring storage link"
run "${PHP} artisan storage:link || true"

# 7) Set secure permissions
note "Setting permissions for storage and cache"
run "chown -R ${WWW_USER}:${WWW_GROUP} storage bootstrap/cache || true"
run "find storage -type d -exec chmod 775 {} \\; || true"
run "find storage -type f -exec chmod 664 {} \\; || true"
run "chmod -R 775 bootstrap/cache || true"

# 8) Optimize caches
note "Caching config/routes/views"
run "${PHP} artisan config:clear || true"
run "${PHP} artisan route:clear || true"
run "${PHP} artisan view:clear || true"
run "${PHP} artisan config:cache"
run "${PHP} artisan route:cache"
run "${PHP} artisan view:cache"

# 9) Bring app back up
note "Disabling maintenance mode"
run "${PHP} artisan up"

note "Deployment complete. If you want to access during maintenance, use: /_maintenance/${MAINT_SECRET}"
