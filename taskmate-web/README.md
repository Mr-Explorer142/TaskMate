# TaskMate Web

The React frontend for TaskMate - a task marketplace where people find teammates
for real work. Day 1 covers authentication and public/private profiles; Day 2
adds task browsing, creation, editing, and owner management. Day 5 adds
task-scoped direct messages and the points wallet. Day 6 adds project
completion, escrow settlement, peer reviews, and rating summaries.

## Stack

- Vite + React 19 + TypeScript
- React Router
- Zustand (persisted auth store)
- Axios (attaches the Sanctum Bearer token to API requests)
- React Hook Form + Zod
- Tailwind CSS v4 + shadcn/ui (Radix)
- Sonner (toasts), Lucide (icons), date-fns
- Inter via `@fontsource/inter`

The UI uses the Indigo + Slate palette defined in `src/index.css` and supports
light and dark themes.

## Prerequisites

- Node.js 20+
- The TaskMate API running at `http://localhost:8000`
- Backend CORS configured for `http://localhost:5173` (already the default)

## Setup

```bash
npm install
cp .env.example .env
npm run dev
```

The app runs at `http://localhost:5173`.

### Environment

| Variable | Default | Description |
| --- | --- | --- |
| `VITE_API_URL` | `http://localhost:8000/api/v1` | Base URL of the Laravel API |

### API server

From the backend directory:

```bash
php -d post_max_size=10M -d upload_max_filesize=8M artisan serve
```

The upload flags keep CV uploads (5 MB limit) within PHP limits.

## Demo account

```
Email: demo@taskmate.test
Password: password
```

The seeded database also contains other members to visit via
`/users/:id`.

## Features

- **Auth** — login and register with client and server validation, a persisted
  Laravel Sanctum Bearer token, session refresh via `/auth/me`, protected routes,
  redirects for signed-in users on auth pages, logout.
- **Social auth** — sign in or register with Google or GitHub through the API's
  Socialite OAuth flow, with the token picked up from `/auth/callback`.
- **Email verification** — enter the 6-character mixed code from the
  verification email (or follow the email link), resend a fresh code, and see a
  reminder banner until the email is verified.
- **Profile** — name, bio, up to 30 skills, CV upload with progress and
  client-side type/size checks, authenticated blob download, replace and delete
  with confirmation.
- **Profile links** — add, edit, delete, reorder, platform auto-detection, and a
  live detection preview. Invalid and duplicate URLs surface inline errors.
- **Public profile** — read-only view at `/users/:id`, showing skills, links,
  rating, and a CV download only when one exists. Email is never exposed.
- **Tasks** — browse the task grid with debounced search, type/skill/reward
  filters, an open-slots switch, and sorting. Every filter, sort, and page is
  reflected in the URL, so refreshes and back/forward navigation keep state.
  Loading skeletons, empty states, and retryable errors are included.
- **Task detail** - badges, full description, skills, reward, deadline, team
  slot progress, and the uploader's card with rating. Owners can review
  applicants, edit, cancel, or delete a task (with validation errors surfaced
  in toasts). Non-owners can apply while the task is open (optional message up
  to 1000 characters), withdraw a pending application, and see their status:
  pending, accepted ("You are on this team"), or rejected ("Not selected").
  Closed, full, and cancelled tasks show a disabled action instead.
- **Task forms** - create and edit share a React Hook Form + Zod form mirroring
  the API rules: paid vs points reward rules, description counter, date
  minimums, up to 15 skills, unsaved-changes warning, field-level 422 errors,
  and type locking once a task has applications.
- **My Tasks** - status tabs (all, open, full, in progress, completed,
  cancelled) backed by the URL, pending-applicant counts, an Applicants quick
  action, and per-card view/edit/cancel actions.
- **Applicants** - an owner-only review page at `/tasks/:id/applicants` with
  pending/accepted/rejected/all tabs backed by the URL, team slot progress,
  applicant profiles, skills, links, CV download, and accept/reject actions with
  confirmation. Accepting is disabled with a "Team is full" hint once full, and
  API conflicts (409) refresh the task and show the server message.
- **My Applications** - track every application at `/my-applications` with
  all/pending/accepted/rejected tabs, task type, reward, status, applied date,
  and withdrawal of pending applications.
- **Direct messages** - task-scoped conversations at `/messages` and
  `/messages/:conversationId`. Desktop shows the conversation list beside the
  thread; mobile shows the list, then the thread full screen with a back
  button. Items show the other user, task, last message, relative time, and an
  unread badge. Threads load older history with `before_id`, poll for new
  messages with `after_id` every 4 seconds, mark messages read on open and on
  arrival, and keep a plain-text, `pre-wrap` body. Messages are sent with Enter
  (Shift+Enter for a newline), a 2000-character counter, and optimistic append
  with rollback plus an error toast on failure. Entry points: "Message
  uploader" on a task, "Message" on each applicant card, and the navbar
  Messages link with an unread badge.
