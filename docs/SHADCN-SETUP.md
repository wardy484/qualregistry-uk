# shadcn/ui setup notes

This repo includes a starter `components.json` compatible with the React + Inertia setup.

## First-time setup

```bash
npm install
npx shadcn@latest init
```

Use these answers if prompted:
- Framework: `Vite`
- TypeScript: `No` (current app is JSX)
- Tailwind config: `tailwind.config.js`
- Global CSS: `resources/css/app.css`
- Import alias: `@/*`

## Add your first component

```bash
npx shadcn@latest add button
```

Generated components should live under `resources/js/components/ui`.
