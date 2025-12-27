#!/usr/bin/env bash
set -euo pipefail

# Bootstrap a local dev Laravel app wired to local packages via path repositories
# Usage: from repo root run: bash apps/bootstrap-dev.sh

APP_DIR="apps/dev"

if [ -d "$APP_DIR" ]; then
  echo "Directory $APP_DIR already exists. Remove it to re-create, or edit this script to use another path." >&2
  exit 1
fi

composer create-project laravel/laravel "$APP_DIR"

cd "$APP_DIR"

# Configure path repositories with symlinks so edits in packages reflect immediately
composer config --json repositories.kusikusi-models '{"type":"path","url":"../../packages/kusikusicms/models","options":{"symlink":true}}'
composer config --json repositories.kusikusi-website '{"type":"path","url":"../../packages/kusikusicms/website","options":{"symlink":true}}'
composer config --json repositories.kusikusi-media '{"type":"path","url":"../../packages/kusikusicms/media","options":{"symlink":true}}'
composer config --json repositories.kusikusi-admin '{"type":"path","url":"../../packages/kusikusicms/admin","options":{"symlink":true}}'

# Prefer stable but allow dev since we are using path repos
composer config minimum-stability dev
composer config prefer-stable true

# Require local packages at @dev
composer require kusikusicms/models:@dev kusikusicms/website:@dev kusikusicms/media:@dev kusikusicms/admin:@dev

php artisan vendor:publish --tag=kusikusicms-config --force || true

printf "\nAll set!\n- Dev app location: %s\n- Start the server: cd %s && php artisan serve\n" "$PWD" "$PWD"
