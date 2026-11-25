# Deploying the TaskMate API

This document covers the backend-only Day 7 MVP. The `taskmate-web` frontend is
deployed separately.

## Requirements

- PHP 8.4 with the extensions required by Laravel and the installed database driver
- Composer dependencies from the committed `composer.lock`
- MySQL (or the configured production database)
- A persistent queue backend (`database` is configured by default) and a worker
- A private, persistent filesystem disk for CV uploads
- SMTP credentials for verification codes and password reset links

No new Composer or npm packages are required.

## Environment

Create a real `.env` from `.env.example` and set at least:

```env
APP_NAME=TaskMate
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.example.com

# One origin, or a comma-separated list of frontend origins.
FRONTEND_URL=https://app.example.com,https://admin.example.com

DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=taskmate
DB_USERNAME=...
DB_PASSWORD=...

BROADCAST_CONNECTION=log
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Must be private and persistent. Never set this to the public disk.
CV_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/auth/google/callback"

GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
GITHUB_REDIRECT_URI="${APP_URL}/api/v1/auth/github/callback"

TASKMATE_CV_MAX_KB=5120
TASKMATE_SIGNUP_BONUS=100
```

`FRONTEND_URL` drives both CORS `allowed_origins` and the password reset URL
(`{FRONTEND_URL}/reset-password?token=...&email=...`). Register the two OAuth
callback URLs above with Google and GitHub. Keep `APP_DEBUG=false` so error
responses never include exception traces.

CV files are private and are only served through
`GET /api/v1/users/{user}/cv`. `CV_DISK=local` stores them under
`storage/app/private`; make sure that path is on persistent storage and included
in backups. The API deliberately rejects a public disk and does not require an
S3 package.

## Release

From the project root:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan optimize
```

Run `php artisan config:clear && php artisan cache:clear` after changing `.env`
before rebuilding the caches with `php artisan optimize`. Route caching is safe:
the public health endpoint uses `HealthController`, not a route closure.

Do **not** run `migrate:fresh --seed` on production data. The seeder is for local
and staging demos only. It is re-runnable with `migrate:fresh --seed` and creates
`demo@taskmate.test`, `alice@taskmate.test`, and `bob@taskmate.test`, all with
password `password`.

## Queue worker

Database notifications and bulk notification inserts are queued with
`afterCommit`, so a running worker is required. In development:

```bash
php artisan queue:work
```

In production, supervise the worker, for example:

```ini
[program:taskmate-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /srv/taskmate-api/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/taskmate-queue.log
stopwaitsecs=60
```

Restart workers after each release so they load the new code. Monitor the
`jobs` and `failed_jobs` tables.

## Web server

Point the document root at `public/` and route all requests to `index.php`.
Terminate TLS at the server or load balancer. Allow the queue worker and web
process to read/write the same `storage/` and `bootstrap/cache/` directories,
and make sure `bootstrap/cache/` is writable during `optimize`.

## Verification

Run these after deployment (adjust the host):

```bash
curl -fsS https://api.example.com/api/v1/health
# {"status":"ok"}

php artisan route:list --path=api
php artisan queue:work --once
```

For a local end-to-end check, start `php artisan serve`, trigger an action such
as applying to a task, then run `php artisan queue:work --once` and confirm the
recipient's `GET /api/v1/notifications` response.

Before merging code changes, run the full suite:

```bash
php artisan migrate:fresh --seed
./vendor/bin/pest
vendor/bin/pint --dirty --format agent
```

## Operational notes

- Existing rate limits remain: register/login/password 10/min, apply 30/min,
  messages 60/min, CV upload 20/min, email verification/resend 6/3 per minute.
  Use a shared `CACHE_STORE` when running multiple API instances so throttles
  are enforced consistently.
- The API uses Sanctum bearer tokens; CORS uses `supports_credentials=false`.
- Composite indexes back the common task, application, transaction, and
  notification queries. Keep them when tuning schema.
- Backups must include the database plus the private CV disk.
