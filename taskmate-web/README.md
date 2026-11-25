# TaskMate Web

TaskMate is a small task marketplace where people team up on real work. This is
the React single-page frontend for the Laravel API in `../taskmate-api`. Users
can publish tasks, apply to join a team, message teammates, settle points tasks,
leave peer reviews, and follow progress through in-app notifications.

## User flow

1. **Register or log in.** New accounts receive 100 starter points.
2. **Build a profile.** Add a bio, skills, country, profile links, and a private
   CV; verify your email address.
3. **Post a task.** Choose points or paid, set the team size, reward, deadline,
   and skills.
4. **Find teammates.** Browse or filter tasks, then apply with an optional
   message.
5. **Review applicants.** The uploader checks profiles and CVs, messages
   applicants, accepts or rejects them, and the task closes when the team is
   full.
6. **Run the project.** The uploader starts work (points are escrowed), then
   completes it (points are released) or cancels in progress (escrow is
   refunded). Everyone gets a notification.
7. **Review the team.** Participants rate each other; ratings and comments
   appear on public profiles.

## Tech stack

- Vite + React 19 + TypeScript
- React Router with lazy-loaded route pages
- Zustand for persisted auth and feature stores
- Axios with a Sanctum Bearer-token interceptor
- React Hook Form + Zod
- Tailwind CSS v4 + shadcn/ui (Radix primitives)
- Sonner (toasts), Lucide (icons), date-fns (dates)
- Inter via `@fontsource/inter`

The flat Indigo/Slate palette is defined in `src/index.css`, with light and dark
themes and a visible keyboard focus ring.

## Setup

Prerequisites: Node.js 20+ and the TaskMate API running locally.

```bash
npm install
cp .env.example .env
npm run dev
```

The app runs at `http://localhost:5173`. Start the API from `../taskmate-api`
with `php artisan serve`; CV uploads need PHP limits of at least 8 MB.

### Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `VITE_API_URL` | `http://localhost:8000/api/v1` | Base URL for the Laravel API. |

The backend must allow this origin. It uses `FRONTEND_URL` for both CORS and
links in verification/password-reset emails; see `DEPLOY.md` for production.

## Demo accounts

All seeded accounts use the password `password`:

| Email | Role in the demo data |
| --- | --- |
| `demo@taskmate.test` | Uploader with tasks, applications, and reviews |
| `alice@taskmate.test` | Applicant/teammate |
| `bob@taskmate.test` | Applicant/teammate |

Log in with any of them, or register a new account to receive starter points.

## Features

- **Auth** — login, registration, email verification, social auth callback,
  protected/public-only routes, session-expiry redirect, logout.
- **Notifications** — bell dropdown with unread badge and latest 8 items, a
  paginated `/notifications` page with All/Unread tabs, mark-read actions, and
  20-second unread polling that pauses in hidden tabs.
- **Profiles** — bio, skills, country, links, private CV upload/download, public
  profile pages, rating summaries, and review lists.
- **Tasks** — browsable/filterable task list, create/edit forms, detail page,
  applicant review, team slots, start/complete/cancel flows, and escrow hints.
- **Messaging** — task-scoped conversations with unread counts, older-history
  loading, polling, optimistic send, and read receipts.
- **Points** — balance badge, wallet page, escrow/release transactions, and
  starter bonus.
- **Reviews** — star input for teammates and 1–5 rating breakdowns on profiles.

## Routes

| Route | Access | Page |
| --- | --- | --- |
| `/login` | Public | Log in |
| `/register` | Public | Sign up |
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
| `/notifications` | Protected | Notification inbox |
| `/messages` | Protected | Conversation list |
| `/messages/:conversationId` | Protected | Conversation thread |
| `/points` | Protected | Points wallet |
| `/profile` | Protected | Own profile |
| `/users/:id` | Protected | Public profile (`#reviews` anchors reviews) |

Unknown routes render a real 404 page. Unexpected render errors are caught by a
global error boundary with a reload action.

## Project structure

```text
src/
  api/            axios client and typed endpoint wrappers
  components/
    applications/ application dialogs, cards, rows, badges
    chat/         conversation list, thread, bubbles, input
    layout/       app shell, auth shell, footer
    notifications/ bell dropdown and notification row
    points/       balance badge and transaction row
    profile/      profile cards, link editor, public profile view
    reviews/      star input, review cards, ratings, pending reviews
    tasks/        cards, filters, forms, badges, pagination, dialogs
    ui/           shadcn/ui components
  hooks/          useDebounce, usePolling, usePageTitle
  lib/            formatting, notification navigation, utilities
  pages/          lazy-loaded route pages
  stores/         Zustand auth, task, application, chat, points, notifications
  types/          shared TypeScript types
```

## Verification

```bash
npm run build    # TypeScript check + production build
npm run preview  # serve the production build locally
npm run lint     # oxlint
```

`npm run build` also confirms every page can be code-split and loaded lazily.
See `DEPLOY.md` for static-hosting and backend-origin notes.

## Known limitations

- Notifications and messages use polling, not WebSockets.
- Paid tasks are tracked in TaskMate, but payments are processed outside the app.
- Notifications are in-app only; no email notification delivery is configured.
- CVs are private files fetched through authenticated API endpoints, so direct
  public URLs are not available.
