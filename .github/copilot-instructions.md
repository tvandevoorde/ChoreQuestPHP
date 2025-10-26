# ChoreQuestPHP – AI Agent Guide

## Architecture overview
- Monorepo with `backend/` (PHP 8 API + SPA host) and `frontend/` (Angular 20 standalone). Angular build artifacts live under `backend/public_html/app` so PHP can serve the SPA and API from one origin.
- `backend/public_html/index.php` is both router and asset server: non-`/api` paths fall through to `serveFrontend()` which resolves files inside `app/`, while `/api` routes dispatch through `Routing\Router` to controllers in `backend/src/Controllers`.
- Angular root component (`frontend/src/app/app.ts`) observes `BreakpointObserver` to swap between a mobile bottom-nav layout and a desktop side-nav/top-bar shell.

## Backend workflow
- Bootstrap (`backend/src/bootstrap.php`) loads `.env` via `Support\Env`, builds the config array, initialises the singleton `Database\Connection`, then runs `Database\Migrations\Schema::migrate` before returning the config.
- `Database\Connection` currently defaults to MySQL/MariaDB: DSN pieces, charset, collation, timezone, and credentials all come from the config array (populated from `.env`). `DB_DRIVER=sqlite` remains available for local work.
- Schema migrations (`Schema::migrateMysql`) fully qualify tables with the configured schema name and expect it to exist; keep MySQL and SQLite branches in sync when adding tables to avoid drift.
- Controllers expect validated query/body params (e.g. `userId` guard clauses in `ChoreListsController`) and always return JSON via `Http\Response::json`. Follow the prepared statement pattern using the shared PDO from `Connection::getInstance()`.
- Side-effect services (e.g. `Services/EmailService.php`) log to `backend/storage/logs`; constructor signatures are relied on by existing controllers so match them when extending.

## Frontend workflow
- Install deps once with `cd frontend && npm install`. Development uses `npm start` (Angular dev server) with API requests proxied to the PHP backend.
- Production build: `npm run build` runs Angular `ng build` and then `scripts/flatten-browser.js`, which moves files out of the generated `browser/` subfolder into `backend/public_html/app` and deletes the extra directory. The PHP server serves those hashed assets directly.
- Top-level layout resides in `frontend/src/app/app.html` and `app.css`, with desktop optimizations (sidebar, sticky toolbar) gated on the handset breakpoint. Feature pages such as `components/dashboard` and `components/chore-list-detail` follow a mobile-first style sheet with responsive grid breakpoints.

## Conventions & integration points
- Secrets and environment configuration stay in `backend/.env`; `.env.example` documents required keys (`DB_DATABASE`, `DB_USERNAME`, etc.). Never commit real `.env` values or generated bundles (`backend/public_html/app` is gitignored except `.gitkeep`).
- API keys and DTOs use camelCase. When adding endpoints update both `backend/public_html/index.php` routing table and the matching Angular service (e.g. `frontend/src/app/services/chore.service.ts`).
- Authentication state is persisted in `localStorage` via `AuthService`; logouts must call `authService.logout()` to clear storage and BehaviorSubject state.
- To serve the integrated stack locally use `php -S localhost:8000 -t public_html public_html/index.php`; the final argument ensures asset requests run through the router so `/app/*.js` files resolve.
- Log output (including password reset emails) lands in `backend/storage/logs`; ensure the directory stays writable.

Ping maintainers if any behaviour above feels ambiguous so this guide can stay sharp.
