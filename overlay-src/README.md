# Noted Visual Feedback — Overlay Source

This directory contains the human-readable TypeScript source for the
frontend review overlay that is compiled into `assets/js/noted-overlay.min.js`.

## Layout

- `src/index.ts` — entry point, wires up the overlay lifecycle
- `src/api.ts` — REST client against `noted/v1/`
- `src/state.ts` — in-memory store
- `src/types.ts` — shared TypeScript types
- `src/components/` — UI components (toolbar, comments panel, pin markers, etc.)
- `src/walkthrough/` — first-run onboarding
- `src/utils/` — DOM helpers, time/position math, selectors
- `src/styles/overlay.css` — shadow-DOM styles, inlined into the bundle

## Build

Requires Node 18+ and npm.

```bash
cd overlay-src
npm install
npm run build
```

Produces `../assets/js/noted-overlay.min.js` (the file the plugin enqueues on the frontend).

`npm run dev` runs vite in watch mode for iterative development.

## Tooling

- [Vite](https://vite.dev/) for bundling and minification
- TypeScript 5 (strict)

No runtime dependencies — the bundle is framework-free vanilla TypeScript that renders into a Shadow DOM root.
