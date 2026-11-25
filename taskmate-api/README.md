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

GET    /api/v1/tasks                    # Bearer token required
GET    /api/v1/tasks/{task}             # Bearer token required
GET    /api/v1/tasks/{task}/team        # Bearer token required
POST   /api/v1/tasks                    # Bearer token required
PUT    /api/v1/tasks/{task}             # Bearer token required
PATCH  /api/v1/tasks/{task}/start       # Bearer token required (owner only)
PATCH  /api/v1/tasks/{task}/cancel      # Bearer token required
DELETE /api/v1/tasks/{task}             # Bearer token required
GET    /api/v1/my/tasks                 # Bearer token required

POST   /api/v1/tasks/{task}/apply       # Bearer token required
GET    /api/v1/tasks/{task}/applications  # Bearer token required (owner only)
DELETE /api/v1/applications/{application} # Bearer token required
GET    /api/v1/my/applications          # Bearer token required
PATCH  /api/v1/applications/{application}/accept  # Bearer token required (owner only)
PATCH  /api/v1/applications/{application}/reject  # Bearer token required (owner only)
PATCH  /api/v1/applications/{application}/leave   # Bearer token required

GET    /api/v1/points                   # Bearer token required
GET    /api/v1/points/transactions      # Bearer token required

POST   /api/v1/conversations            # Bearer token required
GET    /api/v1/conversations            # Bearer token required
GET    /api/v1/conversations/unread-count  # Bearer token required
GET    /api/v1/conversations/{conversation}/messages  # Bearer token required
POST   /api/v1/conversations/{conversation}/messages  # Bearer token required
POST   /api/v1/conversations/{conversation}/read      # Bearer token required
```

## Tasks

Tasks support search, filtering, sorting, and pagination through spatie
query-builder parameters:

```
GET /api/v1/tasks?filter[search]=dashboard&filter[type]=paid
    &filter[skill]=Laravel&filter[min_reward]=50&filter[max_reward]=500
    &filter[has_open_slots]=1&sort=-reward&page=2&per_page=12
```

Allowed filters: `search` (title and description), `type`, `status`, `skill`
(comma-separated, matches any), `min_reward`, `max_reward`, `has_open_slots`,
and `uploader_id`. Allowed sorts: `created_at`, `reward`, `deadline`, and
`team_size`; prefix with `-` for descending. The public list hides cancelled and
completed tasks unless `filter[status]` is provided, while `/my/tasks` includes
every status and only tasks owned by the authenticated user.

Business rules:

- Only the uploader can update, cancel, or delete a task.
- Tasks can be updated or cancelled only while `open` or `full`. Editing an
  `in_progress` task returns `409` (`This task is already in progress and can no
  longer be edited.`).
- The type cannot change once the task has applications.
- Team size cannot drop below the number of accepted members.
- A task with accepted members cannot be deleted; cancel it instead.
- Cancelling rejects all pending applications.
- List responses return `{ data, links, meta }`; single-task responses return
  `{ data, message }`. State-rule violations return `422`; delete conflicts
  return `409`.

## Applications

Applicants can apply to any open task, and owners review, accept, or reject
them. Accepted applicants fill team slots until the task is `full`.

```
POST   /api/v1/tasks/{task}/apply
       { "message": "Optional note, up to 1000 characters." }

DELETE /api/v1/applications/{application}        # Withdraw a pending application

GET    /api/v1/my/applications?filter[status]=pending&page=1
GET    /api/v1/tasks/{task}/applications?filter[status]=pending&page=1

PATCH  /api/v1/applications/{application}/accept
PATCH  /api/v1/applications/{application}/reject
```

Business rules:

- A user can apply to a task once; applying again returns `409`
  (`You have already applied to this task.`).
- Applications are accepted only while the task is `open`; a full, cancelled,
  completed, or in-progress task returns `409` with a specific message.
- Rejected applicants cannot re-apply to the same task (`409`).
- Owners cannot apply to their own task (`403`).
- Only pending applications can be withdrawn (`409` otherwise).
- Only the task owner can list applicants or accept/reject applications
  (`403` elsewhere). Accepting when the team is already full returns `409`
  (`Team is already full.`).
- Accepting fills a slot; when the last slot is filled the task becomes `full`.
- `GET /my/applications` returns 12 per page (newest first);
  `GET /tasks/{task}/applications` returns 20 per page with pending first.
- Apply is rate limited to 30 requests per minute.
- Applicant payloads expose public profile fields only (name, avatar, bio,
  skills, rating, `has_cv`, links); owner task lists also include
  owner-only `pending_count`, plus each task exposes `my_application_id` and
  `my_application_status` to the current user.

## Points

Every new account - email/password or social - receives a starter bonus of
`100` points (`config/points.php`). Each balance change is written to the
`point_transactions` ledger:

- `bonus` - starter points on signup.
- `escrow` - points held when a points task starts.
- `release` - escrow released to members as the project progresses.
- `refund` - remaining escrow returned to the uploader.

```http
GET /api/v1/points
```

```json
{
  "data": { "balance": 250, "escrowed": 200 },
  "message": "Points balance."
}
```

```http
GET /api/v1/points/transactions?page=1
```

Paginated 20 per page, newest first. Each row contains `id`, signed `amount`,
`type` (`bonus`, `escrow`, `release`, `refund`), `note`, optional `task`
(`id`, `title`), and `created_at`.

Business rules:

- Starting a points task escrows `reward x accepted members` from the
  uploader's balance. The task stores the held amount in `tasks.escrow_amount`.
- Starting with too few points returns `422`:
  `Insufficient points: you need X and have Y` under the `points` error key.
- The ledger is the source of truth: `users.points_balance` always equals the
  sum of that user's transaction amounts.
- Escrow is reduced by `release` and cleared by `refund`.

## Direct messages

Conversations are scoped to a task and are one per task/user pair. The
`conversations.task_id` column is nullable in the schema for legacy rows, but
the application and factory always set it.

```http
POST /api/v1/conversations
Content-Type: application/json

{ "task_id": 4, "user_id": 9 }
```

Returns `201` when a conversation is created and `200` when it already exists.
Only the task uploader may start a conversation with an applicant, and only an
applicant may start one with the uploader (`403` otherwise).

```http
GET /api/v1/conversations?page=1
GET /api/v1/conversations/unread-count
```

The list is ordered by the most recent message and includes `other_user`,
`task`, `last_message`, and the viewer's `unread_count`. The unread endpoint
returns `{ "data": { "count": 3 } }`.

```http
GET /api/v1/conversations/{conversation}/messages
GET /api/v1/conversations/{conversation}/messages?before_id=120
GET /api/v1/conversations/{conversation}/messages?after_id=150
```

Without a cursor the newest 30 messages are returned oldest first. `before_id`
loads 30 older messages for pagination; `after_id` returns up to 100 newer
messages for polling.

```http
POST /api/v1/conversations/{conversation}/messages
Content-Type: application/json

{ "body": "Hi! I'd like to discuss the interview details." }

POST /api/v1/conversations/{conversation}/read
```

Messages are plain text up to 2000 characters. Sending is rate limited to 60
requests per minute. `read` marks every unread message from the other
participant and returns `{ "data": { "updated": 3 } }`. The API is polling
based; no websockets are required.

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
