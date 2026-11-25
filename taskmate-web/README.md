# TaskMate Web

The React frontend for TaskMate — a task marketplace where people find teammates
for real work. Day 1 covers authentication and public/private profiles; Day 2
adds task browsing, creation, editing, and owner management.

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
  api/            axios client and endpoint wrappers
  components/
    applications/ application dialog, applicant card, row, status badge
    layout/       app shell and auth shell
    profile/      profile cards, link editor, public profile view
    tasks/        task cards, filters, form, badges, pagination
    ui/           shadcn/ui components
  hooks/          useDebounce
  lib/            utilities, formatting, and platform detection
  pages/          route pages
  stores/         Zustand auth, task, and application stores
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
- The Sanctum personal access token is stored under the `taskmate-auth` key in
  `localStorage` and sent as `Authorization: Bearer <token>` by the Axios client.
