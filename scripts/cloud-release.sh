#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --force
php artisan optimize:clear
php artisan optimize

echo "Laravel Cloud release steps completed."
