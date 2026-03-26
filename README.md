# QualRegistry UK

Public UK qualifications repository + API (England first), with UK-wide architecture.

## Stack
- **Backend:** Laravel 13 (PHP 8.3)
- **Frontend:** Inertia.js + React + Vite + Tailwind
- **UI components:** shadcn/ui (starter config included)
- **Testing/Linting:** Pest, Laravel Pint, ESLint
- **Deployment target:** Laravel Cloud

## Local setup
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run dev
```

In another terminal:
```bash
php artisan serve
```

## CI
GitHub Actions runs on push/PR and covers:
- PHP lint (`pint --test`)
- JS lint (`npm run lint`)
- Tests (`php artisan test`)
- Frontend build (`npm run build`)

See `.github/workflows/ci.yml`.

## shadcn/ui
- Starter config is in `components.json`
- Setup notes: `docs/SHADCN-SETUP.md`

## Initial planning docs
- docs/PRD-v0.md
- docs/DATA-CONTRACT-v0.md
- docs/INGESTION-ARCHITECTURE-v0.md

## Institutions ingestion (England schools + FE/HE placeholders)
Run from repo root:

```bash
php artisan ingest:institutions-england
```

Deterministic rerun command (pin source URL):

```bash
php artisan ingest:institutions-england --csv-url="https://ea-edubase-api-prod.azurewebsites.net/edubase/downloads/public/edubasealldataYYYYMMDD.csv"
```

Expected output:

```text
Schools: inserted=<N> updated=<N> skipped=<N>
Colleges: Colleges ingestion not configured yet. TODO: wire authoritative FE/HE source (services.institutions.colleges).
Universities: Universities ingestion not configured yet. TODO: wire authoritative FE/HE source (services.institutions.universities).
Run report: /.../reports/ingestion/institutions/<YYYY-MM-DD>/run-report.md
```

## One-command England ingestion runbook (ops)
Run from repo root:

```bash
php artisan ingest:all-england
```

Deterministic rerun for a known date + pinned schools source:

```bash
php artisan ingest:all-england --run-date=2026-03-26 --csv-url="https://ea-edubase-api-prod.azurewebsites.net/edubase/downloads/public/edubasealldataYYYYMMDD.csv"
```

What this orchestrates:
1. `ingest:institutions-england`
2. `ingest:ofqual`

Behavior:
- Stops on first failed step (safe default).
- Prints per-step status + exit code summary.
- Prints latest run-report file paths when present.

List latest ingestion reports:

```bash
php artisan ingest:reports --limit=10
```

Local-only manual trigger endpoint (guarded for safety):
- `POST /internal/ingestion/all-england/run`
- `GET /internal/ingestion/reports?limit=10`

Notes:
- Endpoints require authenticated web session.
- Endpoints return **403** outside `APP_ENV=local`.
- For Laravel Cloud/prod operations, use artisan commands instead.

## Ofqual ingestion POC (real data)
Run from repo root (PHP/Laravel only):

```bash
php artisan ingest:ofqual
```

What it does in one deterministic pass:
- Downloads source CSVs to `data/raw/ofqual/<YYYY-MM-DD>/`
- Writes `sha256sums.txt` alongside raw files
- Transforms to canonical CSVs in `data/canonical/<YYYY-MM-DD>/`:
  - `awarding_bodies.csv`
  - `qualifications.csv`
- Loads canonical outputs into SQLite at `storage/qualregistry.sqlite`
  - tables: `awarding_bodies`, `qualifications`
- Generates run reports in `reports/ingestion/ofqual/<YYYY-MM-DD>/`:
  - `run-report.json`
  - `run-report.md`

Runtime note:
- Ingestion runtime is now Laravel-native PHP (no Python dependency in the ingestion path).

Optional:
```bash
php artisan ingest:ofqual --run-date=2026-03-26
```
