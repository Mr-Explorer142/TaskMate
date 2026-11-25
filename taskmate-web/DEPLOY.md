# Deploying TaskMate Web

## Build

```bash
npm install
VITE_API_URL=https://api.example.com/api/v1 npm run build
```

The production site is written to `dist/`. `VITE_API_URL` is baked into the
bundle at build time, so rebuild whenever the API origin changes.

## Static hosting

Serve `dist/` as a static site and add an SPA fallback: rewrite **all** unknown
paths to `/index.html` so React Router can handle deep links such as
`/tasks/12` and `/notifications`.

Examples:

- **Nginx**: `try_files $uri $uri/ /index.html;`
- **Netlify**: `/*  /index.html  200` in `_redirects`
- **Vercel**: a rewrite from `/(.*)` to `/index.html`
- **Apache**: `RewriteRule ^index\.html$ - [L]` followed by
  `RewriteCond %{REQUEST_FILENAME} !-f`,
  `RewriteCond %{REQUEST_FILENAME} !-d`, and
  `RewriteRule . /index.html [L]`

Do not enable a fallback for `/assets/*` files; those must be served directly.

## Backend origin

Add the production frontend URL to the backend `FRONTEND_URL` setting. The
Laravel API uses it for both CORS `allowed_origins` and links in verification
and password-reset emails:

```dotenv
FRONTEND_URL=https://taskmate.example.com
```

Restart/recache backend config after changing it (`php artisan optimize:clear`,
then `php artisan config:cache` and `php artisan route:cache` in production).

## Runtime notes

- The frontend stores a Sanctum Bearer token in `localStorage`; the API must be
  reachable over HTTPS in production.
- Notification delivery is queued on the API side. Run a queue worker so
  in-app notifications are created after commits.
- `npm run preview` serves the production build locally if you need a final
  click-through before publishing.
