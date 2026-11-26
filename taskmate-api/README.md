# TaskMate API

The Laravel REST API behind TaskMate, a task marketplace where people post
tasks, build teams, and collaborate on real projects. The Day 7 backend MVP adds
database notifications, password reset, private CV storage configuration,
health checks, hardened authorization/mass assignment, and demo seed data.

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

### Password reset

```http
POST /api/v1/auth/password/forgot
{ "email": "user@example.com" }

POST /api/v1/auth/password/reset
{
  "email": "user@example.com",
  "token": "reset-token-from-email",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

The forgot endpoint always returns the same message whether or not the account
exists. Reset emails link to
`{FRONTEND_URL}/reset-password?token={token}&email={email}`; the frontend must
post the token back to the reset endpoint. Successful resets invalidate the old
token. Both password endpoints, along with login and register, are limited to
10 requests per minute.

## Auth & profile endpoints

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/password/forgot
POST   /api/v1/auth/password/reset
POST   /api/v1/auth/logout              # Bearer token required
GET    /api/v1/auth/me                  # Bearer token required
POST   /api/v1/auth/email/verify
POST   /api/v1/auth/email/resend        # Bearer token required
GET    /api/v1/health                   # Public, no database access

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
GET    /api/v1/users/{user}/reviews     # Bearer token required
GET    /api/v1/users/{user}/cv          # Bearer token required

GET    /api/v1/tasks                    # Public (optional Bearer token)
GET    /api/v1/tasks/{task}             # Public (optional Bearer token)
GET    /api/v1/tasks/{task}/team        # Public (optional Bearer token)
POST   /api/v1/tasks                    # Bearer token required
PUT    /api/v1/tasks/{task}             # Bearer token required
PATCH  /api/v1/tasks/{task}/start       # Bearer token required (owner only)
PATCH  /api/v1/tasks/{task}/complete    # Bearer token required (owner only)
PATCH  /api/v1/tasks/{task}/cancel      # Bearer token required
GET    /api/v1/tasks/{task}/reviews/pending  # Bearer token required
POST   /api/v1/tasks/{task}/reviews     # Bearer token required
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

GET    /api/v1/notifications             # Bearer token required
GET    /api/v1/notifications/unread-count # Bearer token required
POST   /api/v1/notifications/{id}/read   # Bearer token required
POST   /api/v1/notifications/read-all    # Bearer token required
```

The three public task-browsing routes accept a Bearer token when present.
Authenticated viewers receive owner and application flags on the same payload;
guests receive sanitized data with no uploader email, CV paths, points
balances, applicant details, or application data. These routes are limited to
60 requests per minute per IP.

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

## Team, leaving, and starting a project

```
GET   /api/v1/tasks/{task}/team
PATCH /api/v1/applications/{application}/leave
PATCH /api/v1/tasks/{task}/start
```

Business rules:

- `GET /tasks/{task}/team` lists accepted members only, ordered by acceptance
  time, and returns `{ data, meta }` where `meta` is
  `{ team_size, accepted_count, open_slots }`.
- Accepted members can leave while the task is `open` or `full`. Leaving sets
  `left_at`, keeps the application row, and reopens the task (`full` → `open`)
  so the owner can accept another applicant.
- A user who left cannot re-apply to the same task (`409`,
  `You left this team and cannot re-apply.`).
- Once a project starts, accepted members cannot leave (`409`,
  `The project has already started, you cannot leave`).
- Only the owner can start a project, and only while the task is `open` or
  `full` with at least one accepted member (`422`,
  `Accept at least one member before starting`).
- Starting sets `status` to `in_progress` and `started_at`, rejects every
  pending application, and locks the team: applying, accepting, leaving, and
  editing all return `409`.
- Tasks expose `started_at`, `can_start` (owner, `open`/`full`, at least one
  accepted member), and `can_leave` (accepted viewer, `open`/`full`).

## Completion and reviews

The task owner can complete an `in_progress` project:

```http
PATCH /api/v1/tasks/{task}/complete
```

For a points task, each accepted member receives `reward` points and any
remaining escrow is refunded to the owner. Paid tasks complete without ledger
rows. A task that is not `in_progress` returns `409`.

Cancelling an `in_progress` project refunds the full escrow to the owner and
pays members nothing. A completed task cannot be cancelled (`409`).

Reviews are bidirectional between the uploader and accepted members, and only
participants of a completed task may review each other:

```http
GET  /api/v1/tasks/{task}/reviews/pending
POST /api/v1/tasks/{task}/reviews
     { "reviewee_id": 3, "rating": 5, "comment": "Great work" }
GET  /api/v1/users/{user}/reviews?page=1
```

