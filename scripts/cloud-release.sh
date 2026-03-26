#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --force

# Avoid release-time dependency on database cache table availability.
CACHE_STORE=file php artisan optimize:clear
php artisan optimize

echo "Laravel Cloud release steps completed."
