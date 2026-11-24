
# AI-Tool-Finder

AI-Tool-Finder is a web application built with Laravel and Next.js, designed to help users discover and work with AI-related tools and resources.

## Tech Stack

### Backend
- PHP 8.3
- Laravel 13
- Laravel Fortify
- Laravel Sanctum
- MySQL 8.4

### Frontend
- Next.js
- Node.js 22

### Infrastructure
- Docker
- Docker Compose
- Nginx

## Project Structure

```text
AI-Tool-Finder/
├── backend/              # Laravel application
├── frontend/             # Next.js application
├── nginx/                # Nginx configuration
├── docker/               # Docker configurations
│   └── php/
├── mysql/
│   └── init/             # Database initialization scripts
├── tools/                # Development utilities
├── docker-compose.yml
└── README.md
```

## Getting Started

### Prerequisites
- Docker Desktop
- Git

### Start the application

Clone the repository:

```bash
git clone <repository-url>
cd AI-Tool-Finder
```

Build and start Docker containers:

```bash
docker compose up -d --build
```

### Application URLs

- Laravel Backend: http://localhost:8000
- MySQL: localhost:3307

### Useful Docker Commands

Check running containers:

```bash
docker compose ps
```

View logs:

```bash
docker compose logs -f
```

Stop containers:

```bash
docker compose down
```

## Development Status

Project setup and Docker infrastructure are completed.

Authentication, role management, dashboard functionality, and Next.js frontend are under development.
