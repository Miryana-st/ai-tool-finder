# AI-Tool-Finder

AI-Tool-Finder is a web application built with Laravel and Next.js, designed to help users discover and work with AI-related tools and resources.

## Tech Stack

### Backend
- PHP 8.3
- Laravel 13
- Laravel Fortify
- Laravel Sanctum (planned, see #3)
- MySQL 8.4

### Frontend
- Next.js (planned, see #5)
- Node.js 22

### Infrastructure
- Docker / Docker Compose
- Nginx
- Mailpit (local email testing)

## Project Structure

```text
AI-Tool-Finder/
├── backend/              # Laravel application
├── frontend/             # Next.js application (created in #5)
├── nginx/                # Nginx configuration
├── docker/
│   └── php/              # PHP-FPM image (used by app and queue)
├── docker-compose.yml
└── README.md
```

## Docker Services

| Service   | Container                | Purpose                                      |
|-----------|--------------------------|----------------------------------------------|
| `app`     | `ai-tool-finder-app`     | PHP-FPM running Laravel                      |
| `nginx`   | `ai-tool-finder-nginx`   | Web server for Laravel                       |
| `db`      | `ai-tool-finder-db`      | MySQL 8.4                                    |
| `queue`   | `ai-tool-finder-queue`   | Laravel queue worker (`queue:work`)          |
| `node`    | `ai-tool-finder-node`    | Next.js dev server (idle until `frontend/` has a `package.json`) |
| `mailpit` | `ai-tool-finder-mailpit` | Catches all outgoing emails                  |

## Getting Started

### Prerequisites
- Docker Desktop
- Git

### First-time setup

```bash
git clone <repository-url>
cd AI-Tool-Finder

# 1. Create the backend environment file
cp backend/.env.example backend/.env

# 2. Build and start the containers
docker compose up -d --build

# 3. Install PHP dependencies
docker compose exec app composer install

# 4. Generate the application key
docker compose exec app php artisan key:generate

# 5. Create the database tables and sample data
docker compose exec app php artisan migrate --seed
```

The `queue` container restarts until step 3 is done; this is expected on the first run.

### Application URLs

| What             | URL                                         |
|------------------|---------------------------------------------|
| Laravel backend  | http://localhost:8000                       |
| Next.js frontend | http://localhost:3000 (after #5)            |
| Mailpit (emails) | http://localhost:8025                       |
| MySQL            | `localhost:3307` (user `ai_finder_user`)    |

### Useful Docker Commands

```bash
docker compose ps                 # running containers
docker compose logs -f            # all logs
docker compose logs -f queue      # queue worker logs
docker compose restart queue      # reload the worker after changing job code
docker compose exec app php artisan test   # run backend tests
docker compose down               # stop containers (database data is kept)
```

## Development Status

- ✅ Docker environment (app, nginx, MySQL, queue, Node, Mailpit)
- ✅ Laravel with Fortify login
- 🚧 User roles, Sanctum SPA authentication, dashboard, Next.js frontend — see the [GitHub issues](https://github.com/Miryana-st/AI-Tool-Finder/issues)