- **Points** - a navbar `PointsBadge` and a `/points` page with balance, in
  escrow, and a paginated transaction list. Bonus, escrow, release, and refund
  rows use distinct badges; signed amounts are colored by direction, with task
  links and dates. New users see "You received 100 starter points" after
  registering. Points task forms and details show the escrow hint, and the
  start dialog shows required versus available points, blocks a start when the
  balance is too low, links to `/points`, and refreshes the balance after a
  successful start.

- **Completion and reviews** - owners can complete an in-progress project; the
  dialog explains points released per member, the total escrow, or that payment
  is handled outside TaskMate. It also offers a secondary Cancel project action
  with a stronger escrow-refund warning. Completed tasks show a green completion
  banner and hide apply, leave, edit, start, and cancel actions. Participants
  review the rest of the team with a keyboard-accessible 1–5 star input and an
  optional comment; the pending list shrinks after each submission and 409/422
  errors appear as toasts. My Tasks and My Applications link to unfinished
  reviews, and the points balance refreshes after settlement.
- **Ratings** - profiles show an average, review count, and a flat distribution
  summary, followed by paginated review cards with reviewer, stars, comment,
  task link, and date. Task cards, applicant cards, team rosters, and task
  detail show `4.6 (12)` style ratings or `New` when there are no reviews.

## Day 6 API endpoints

All endpoints use `Authorization: Bearer <token>` and the `/api/v1` prefix.

```text
PATCH /tasks/{task}/complete
GET  /tasks/{task}/reviews/pending
POST /tasks/{task}/reviews          { "reviewee_id": 3, "rating": 5, "comment": "Great work" }
GET  /users/{user}/reviews?page=1
```

Completing a points task releases escrow to every accepted member and refunds
any remainder; paid tasks create no point transactions. Cancelling an
in-progress points task refunds the full escrow to the owner and pays members
nothing. No new frontend routes were added: these flows live on task detail,
My Tasks, My Applications, and profiles.

## Routes

| Route | Access | Page |
| --- | --- | --- |
| `/login` | Public | Login |
| `/register` | Public | Register |
| `/verify-email` | Public | Email verification |
| `/auth/callback` | Public | OAuth callback |
| `/` | Protected | Redirects to `/tasks` |
| `/tasks` | Protected | Browse tasks |
| `/tasks/new` | Protected | Post a task |
| `/tasks/:id` | Protected | Task detail |
| `/tasks/:id/edit` | Protected | Edit an owned task |
| `/tasks/:id/applicants` | Protected (owner) | Review applicants |
| `/my-tasks` | Protected | Tasks you posted |
| `/my-applications` | Protected | Applications you sent |
| `/messages` | Protected | Conversation list |
| `/messages/:conversationId` | Protected | Conversation thread |
| `/points` | Protected | Points wallet and transactions |
| `/profile` | Protected | Own profile |
| `/users/:id` | Protected | Public profile |

## Scripts

```bash
npm run dev      # Vite dev server
npm run build    # Type check + production build
npm run preview  # Preview the production build
npm run lint     # oxlint
```

## Project structure

```
src/
  api/            axios client and endpoint wrappers (auth, tasks, messages, points, reviews)
  components/
    applications/ application dialog, applicant card, row, status badge
    chat/         conversation list/item, thread, bubbles, input, message button
    layout/       app shell and auth shell
    points/       balance badge and transaction row
    profile/      profile cards, link editor, public profile view
    reviews/      star rating/input, review dialog/card, rating summary, pending reviews
    tasks/        task cards, filters, form, badges, pagination, completion dialog
    ui/           shadcn/ui components
  hooks/          useDebounce, usePolling
  lib/            utilities, formatting, and platform detection
  pages/          route pages (including Messages and Points)
  stores/         Zustand auth, task, application, chat, and points stores
  types/          shared TypeScript types
```

## Notes

- Task list filters, sorting, and pagination live in the URL query string
  (`search`, `type`, `skill`, `min_reward`, `max_reward`, `has_open_slots`,
  `sort`, `page`); My Tasks, My Applications, and the applicants page use
  `status`/`page`. The task and application stores abort stale list requests so
  rapid tab or filter changes cannot overwrite newer results.
- CVs live on the API's private disk and are fetched as authenticated blobs; no
  direct file URL is exposed.
- The app supports light and dark themes, following the saved preference or the
  operating system setting.
- Messaging is polling based. `usePolling` pauses when the tab is hidden, never
  overlaps requests, and cleans up on unmount: the active thread polls every 4
  seconds, the conversation list every 10 seconds, and the navbar unread badge
  every 15 seconds. Message bodies render as plain text with `white-space:
  pre-wrap`; HTML is never injected.
- The points store also syncs `points_balance` back into the persisted auth
  user so the navbar and register flow stay current.
- The Sanctum personal access token is stored under the `taskmate-auth` key in
  `localStorage` and sent as `Authorization: Bearer <token>` by the Axios client.
