# AI-Tool-Finder

AI-Tool-Finder is a role-based web application for discovering and managing AI tools.
It has a Laravel API backend and a separate Next.js frontend, both run with Docker.

## Contents

- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Running with Docker](#running-with-docker)
- [Adding Tools](#adding-tools)
- [Roles and Permissions](#roles-and-permissions)
- [Development Status](#development-status)

## Tech Stack

### Backend
- PHP 8.3
- Laravel 13
- Laravel Fortify
- Laravel Sanctum (SPA cookie authentication)
- MySQL 8.4

### Frontend
- Next.js 16 (App Router, TypeScript)
- Tailwind CSS 4
- Node.js 22

### Infrastructure
- Docker / Docker Compose
- Nginx
- Redis (cache)

## Project Structure

```text
AI-Tool-Finder/
├── backend/              # Laravel application (API only)
├── frontend/             # Next.js application
├── nginx/                # Nginx configuration
├── docker/
│   └── php/              # PHP-FPM image (used by app and queue)
├── docker-compose.yml
└── README.md
```

## Installation

### Prerequisites
- Docker Desktop
- Git
- Port `3000` free on your machine

### First-time setup

```bash
git clone <repository-url>
cd AI-Tool-Finder

# 1. Create the environment files
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local

# 2. Build and start the containers
docker compose up -d --build

# 3. Install PHP dependencies
docker compose exec app composer install

# 4. Generate the application key
docker compose exec app php artisan key:generate

# 5. Create the database tables and sample data
docker compose exec app php artisan migrate --seed

# 6. Make uploaded files (tool screenshots) public at /storage/*
docker compose exec app php artisan storage:link
```

The `queue` container restarts until step 3 is done; this is expected on the first run.
The `node` container runs `npm install` by itself, so no manual frontend install is needed.

Open http://localhost:3000 and sign in with one of the sample accounts.

### Sample accounts

`migrate --seed` creates one user per company role. The password for all of them is `password`.

| Email                     | Company role | System role |
|---------------------------|--------------|-------------|
| `owner@ai-finder.test`    | owner        | **admin**   |
| `backend@ai-finder.test`  | backend      | user        |
| `frontend@ai-finder.test` | frontend     | user        |
| `pm@ai-finder.test`       | pm           | user        |
| `qa@ai-finder.test`       | qa           | user        |
| `designer@ai-finder.test` | designer     | user        |

There is no public registration: new accounts are created by an admin
(**Потребители** → `/admin/users/new`).

### Updating an existing setup

- Rebuild the PHP image once (`docker compose up -d --build`) to get the upload
  limits from `docker/php/uploads.ini`, then run step 6.
- To switch to the Redis cache: set `CACHE_STORE=redis` and `REDIS_HOST=redis`
  in `backend/.env`, run `docker compose up -d --build` (adds the `redis`
  service and the PHP `redis` extension), then `docker compose restart nginx`
  (a rebuilt `app` container gets a new address, and nginx answers 502 until
  it is restarted).
- After pulling new code, run `docker compose exec app php artisan migrate`.

## Running with Docker

### Starting and stopping

```bash
docker compose up -d        # start all containers
docker compose down         # stop them (database data is kept in a volume)
```

### Docker services

| Service   | Container                | Purpose                                      |
|-----------|--------------------------|----------------------------------------------|
| `app`     | `ai-tool-finder-app`     | PHP-FPM running Laravel                      |
| `nginx`   | `ai-tool-finder-nginx`   | Single entry point: routes to Laravel and Next.js |
| `db`      | `ai-tool-finder-db`      | MySQL 8.4                                    |
| `queue`   | `ai-tool-finder-queue`   | Laravel queue worker (`queue:work`)          |
| `node`    | `ai-tool-finder-node`    | Next.js dev server (`npm install` + `npm run dev`) |
| `redis`   | `ai-tool-finder-redis`   | Cache store (`CACHE_STORE=redis`); sessions and the queue stay in MySQL |

### Application URLs

| What             | URL                                         |
|------------------|---------------------------------------------|
| Application      | http://localhost:3000                       |
| MySQL            | `localhost:3307` (user `ai_finder_user`, this machine only) |

nginx is the only public entry point. It sends `/api/*`, `/sanctum/*` and `/up`
to Laravel (JSON only, no pages), serves uploaded files from `/storage/*`, and
sends everything else to the Next.js dev server.
Laravel and Next.js do not publish their own ports, so the frontend and the API
share one origin (no CORS, first-party session cookies).

### Useful commands

```bash
docker compose ps                          # running containers
docker compose logs -f                     # all logs
docker compose logs -f node                # Next.js dev server logs
docker compose logs -f queue               # queue worker logs
docker compose restart queue               # reload the worker after changing job code
docker compose exec app php artisan test   # run backend tests
docker compose exec node npm run lint      # lint the frontend
```

## Adding Tools

Any signed-in user can add a tool from **Добави инструмент** (`/tools/new`).

### Tool fields

| Field             | Required | Notes                                                  |
|-------------------|----------|--------------------------------------------------------|
| Name              | yes      | Unique, also among deleted tools                       |
| URL               | yes      | `http` or `https` link to the tool                     |
| Documentation URL | yes      | `http` or `https` link to its documentation            |
| Description       | yes      |                                                        |
| How to use        | yes      |                                                        |
| Examples          | no       |                                                        |
| Company roles     | yes (≥ 1)| The positions the tool is meant for                    |
| Categories        | yes (≥ 1)| Only approved categories can be picked                 |
| Tags              | no       | Only approved tags can be picked                       |
| Screenshots       | no       | PNG, JPG or WebP, up to 4 MB each, added after saving  |

### Approval

- A tool added by an **admin** is approved and visible to everyone right away.
- A tool added by a **user** is *pending*: only its creator and the admins can see
  it until an admin approves or rejects it in **Чакащи инструменти**
  (`/admin/pending-tools`). Rejecting a pending tool deletes it.

### New categories and tags

If a fitting category or tag does not exist, it can be suggested from the tool form.
Suggestions follow the same rule: an admin's is approved immediately, a user's waits
for an admin in **Категории** (`/admin/categories`) / **Тагове** (`/admin/tags`).
A suggestion can be picked
for a tool only after it is approved.

### Editing and deleting

The creator of a tool and the admins can edit or delete it.
Any signed-in user can mark a tool they can see as a favorite (**Любими**, `/favorites`).

### Through the API

The frontend uses these endpoints; all of them need a Sanctum session
(`GET /sanctum/csrf-cookie`, then `POST /api/login`).

| Method   | Endpoint                          | Who                |
|----------|-----------------------------------|--------------------|
| `GET`    | `/api/tools`                      | signed-in users    |
| `POST`   | `/api/tools`                      | signed-in users    |
| `GET`    | `/api/tools/{slug}`               | signed-in users*   |
| `PUT`    | `/api/tools/{id}`                 | creator or admin   |
| `DELETE` | `/api/tools/{id}`                 | creator or admin   |
| `POST`   | `/api/tools/{id}/images`          | creator or admin   |
| `GET`    | `/api/tools/pending`              | admin              |
| `POST`   | `/api/tools/{id}/approve`         | admin              |
| `POST`   | `/api/tools/{id}/reject`          | admin              |

\* A pending tool returns `404` to anybody except its creator and the admins.

## Roles and Permissions

Every user has two separate roles:

| Role             | Values                                                   | What it does |
|------------------|----------------------------------------------------------|--------------|
| **System role**  | `user` (default), `admin`                                | Decides the **rights** in the application. Fixed in code (`App\Enums\User\SystemRole`). |
| **Company role** | `owner`, `backend`, `frontend`, `pm`, `qa`, `designer`, … | The job **position**. Stored as data (`company_roles` table), drives the dashboard menu and which tools are shown for a position. Gives **no rights** — not even `owner`. |

Authorization is enforced on the backend only by the system role: the `role:admin`
middleware (`EnsureUserHasSystemRole`) guards the admin routes, and the policies in
`app/Policies` check the finer rules.

### What each system role can do

| Action                                           | User | Admin |
|--------------------------------------------------|:----:|:-----:|
| Browse approved tools, categories and tags       |  ✅  |  ✅   |
| Add tools (users' tools wait for approval)       |  ✅  |  ✅   |
| Edit / delete **own** tools and their screenshots|  ✅  |  ✅   |
| Edit / delete **other users'** tools             |  ❌  |  ✅   |
| Suggest categories and tags                      |  ✅  |  ✅   |
| Approve / reject tools, categories and tags      |  ❌  |  ✅   |
| Rename / delete categories and tags              |  ❌  |  ✅   |
| Manage favorites, own profile, password and 2FA  |  ✅  |  ✅   |
| Create, edit and delete users                    |  ❌  |  ✅   |
| Change a user's system role                      |  ❌  |  ✅   |
| Reset another user's two-factor authentication   |  ❌  |  ✅   |
| Add, rename and delete company roles             |  ❌  |  ✅   |
| View the activity history                        |  ❌  |  ✅   |

### Safety rules

- The system role cannot be set through mass assignment; only an admin can change it
  (`PATCH /api/users/{id}/system-role`).
- The last admin cannot lose the admin role.
- Admins cannot delete their own account, so at least one admin always remains.
- Admins turn off their own two-factor authentication from the profile (with their
  password), not from the users list.

### Making someone an admin

Sign in as an admin, open **Потребители** (`/admin/users`), pick the user and change the system role
to admin. The seeded `owner@ai-finder.test` account is the first admin.

## Development Status

- ✅ Docker environment (app, nginx, MySQL, queue, Node, Redis)
- ✅ API-only Laravel backend with Fortify auth endpoints under `/api`
- ✅ Sanctum SPA authentication with two-factor authentication
- ✅ System roles and admin-managed company roles
- ✅ Tools with categories, tags, screenshots and admin approval
- ✅ Favorites and admin activity history
- ✅ Redis cache for shared catalog data
- ✅ Single entry point via nginx; MySQL bound to this machine only
