# Laravel Cloud deployment runbook (QualRegistry UK)

This project is deployable to Laravel Cloud with a managed SQL database.

## Readiness summary

- ✅ Laravel 13 app with `/up` health endpoint (in `bootstrap/app.php`)
- ✅ Frontend build supported via Vite (`npm run build`)
- ✅ Migrations present for app schema/session/cache/queue tables
- ⚠️ Production DB should be **MySQL/PostgreSQL** (not SQLite)
- ⚠️ Laravel Cloud project/env + GitHub linkage require dashboard auth/UI

## Recommended production defaults

Use a managed SQL DB in Laravel Cloud and set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=mysql` (or `pgsql`)
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database` (or `sync` if no worker yet)

A production env template is included at `.env.cloud.example`.

## Build + release commands

### Build command (Laravel Cloud)

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
npm ci
npm run build
```

### Release command (Laravel Cloud)

```bash
bash scripts/cloud-release.sh
```

This release script runs:

- `php artisan migrate --force`
- `php artisan optimize:clear`
- `php artisan optimize`

## One-time manual setup in Laravel Cloud (Kim)

1. Open Laravel Cloud and create/select project for `wardy484/qualregistry-uk`.
2. Connect GitHub repo and select branch `main` for auto-deploy.
3. Add environment variables from `.env.cloud.example` (production values).
4. Provision and attach a managed database (MySQL preferred).
5. Set build and release commands from this doc.
6. Trigger first deploy.
7. Verify healthcheck:
   - `https://<your-cloud-domain>/up`
8. Verify app home loads:
   - `https://<your-cloud-domain>/`

## Post-deploy checks

- Migrations succeeded in deployment logs.
- `/up` returns success.
- Home page responds 200.
- If `QUEUE_CONNECTION=database`, ensure a worker is configured before enabling async jobs.

## Rollback path

If a deploy is unhealthy:

1. In Laravel Cloud deploy history, redeploy last known-good revision.
2. Confirm `/up` is healthy.
3. Revert or patch via PR, then redeploy.
