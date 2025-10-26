# ChoreQuest

A shared chore management application with a PHP REST API and Angular frontend. Users can create multiple chore lists, assign tasks, configure recurrence, and collaborate through list sharing and notifications.

## Features

### Core Functionality
- **User Management**: Registration, login, password resets (bcrypt hashed credentials)
- **Chore Lists**: Create, update, delete, and share lists with granular permissions
- **Chores**: Assignment, due dates, recurrence patterns, and completion tracking
- **Notifications**: Chore assignment and sharing alerts with read tracking
- **Integrated Delivery**: Angular bundle served directly by the PHP application for single-site hosting

### Technical Highlights
- Lightweight PHP (PDO + MySQL) backend with automatic schema migrations
- Angular 20 standalone component architecture
- RESTful API design with consistent JSON contracts
- SPA hosting via PHP front controller with static asset caching

## Project Structure

```
ChoreQuest/
├── backend/              # PHP API (public_html/ for web root, src/ for application code)
│   ├── public_html/      # index.php front controller + compiled Angular assets
│   ├── src/              # Controllers, routing, services, database layer
│   ├── storage/logs/     # Runtime logs (password reset emails, etc.)
│   └── data/             # Legacy SQLite databases (optional/local only)
├── frontend/             # Angular workspace
│   ├── src/              # Angular application source
│   └── dist/             # Optional build output (unused when building directly to backend/public_html/app)
├── LICENSE
└── README.md
```

## Prerequisites

- PHP 8.2+
- `pdo_mysql` extension enabled
- Node.js 20.x (or newer LTS)
- npm 10.x

## Getting Started

### Backend (PHP API)

1. Copy the example environment file and update credentials (never commit the resulting `.env`):
   ```bash
   cd backend
   Copy-Item .env.example .env
   ```
   Update `DB_HOST`, `DB_DATABASE` (defaults to `chorequest`), `DB_USERNAME`, and `DB_PASSWORD` with your MySQL instance details. Ensure that schema already exists and the user has full privileges to it before continuing.
2. Start the API and serve the SPA bundle:
   ```bash
   php -S localhost:8000 -t public_html
   ```
3. Visit `http://localhost:8000` to load the Angular UI served by PHP. `public_html/index.php` handles API routing and static asset delivery.

The first run will establish a PDO connection to MySQL using the provided credentials and apply any pending schema migrations automatically.

### Frontend (Angular)

1. Install dependencies:
   ```bash
   cd frontend
   npm install
   ```
2. Development server (hits the PHP API on a different origin):
   ```bash
   npm start
   ```
   This serves the app at `http://localhost:4200` and proxies API calls to `/api` on the configured backend origin.

3. Production build:
   ```bash
   npm run build
   ```
   The Angular CLI writes the hashed production assets straight into `../backend/public_html/app`; the `scripts/flatten-browser.js` post-build step flattens Angular's default `browser/` subdirectory so PHP can serve the bundle directly.

## API Overview

- `POST /api/users/register` – Register a new account
- `POST /api/users/login` – Sign in
- `POST /api/users/forgot-password` – Request reset link (emailed to log file)
- `POST /api/users/reset-password` – Complete password reset
- `GET /api/users` – List users
- `GET /api/users/{id}` – Fetch a specific user
- `GET /api/chorelists?userId={id}` – Lists owned or shared with a user
- `POST /api/chorelists?userId={id}` – Create list
- `PUT /api/chorelists/{id}` / `DELETE /api/chorelists/{id}` – Manage list
- `POST /api/chorelists/{id}/share` – Share list with a user
- `GET /api/chorelists/{choreListId}/chores` – List chores
- `POST /api/chorelists/{choreListId}/chores` – Create chore
- `PUT /api/chorelists/{choreListId}/chores/{id}` – Update chore (recurrence aware)
- `DELETE /api/chorelists/{choreListId}/chores/{id}` – Delete chore
- `GET /api/notifications?userId={id}` – Recent notifications
- `PUT /api/notifications/{id}/read` – Mark read
- `PUT /api/notifications/read-all?userId={id}` – Mark all read
- `DELETE /api/notifications/{id}` – Remove notification

All endpoints return JSON and expect JSON bodies. Authentication is sessionless (client stores user info locally); integrate your own auth/token strategy if needed.

## Development Tips

- Backend logging (e.g., password reset emails) is written to `backend/storage/logs/password_reset.log`.
- Update Angular services under `frontend/src/app/services/` to change API behavior.
- To reset the database during development, drop and recreate the MySQL schema referenced in your `.env` file, then reload the site.

## License

Refer to the LICENSE file for full licensing details.

## Contributing

Pull requests are welcome! Please open an issue for significant changes so we can discuss the approach first.
