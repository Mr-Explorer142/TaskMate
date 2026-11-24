# TaskMate Web

The React frontend for TaskMate — a task marketplace where people find teammates
for real work. Day 1 covers authentication and public/private profiles; task
features arrive on Day 2.

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

## Routes

| Route | Access | Page |
| --- | --- | --- |
| `/login` | Public | Login |
| `/register` | Public | Register |
| `/verify-email` | Public | Email verification |
| `/auth/callback` | Public | OAuth callback |
| `/` | Protected | Home |
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
    layout/       app shell and auth shell
    profile/      profile cards, link editor, public profile view
    ui/           shadcn/ui components
  lib/            utilities and platform detection
  pages/          route pages
  stores/         Zustand auth store
  types/          shared TypeScript types
```

## Notes

- CVs live on the API's private disk and are fetched as authenticated blobs; no
  direct file URL is exposed.
- The app supports light and dark themes, following the saved preference or the
  operating system setting.
- The Sanctum personal access token is stored under the `taskmate-auth` key in
  `localStorage` and sent as `Authorization: Bearer <token>` by the Axios client.