Rules:

- Reviewers and reviewees must be participants; self-reviews return `422`.
- One review per task/reviewer/reviewee pair; duplicates return `409`.
- Rating is 1–5; comment is optional, trimmed, and at most 1000 characters.
- Pending reviews are the remaining participants the viewer has not reviewed.
- User reviews are paginated 10 per page, newest first, with
  `meta.average_rating`, `meta.reviews_count`, and `meta.distribution` (1–5).
- Task payloads include `completed_at`, `can_complete`, `is_participant`, and
  `pending_reviews_count`. Nested uploader, applicant, and team-member payloads
  include `average_rating` and `reviews_count`.

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
- Completing a points task releases escrow to every accepted member and refunds
  any leftover escrow to the uploader.
- Cancelling an in-progress points task refunds the full escrow to the uploader;
  accepted members receive nothing.
- Completing a paid task creates no point transactions.

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

## Database notifications

Notifications are stored in the `notifications` table and delivered through the
database channel only. Notification jobs use `ShouldQueueAfterCommit`, so no row
is written if the surrounding transaction rolls back. The actor who caused an
event is excluded from the recipient list, and direct messages never generate
notifications.

Notifications are created for these events:

- `application_received` — task owner, when someone applies.
- `application_accepted` / `application_rejected` — applicant, when the owner
  accepts or rejects.
- `member_left` — task owner, when an accepted member leaves.
- `project_started` / `project_completed` — every accepted team member.
- `project_cancelled` — every accepted team member when an in-progress project
  is cancelled.
- `review_received` — reviewee, when a peer review is submitted.

Team-wide events use one bulk insert job instead of one query per user.

```http
GET /api/v1/notifications?filter[unread]=1&page=1
GET /api/v1/notifications/unread-count
POST /api/v1/notifications/{id}/read
POST /api/v1/notifications/read-all
```

The list returns 15 notifications per page, newest first:

```json
{
  "data": [
    {
      "id": "uuid",
      "type": "project_started",
      "title": "Project started",
      "body": "\"Task title\" is now in progress.",
      "data": { "task_id": 12 },
      "read_at": null,
      "created_at": "2026-11-26T10:00:00.000000Z"
    }
  ],
  "links": {},
  "meta": { "total": 1 }
}
```

`unread-count` returns `{ "count": 2 }`. Marking one notification returns the
updated row under `data`; `read-all` returns `{ "data": { "updated": 2 } }`.
A user can only read their own notifications (`404` otherwise).

## Errors, health and rate limits

All API errors use a consistent JSON envelope:

```json
{ "message": "The given data was invalid.", "errors": { "email": ["..."] } }
```

Validation failures add `errors`; other errors contain only `message`. This
covers `401`, `403`, `404`, `405`, `409`, `419` (CSRF token mismatch), `422`,
and `429`. With `APP_DEBUG=false`, exception traces are never returned.

Current rate limits:

| Endpoints | Limit |
| --- | --- |
| Public task list, detail and team | 60/minute per IP |
| Login, register, password forgot/reset | 10/minute |
| Email verification / resend | 6/minute, 3/minute |
| Apply to a task | 30/minute |
| Send a message | 60/minute |
| CV upload | 20/minute |

`GET /api/v1/health` is public, returns `{ "status": "ok" }`, and performs no
database query. It is suitable for container and load balancer health checks.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

In a second terminal, run the queue worker so notifications are persisted:

```bash
php artisan queue:work
```

`migrate:fresh --seed` is re-runnable and creates the demo accounts below. All
three use the password `password`:

| Email | Purpose |
| --- | --- |
| `demo@taskmate.test` | Main demo user with tasks, applications, messages, reviews and notifications |
| `alice@taskmate.test` | Secondary collaborator account |
| `bob@taskmate.test` | Secondary collaborator account |

Configure the database, queue, mail (verification codes and password resets),
frontend URL, private CV disk, and OAuth credentials in `.env`:

```env
APP_URL=http://localhost:8000
# Comma-separated origins are allowed for local multi-frontend setups.
FRONTEND_URL=http://localhost:5173

QUEUE_CONNECTION=database
# Must be a private, persistent disk. The public disk is rejected.
CV_DISK=local

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
./vendor/bin/pest
php artisan route:list --path=api
```

The suite covers notification triggers and rollback behavior, notification
endpoints, the API-wide auth audit, privacy of nested user payloads, mass
assignment hardening, error envelopes, password reset, seed invariants, and one
complete registration-to-review journey. Tests use SQLite in memory with a
synchronous queue, so no worker or external service is needed for them.

Seed data and deployment notes are in [DEPLOY.md](DEPLOY.md).
