# 🚀 Local Development Guide

## Running the Application Locally

You have two options to run the NexSaaS application:

### Option 1: Docker (Recommended - Currently Running)

This runs all services in containers - easiest and most reliable.

```bash
# Start all services
docker compose up --build

# Or run in background
docker compose up -d --build

# View logs
docker compose logs -f

# Stop services
docker compose down
```

**Access Points:**
- Frontend: http://localhost
- Backend API: http://localhost:8080
- AI Engine: http://localhost:8000
- API Docs: http://localhost:8000/docs
- RabbitMQ UI: http://localhost:15672 (nexsaas/secret)

---

### Option 2: Local Development (Without Docker)

Run frontend and backend separately for development.

#### Prerequisites:
- Node.js 18+ and npm
- Python 3.11+
- PostgreSQL 16
- Redis 7
- RabbitMQ 3
- PHP 8.3 with extensions

#### 1. Start Database Services

You can use Docker for just the databases:

```bash
docker compose up -d postgres redis rabbitmq
```

Or install them locally on your system.

#### 2. Start Backend (PHP)

```bash
# Install PHP dependencies (if using Composer)
composer install

# Start PHP built-in server
php -S localhost:8080 -t .
```

#### 3. Start AI Engine (Python FastAPI)

```bash
cd ai_engine

# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # On Linux/Mac
# or
venv\Scripts\activate  # On Windows

# Install dependencies
pip install -r requirements.txt

# Start FastAPI server
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

#### 4. Start Frontend (React)

```bash
cd modular_core/react-frontend

# Install dependencies
npm install

# Start development server
npm run dev
```

The frontend will typically run on http://localhost:5173

---

## Current Status

✅ Docker Compose is currently building and starting all services
⏳ This may take 3-5 minutes on first run
📦 Installing Python ML libraries (scikit-learn, sentence-transformers, etc.)

Once complete, you'll see:
- ✅ postgres (PostgreSQL database)
- ✅ redis (Cache)
- ✅ rabbitmq (Message queue)
- ✅ php-fpm (PHP backend)
- ✅ backend (Nginx)
- ✅ fastapi (AI engine)
- ✅ celery-worker (Background jobs)
- ✅ frontend (React app)

---

## Useful Commands

### Docker Commands
```bash
# Check service status
docker compose ps

# View all logs
docker compose logs -f

# View specific service logs
docker compose logs -f fastapi
docker compose logs -f backend
docker compose logs -f frontend

# Restart a service
docker compose restart fastapi

# Stop all services
docker compose down

# Clean restart (removes data)
docker compose down -v
docker compose up -d --build

# Access database
docker compose exec postgres psql -U nexsaas -d nexsaas
```

### Development Commands
```bash
# Frontend development
cd modular_core/react-frontend
npm run dev          # Start dev server
npm run build        # Build for production
npm run preview      # Preview production build

# Backend (AI Engine)
cd ai_engine
uvicorn main:app --reload  # Start with hot reload
```

---

## Environment Variables

Copy `.env.example` to `.env` and configure:

```env
APP_ENV=local
DB_HOST=localhost
DB_PORT=5432
DB_NAME=nexsaas
DB_USER=nexsaas
DB_PASSWORD=secret
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=secret
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=nexsaas
RABBITMQ_PASSWORD=secret
```

---

## Troubleshooting

### Port Already in Use
```bash
# Find process using port
sudo lsof -i :80
sudo lsof -i :8080
sudo lsof -i :8000

# Kill process
sudo kill -9 <PID>
```

### Docker Permission Denied
```bash
sudo usermod -aG docker $USER
newgrp docker
```

### Services Not Starting
```bash
# Check logs
docker compose logs <service-name>

# Rebuild specific service
docker compose build --no-cache <service-name>
docker compose up -d <service-name>
```

### Database Connection Issues
```bash
# Check if PostgreSQL is running
docker compose ps postgres

# Check PostgreSQL logs
docker compose logs postgres

# Restart PostgreSQL
docker compose restart postgres
```

---

## Next Steps

1. ✅ Wait for Docker build to complete (3-5 minutes)
2. ✅ Check all services are running: `docker compose ps`
3. ✅ Open http://localhost in your browser
4. ✅ Test the API: http://localhost:8000/docs
5. ✅ Run tests: `./run_tests.sh`

---

## Development Workflow

### Making Changes

**Frontend Changes:**
- Edit files in `modular_core/react-frontend/src/`
- Changes auto-reload in dev mode
- Build for production: `npm run build`

**Backend Changes (PHP):**
- Edit files in `modular_core/modules/`
- Restart PHP-FPM: `docker compose restart php-fpm`

**AI Engine Changes:**
- Edit files in `ai_engine/`
- Restart FastAPI: `docker compose restart fastapi`

### Running Tests
```bash
# All tests
./run_tests.sh

# Specific test
docker compose exec php-fpm php modular_core/tests/run_test.php
```

---

Happy coding! 🎉
