# TaskMate API

The Laravel REST API behind TaskMate, a task marketplace where people post
tasks, build teams, and collaborate on real projects.

## Stack

- Laravel (PHP 8.4) REST API
- Laravel Sanctum personal access tokens
- Laravel Socialite (Google and GitHub OAuth)
- Email verification with 6-character mixed codes
- MySQL

## Authentication

- **Sanctum API tokens** — `POST /api/v1/auth/register` and
  `POST /api/v1/auth/login` return a personal access token. Send it on protected
  requests as `Authorization: Bearer <token>`. `POST /api/v1/auth/logout`
  revokes the current token.
- **Email verification** — registration emails a 6-character mixed letter/digit
  code (for example `45XC78`) plus a verification link. Verify with
  `POST /api/v1/auth/email/verify`, and resend with
  `POST /api/v1/auth/email/resend` (authenticated). Codes expire after 15
  minutes.
- **Social auth** — Laravel Socialite handles **Google** and **GitHub** OAuth
  redirects and callbacks. The callback redirects to the frontend with a Sanctum
  token.

## Auth & profile endpoints

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout              # Bearer token required
GET    /api/v1/auth/me                  # Bearer token required
POST   /api/v1/auth/email/verify
POST   /api/v1/auth/email/resend        # Bearer token required

GET    /api/v1/auth/google/redirect
GET    /api/v1/auth/google/callback
GET    /api/v1/auth/github/redirect
GET    /api/v1/auth/github/callback

PUT    /api/v1/profile                  # Bearer token required
POST   /api/v1/profile/cv               # Bearer token required
DELETE /api/v1/profile/cv               # Bearer token required
GET    /api/v1/profile/links            # Bearer token required
POST   /api/v1/profile/links            # Bearer token required
PATCH  /api/v1/profile/links/reorder    # Bearer token required
PUT    /api/v1/profile/links/{link}     # Bearer token required
DELETE /api/v1/profile/links/{link}     # Bearer token required
GET    /api/v1/users/{user}             # Bearer token required
GET    /api/v1/users/{user}/cv          # Bearer token required
```

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Configure the database, mail (used for verification codes), and OAuth
credentials in `.env`:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS="noreply@taskmate.test"
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/auth/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/api/v1/auth/github/callback"
```

## Tests

```bash
php artisan test
```
