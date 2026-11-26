# TaskMate

> A task marketplace where people post tasks, build teams, and collaborate on real projects, with applications, team slots, and mutual reviews built in.

TaskMate connects **task uploaders** (people with a project or task that needs help) with **applicants** (people who want to contribute). Uploaders review applicants' profiles, CVs, and links, accept or reject them, and fill a fixed number of team slots. When the work is done, both sides review each other.

---

## Table of Contents

- [Features](#features)
- [How It Works](#how-it-works)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Backend (API)](#backend-api)
- [Frontend](#frontend)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [Running the Project](#running-the-project)
- [Testing](#testing)
- [Design Guidelines](#design-guidelines)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features

- **Sanctum API tokens**: registration and login issue a Laravel Sanctum personal access token used as a Bearer token for protected requests
- **Email verification**: new email/password accounts receive a 6-character mixed verification code (for example `45XC78`) plus a verification link
- **Social authentication**: sign in or register with **Google** or **GitHub** via Laravel Socialite
- **Task marketplace**: post, browse, search, filter, and sort tasks; filter by type, skill, reward, or open slots
- **Unlimited applications**: users can apply to as many tasks as they like
- **Accept / reject workflow**: uploaders review each applicant's profile and decide
- **Team slots**: each task has a defined team size; once the team is full, applications close
- **Dynamic slots**: if a member leaves, the slot reopens and accepting continues until the team is full again
- **Paid or points exchange**: tasks can offer payment or a points-based exchange
- **Direct messaging**: applicants can DM the task uploader
- **Rich profiles**: upload a CV and attach any number of links (GitHub, Portfolio, Behance, etc.)
- **Mutual reviews**: when a project finishes, uploader and members review each other

---

## How It Works

```
Find task
   │
   ▼
Apply to task  (unlimited applications)
   │
   ▼
Uploader reviews profile / interview ──► Reject  (application closed)
   │
   ▼ Accept
Is the team full?
   ├── Yes ──► Stop accepting applicants
   └── No  ──► Continue accepting
                  │
                  ▼
        Member leaves? ──► Slot reopens ──► Accepting resumes until full
                  │
                  ▼
          Project finishes
                  │
                  ▼
     Uploader and members review each other
```

---

## Tech Stack

| Layer      | Technology                                   |
| ---------- | -------------------------------------------- |
| Backend    | Laravel (PHP), REST API                      |
| Auth       | Laravel Sanctum API tokens, email verification, Laravel Socialite (Google & GitHub) |
| Database   | MySQL                                        |
| Frontend   | React                                        |
| State      | Zustand                                      |
| UI Library | shadcn/ui, Tailwind CSS                      |

---

## Project Structure

```
TaskMate/
├── taskmate-api/    # Laravel backend (REST API)
├── taskmate-web/    # React frontend
└── README.md        # You are here
```

---

## Backend (API)

The `taskmate-api/` folder contains a Laravel application that exposes a JSON REST API consumed by the React frontend.

### Responsibilities

- Authentication and authorization with Laravel Sanctum API tokens
- Email verification for new accounts
- Social authentication (OAuth) with **Google** and **GitHub** through Laravel Socialite
- User profiles, CV upload, and profile links
- Task creation, listing, search/filter/sort, update, cancellation, and deletion
- Applications and the accept / reject workflow
- Team slot management (full / open logic, member leave handling)
- Direct messaging between applicants and task uploaders
- Paid / points-exchange task options
- Reviews after project completion

### Core Entities

| Entity        | Description                                                             |
| ------------- | ----------------------------------------------------------------------- |
| User          | Account, profile details, CV, and links                                 |
| ProfileLink   | Any number of links per user (GitHub, Portfolio, Behance, etc.)         |
| Task          | Posted work with description, team size, and paid / points option       |
| Application   | A user's application to a task, with status (pending/accepted/rejected) |
| TeamMember    | An accepted user occupying a slot on a task's team                      |
| Message       | Direct messages between users                                           |
| Review        | Mutual reviews between uploader and members after completion            |

### Authentication

TaskMate uses **Laravel Sanctum** personal access tokens for API authentication.

- **Token auth**: `POST /api/v1/auth/register` and `POST /api/v1/auth/login` return a Sanctum personal access token. The React client stores it and sends it as `Authorization: Bearer <token>` on every protected request. `POST /api/v1/auth/logout` revokes the current token, and `GET /api/v1/auth/me` returns the authenticated user.
- **Email verification**: registration sends a **6-character mixed letter/digit code** (for example `45XC78`) to the user's email, along with a verification link. Users can verify from the link or enter the code on the verification page, and can request a fresh code while signed in. Codes expire after 15 minutes.
- **Social auth**: Laravel Socialite handles the OAuth redirect and callback for **Google** and **GitHub**. Accounts are matched to an existing user by email, or a new account is created and marked as verified. The callback redirects to the frontend with a Sanctum API token.

### Key Business Rules

1. New email/password accounts are prompted to verify their email with a 6-character code; social sign-ins are verified automatically.
2. A user can apply to an unlimited number of tasks.
3. Only the task uploader can accept or reject applications.
4. Applications are only accepted while the team has open slots.
5. When the team is full, accepting stops automatically.
6. When a member leaves, a slot reopens and accepting can continue.
7. Reviews are available only once the project is finished, and go both ways.

### Example Endpoint Groups

Authentication endpoints (all under `/api/v1`):

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

Protected routes require the Sanctum Bearer token returned by register, login,
or the social auth callback. Task listing accepts spatie query parameters such
as `filter[search]`, `filter[type]`, `filter[skill]`, `filter[min_reward]`,
`filter[max_reward]`, `filter[has_open_slots]`, `sort`, and `page`. Application
listings accept `filter[status]` (`pending`, `accepted`, `rejected`) with
pagination. Points endpoints return the current balance, escrowed total, and a
paginated ledger, while conversations are task-scoped and support polling with
`before_id` / `after_id` message cursors.

---

## Frontend

The `taskmate-web/` folder contains a React single-page application that consumes the API.

### Responsibilities

- Authentication screens and Sanctum Bearer token session handling
- Email verification code entry and resend screen
- Social login with Google and GitHub plus OAuth callback handling
- Task browsing, search, filters, sorting, and pagination
- Task detail, creation, and editing for uploaders
- My Tasks management with status tabs and cancel actions
- Application flow for applicants
- Applicant review screen for uploaders (profile, CV, links)
- Team and slot overview
- Task-scoped direct messages with polling, unread badges, and mobile thread view
- Points wallet, escrow status, and transaction history
- Profile management (CV upload and links)
- Review submission after project completion

### Architecture

- **React** for the component-based UI
- **Zustand** for lightweight global state (auth, tasks, applications, chat, points, etc.)
- **shadcn/ui** components styled with **Tailwind CSS**
- API calls centralized in a service layer (for example, an Axios or fetch wrapper)

---

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- MySQL 8+

### 1. Clone the repository

```bash
git clone <your-repo-url> TaskMate
cd TaskMate
```

### 2. Set up the backend

```bash
cd taskmate-api
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`, then run:

```bash
php artisan migrate --seed
php artisan storage:link
```

### 3. Set up the frontend

```bash
cd ../taskmate-web
npm install
cp .env.example .env
```

---

## Environment Variables

### Backend (`taskmate-api/.env`)

```env
APP_NAME=TaskMate
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskmate
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173

# Mail (used for 6-character email verification codes)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@taskmate.test"
MAIL_FROM_NAME="${APP_NAME}"

# Social auth (Laravel Socialite)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=http://localhost:8000/api/v1/auth/github/callback
```

### Frontend (`taskmate-web/.env`)

```env
VITE_API_URL=http://localhost:8000/api/v1
```

> Adjust variable names to match your setup (for example, `REACT_APP_API_URL` if you are not using Vite).

---

## Running the Project

Start the backend:

```bash
cd taskmate-api
php artisan serve
```

Start the frontend in a second terminal:

```bash
cd taskmate-web
npm run dev
```

| Service  | URL                       |
| -------- | ------------------------- |
| API      | http://localhost:8000     |
| Frontend | http://localhost:5173     |

---

## Testing

Backend tests:

```bash
cd taskmate-api
php artisan test
```

Frontend (if configured):

```bash
cd taskmate-web
npm run test
```

---

## Design Guidelines

- Clean, professional UI with **no gradients**
- Standard **Indigo** palette:

| Role    | Color     |
| ------- | --------- |
| Primary | `#4F46E5` |
| Hover   | `#4338CA` |
| Tint    | `#EEF2FF` |
| Accent  | `#0EA5E9` |

---

## Roadmap

- [x] Email verification
- [x] Sanctum API token authentication
- [x] Social authentication (Google & GitHub)
- [x] Authentication and user profiles
- [x] CV upload and profile links
- [x] Task CRUD and browsing
- [x] Applications and accept / reject flow
- [x] Team slot management
- [ ] Direct messaging
- [x] Paid / points-exchange options
- [ ] Reviews
- [ ] Notifications
- [ ] Deployment

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Author

**Tahmid Alam Tamim**
GitHub: [Mr-Explorer142](https://github.com/Mr-Explorer142) · LinkedIn: [tahmid-alam-tamim142](https://www.linkedin.com/in/tahmid-alam-tamim142)