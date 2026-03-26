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
