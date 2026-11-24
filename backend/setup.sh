#!/usr/bin/env bash
# HGKA backend bootstrap: creates a fresh Laravel 12 project, installs the packages HGKA needs,
# then applies the HGKA overlay (models, migrations, controllers, routes, seeders, PDF views).
# Requires PHP 8.2+ (with ext: pdo_mysql, mbstring, gd, zip, bcmath, fileinfo, xml), Composer 2, MySQL 8.
# Run from the backend/ folder:   ./setup.sh
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
APP="${1:-$HERE/app-src}"

if [ ! -f "$APP/artisan" ]; then
  composer create-project "laravel/laravel:^12.0" "$APP" --no-interaction
fi
cd "$APP"

composer require laravel/sanctum barryvdh/laravel-dompdf maatwebsite/excel --no-interaction

# Apply HGKA files over the fresh project (replaces bootstrap/app.php, User model, routes, etc.)
cp -R "$HERE/overlay/." "$APP/"

# Sanctum migration (personal_access_tokens) + config
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction || true

# The default Laravel users/cache/jobs migration stays; HGKA adds ALTER + new tables after it.
[ -f .env ] || cp .env.example .env
if ! grep -q '^ADMIN_EMAIL=' .env; then
  { echo; echo "# --- HGKA ---"; cat "$HERE/env.hgka.example"; } >> .env
fi

php artisan key:generate --no-interaction
php artisan storage:link || true

echo
echo "Laravel + HGKA overlay ready in: $APP"
echo "Next:"
echo "  1. Edit $APP/.env  (DB_*, ADMIN_*, FRONTEND_URL, payment keys)"
echo "  2. cd $APP && php artisan migrate --seed          # add SEED_DEMO_DATA=true for sample data (dev only)"
echo "  3. php artisan serve                              # API on http://localhost:8000/api/v1"
echo "  4. Production: run the scheduler: * * * * * php $APP/artisan schedule:run"
