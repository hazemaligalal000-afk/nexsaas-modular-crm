# NexSaaS Application - Local Startup Guide

## Prerequisites

1. **Docker & Docker Compose** installed
2. **Docker permissions** - Run: `sudo usermod -aG docker $USER` then logout/login
3. **Ports available**: 80, 5432, 6379, 5672, 8000, 8080, 15672

## Quick Start

### Option 1: Using the startup script (Recommended)

```bash
# Make script executable
chmod +x start.sh

# Start all services
./start.sh
```

### Option 2: Manual Docker Compose

```bash
# Add your user to docker group (if not done)
sudo usermod -aG docker $USER
newgrp docker

# Start all services
docker compose up -d

# View logs
docker compose logs -f

# Check service status
docker compose ps
```

## Services & Ports

| Service | Port | URL | Description |
|---------|------|-----|-------------|
| Frontend | 80 | http://localhost | React 18 UI |
| Backend API | 8080 | http://localhost:8080 | PHP REST API |
| FastAPI (AI) | 8000 | http://localhost:8000 | AI Engine |
| PostgreSQL | 5432 | localhost:5432 | Database |
| Redis | 6379 | localhost:6379 | Cache & Sessions |
| RabbitMQ | 5672 | localhost:5672 | Message Queue |
| RabbitMQ UI | 15672 | http://localhost:15672 | Management UI |

## Default Credentials

### RabbitMQ Management UI
- URL: http://localhost:15672
- Username: `nexsaas`
- Password: `secret`

### PostgreSQL
- Host: `localhost`
- Port: `5432`
- Database: `nexsaas`
- Username: `nexsaas`
- Password: `secret`

### Redis
- Host: `localhost`
- Port: `6379`
- Password: `secret`

## Startup Sequence

1. **PostgreSQL** starts first (with health check)
2. **Redis** starts (with health check)
3. **RabbitMQ** starts (with health check)
4. **PHP-FPM** starts (depends on DB, Redis, RabbitMQ)
5. **Nginx Backend** starts (serves PHP API)
6. **FastAPI** starts (AI Engine)
7. **Celery Worker** starts (background jobs)
8. **React Frontend** starts (UI)

## Verify Services

### Check all containers are running
```bash
docker compose ps
```

Expected output: All services should show "Up" status

### Check logs for errors
```bash
# All services
docker compose logs

# Specific service
docker compose logs php-fpm
docker compose logs fastapi
docker compose logs celery-worker
docker compose logs postgres
```

### Test API endpoints
```bash
# Backend health check
curl http://localhost:8080/health

# AI Engine health check
curl http://localhost:8000/health

# Frontend
curl http://localhost/
```

## Database Initialization

The database migrations run automatically on first startup from:
- `modular_core/database/migrations/*.sql`

To manually run migrations:
```bash
docker compose exec postgres psql -U nexsaas -d nexsaas -f /docker-entrypoint-initdb.d/create_audit_log_table.sql
```

## Troubleshooting

### Port already in use
```bash
# Find process using port 80
sudo lsof -i :80

# Kill process
sudo kill -9 <PID>
```

### Permission denied (Docker)
```bash
# Add user to docker group
sudo usermod -aG docker $USER

# Apply changes
newgrp docker

# Or logout and login again
```

### Container won't start
```bash
# View detailed logs
docker compose logs <service-name>

# Restart specific service
docker compose restart <service-name>

# Rebuild and restart
docker compose up -d --build <service-name>
```

### Database connection issues
```bash
# Check PostgreSQL is ready
docker compose exec postgres pg_isready -U nexsaas

# Connect to database
docker compose exec postgres psql -U nexsaas -d nexsaas

# Check tables
docker compose exec postgres psql -U nexsaas -d nexsaas -c "\dt"
```

### Redis connection issues
```bash
# Test Redis connection
docker compose exec redis redis-cli -a secret ping

# Should return: PONG
```

### RabbitMQ connection issues
```bash
# Check RabbitMQ status
docker compose exec rabbitmq rabbitmq-diagnostics ping

# List queues
docker compose exec rabbitmq rabbitmqctl list_queues
```

## Stop Application

```bash
# Stop all services
docker compose down

# Stop and remove volumes (clean slate)
docker compose down -v

# Stop and remove images
docker compose down --rmi all
```

## Development Mode

### Hot reload for frontend
```bash
# Frontend already has hot reload enabled in dev mode
# Edit files in modular_core/react-frontend/src/
# Changes will auto-reload in browser
```

### View real-time logs
```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f php-fpm
docker compose logs -f fastapi
docker compose logs -f celery-worker
```

### Execute commands in containers
```bash
# PHP container
docker compose exec php-fpm bash

# Run PHP script
docker compose exec php-fpm php modular_core/cli/rotate_jwt_keys.php

# FastAPI container
docker compose exec fastapi bash

# Celery container
docker compose exec celery-worker bash
```

## Testing All Phases

See `TEST_ALL_PHASES.md` for comprehensive testing checklist.

## Next Steps

1. Access frontend: http://localhost
2. Access API docs: http://localhost:8080/api/docs
3. Access AI Engine docs: http://localhost:8000/docs
4. Access RabbitMQ UI: http://localhost:15672
5. Run tests: See `TEST_ALL_PHASES.md`

## Support

If you encounter issues:
1. Check logs: `docker compose logs`
2. Verify all services are up: `docker compose ps`
3. Check port availability: `sudo lsof -i :<port>`
4. Restart services: `docker compose restart`
5. Clean restart: `docker compose down && docker compose up -d`
