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
- [AI Agents](#ai-agents)
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
├── .claude/              # Claude Code agents and skills (see AI Agents)
├── backend/              # Laravel application (API only)
├── frontend/             # Next.js application
├── nginx/                # Nginx configuration
├── docker/
│   └── php/              # PHP-FPM image (used by app and queue)
├── docker-compose.yml
├── CLAUDE.md             # Project rules for Claude Code
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

> Open the app via `http://localhost:3000`, not `http://127.0.0.1:3000`.
> The session and CSRF cookies are bound to `localhost`
> (`SESSION_DOMAIN=localhost`, `SANCTUM_STATEFUL_DOMAINS=localhost:3000` in
> `backend/.env`), so on `127.0.0.1` they are not sent and login fails with **419**.

### Useful commands

```bash
docker compose ps                          # running containers
docker compose logs -f                     # all logs
docker compose logs -f node                # Next.js dev server logs
docker compose logs -f queue               # queue worker logs
docker compose restart queue               # reload the worker after changing job code
docker compose exec app php artisan test   # run backend tests
docker compose exec app php artisan test --testsuite=Unit   # only the fast unit tests (no database)
docker compose exec app php artisan test -c phpunit.integration.xml   # integration tests (real MySQL and Redis)
docker compose exec node npm run lint      # lint the frontend
```

### Integration tests

`backend/tests/Integration` runs the API against the real MySQL and Redis
containers instead of in-memory SQLite and the array cache: database constraints,
the collation, sessions stored in MySQL and the catalog cache in Redis. It uses its own
database, `ai_finder_testing`, and Redis database 15. The tests empty both, and they
refuse to run against anything else, so development data is never touched.

A fresh MySQL volume creates the test database from `mysql/init/01-testing-database.sql`.
On an existing volume, create it once:

```bash
docker compose exec -T db mysql -uroot -plocal_root_password < mysql/init/01-testing-database.sql
```

In CI they run in their own job, with MySQL and Redis services, after the unit and feature tests pass.

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
| Examples          | no       | Markdown; **Добави линк** and **Качи скрийншот** insert a link or an uploaded screenshot (see below) |
| Company roles     | yes (≥ 1)| The positions the tool is meant for                    |
| Categories        | yes (≥ 1)| Only approved categories can be picked                 |
| Tags              | no       | Only approved tags can be picked                       |
| Screenshots       | no       | PNG, JPG or WebP, up to 4 MB each, added after saving  |

### Links and screenshots in the examples

Above the examples field, **Добави линк** asks for an address (`http`/`https`) and an
optional text, and **Качи скрийншот** uploads a PNG, JPG or WebP image (up to 4 MB, at
most 30 uploads a minute). Both put the Markdown at the cursor, and **Преглед** shows how
the text will look.

Example screenshots are separate from the tool's own screenshots: they are uploaded
before the tool is saved (`POST /api/example-images`), stored under
`storage/app/public/examples` and referred to by their `/storage/…` URL in the text.
Screenshots no tool refers to (a form that was never saved, an image removed from the
text) are deleted after a day by:

```bash
docker compose exec app php artisan example-images:prune
```

It is scheduled daily in `routes/console.php`, but the Docker setup runs no scheduler,
so for now run it by hand.

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

### Ratings and comments

Approved tools can be rated and commented on by any signed-in user (pending ones cannot):

- **Rating:** 1 to 5 stars, one per user and tool. Rating again replaces it, and it can
  be removed. Tools show the average (one decimal), the number of ratings and your own.
- **Comments:** plain text up to 2000 characters, newest first, 10 per page. The author
  can edit and delete their own comments; an admin can delete any comment, and that is
  recorded in the activity history. A deleted user's comments stay, without an author.

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
| `PUT`    | `/api/tools/{id}/rating`          | signed-in users**  |
| `DELETE` | `/api/tools/{id}/rating`          | signed-in users    |
| `GET`    | `/api/tools/{id}/comments`        | signed-in users*   |
| `POST`   | `/api/tools/{id}/comments`        | signed-in users**  |
| `PUT`    | `/api/tools/{id}/comments/{id}`   | author             |
| `DELETE` | `/api/tools/{id}/comments/{id}`   | author or admin    |

\* A pending tool returns `404` to anybody except its creator and the admins.
\*\* Approved tools only; a pending one returns `403` to its creator and the admins.

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
| Rate and comment on approved tools; edit / delete **own** comments |  ✅  |  ✅   |
| Delete **other users'** comments                 |  ❌  |  ✅   |
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
- Deleting a user keeps the tools they added (shown as added by a deleted user), as well
  as their comments and suggestions.
- Admins turn off their own two-factor authentication from the profile (with their
  password), not from the users list.

### Making someone an admin

Sign in as an admin, open **Потребители** (`/admin/users`), pick the user and change the system role
to admin. The seeded `owner@ai-finder.test` account is the first admin.

## AI Agents

The project is developed with [Claude Code](https://claude.com/claude-code).
Its configuration is in the repository, so every session uses the same rules and helpers.

| Name                   | Type  | File                                    | What it does | Changes code? |
|------------------------|-------|-----------------------------------------|--------------|:-------------:|
| Project rules          | —     | `CLAUDE.md`                             | Stack, Git workflow, coding, database, security and testing rules. Loaded automatically in every session. | — |
| `issue-analyzer`       | skill | `.claude/skills/issue-analyzer/SKILL.md` | Reads a GitHub issue (`gh`) and the related code, and returns an implementation plan in Bulgarian. Works only in Plan Mode. | ❌ |
| `senior-code-reviewer` | agent | `.claude/agents/senior-code-reviewer.md` | Independent review of the local changes or of the current branch against the newer of `main` / `origin/main` (it does not run `git fetch`, so pull `main` first for an accurate scope). Returns a report in Bulgarian with findings by severity (CRITICAL … INFO) and a verdict (APPROVE / REQUEST CHANGES / …). | ❌ |

### How to use them

- **`issue-analyzer`**: type `/issue-analyzer <issue number>`.
- **`senior-code-reviewer`**: ask for a code review, for example "пусни senior-code-reviewer".
  Claude Code may also start it on its own after a feature or fix, before a commit.
  It reads Git and the code, and may run `pint --test` (in the `app` container),
  `tsc --noEmit` and `eslint`. It does not run `php artisan test` or builds, does not
  read `.env` files, and does not change files, branches or commits.
- New or changed agents are picked up after Claude Code is restarted; check with `/agents`.

### Recommended workflow

1. Analyze the issue with `issue-analyzer` and agree on the plan.
2. Create a branch from the latest `main` (`feat/…`, `fix/…`, `docs/…`).
3. Implement the plan and add or update tests.
4. Run the tests: `docker compose exec app php artisan test` (and `docker compose exec node npm run lint` for frontend changes).
5. Run `senior-code-reviewer` and fix the CRITICAL and HIGH findings.
6. Review the diff; commit and push only after approval.

### Starter prompts

Copy a prompt into a new Claude Code session and replace the values in `<…>`.

**1. Analyze an issue**

```text
/issue-analyzer <номер на issue>
```

**2. Start development from an approved plan**

```text
Имплементирай одобрения план за Issue #<номер>.
- Провери git status и създай нов бранч от актуалния main: <feat/кратко-име>.
- Спазвай CLAUDE.md: малки и фокусирани промени, без несвързан рефакторинг.
- Добави или обнови тестове за успешни сценарии, валидационни грешки и edge cases.
- Пусни docker compose exec app php artisan test (и npm run lint при промени във frontend)
  и докладвай реалните резултати.
- Накрая пусни senior-code-reviewer и оправи CRITICAL и HIGH забележките.
- Покажи ми diff-а. Не commit-вай и не push-вай без моето одобрение.
```

**3. Start development without an issue**

```text
Искам да добавя: <кратко описание на функционалността>.
Очаквано поведение: <какво трябва да се случва>.
Засегнати роли: <user / admin / конкретна company role>.
Първо разгледай съществуващия код и ми предложи план. Не пиши код, докато не одобря плана.
След одобрение работи в нов бранч от main и следвай CLAUDE.md.
```

**4. Fix a bug**

```text
Има бъг: <какво се случва>.
Стъпки за възпроизвеждане: <1. … 2. … 3. …>.
Очаквано поведение: <какво трябва да се случва>.
Намери причината, преди да променяш код, и ми я обясни.
Оправи я в нов бранч fix/<кратко-име>, добави тест, който пада без поправката,
и пусни тестовете. Не commit-вай без одобрение.
```

**5. Code review**

```text
Пусни senior-code-reviewer за локалните промени.
```

```text
Пусни senior-code-reviewer за всички промени в текущия бранч спрямо main.
```

**6. Fix the review findings**

```text
Оправи забележките от code review с приоритет <CRITICAL и HIGH / всички>.
За всяка забележка, с която не си съгласен, обясни защо, вместо да я пропускаш.
Пусни тестовете отново и покажи diff-а.
```

**7. Continue earlier work (after `/clear` or in a new session)**

```text
Продължаваме работата по бранча <име на бранча> (Issue #<номер>).
Провери git status, git log спрямо main и diff-а, за да видиш какво е направено.
Обобщи текущото състояние и какво остава, преди да продължиш.
```

## Development Status

- ✅ Docker environment (app, nginx, MySQL, queue, Node, Redis)
- ✅ API-only Laravel backend with Fortify auth endpoints under `/api`
- ✅ Sanctum SPA authentication with two-factor authentication
- ✅ System roles and admin-managed company roles
- ✅ Tools with categories, tags, screenshots and admin approval
- ✅ Favorites and admin activity history
- ✅ Tool ratings and comments
- ✅ Redis cache for shared catalog data
- ✅ Single entry point via nginx; MySQL bound to this machine only
