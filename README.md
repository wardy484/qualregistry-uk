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
