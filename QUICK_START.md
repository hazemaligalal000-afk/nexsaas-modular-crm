# NexSaaS - Quick Start Guide

## 🚀 Start Application (3 Steps)

### Step 1: Fix Docker Permissions (One-time setup)
```bash
sudo usermod -aG docker $USER
newgrp docker
```

### Step 2: Start All Services
```bash
./start.sh
```

### Step 3: Access Application
- **Frontend**: http://localhost
- **Backend API**: http://localhost:8080
- **AI Engine**: http://localhost:8000/docs
- **RabbitMQ UI**: http://localhost:15672 (nexsaas/secret)

---

## 🧪 Run Tests

```bash
./run_tests.sh
```

---

## 📊 Service Status

```bash
# Check all services
docker compose ps

# View logs
docker compose logs -f

# Restart a service
docker compose restart <service-name>
```

---

## 🛑 Stop Application

```bash
docker compose down
```

---

## 🔧 Troubleshooting

### Port Already in Use
```bash
# Find process
sudo lsof -i :80

# Kill process
sudo kill -9 <PID>
```

### Service Won't Start
```bash
# View logs
docker compose logs <service-name>

# Rebuild
docker compose up -d --build <service-name>
```

### Database Issues
```bash
# Connect to database
docker compose exec postgres psql -U nexsaas -d nexsaas

# Check tables
\dt

# Reset database
docker compose down -v
docker compose up -d
```

---

## 📚 Documentation

- **Full Startup Guide**: `START_APPLICATION.md`
- **Complete Testing Guide**: `TEST_ALL_PHASES.md`
- **Phase 5 Report**: `PHASE_5_COMPLETION_REPORT.md`

---

## 🎯 Quick Tests

### Test Backend API
```bash
curl http://localhost:8080/health
```

### Test AI Engine
```bash
curl http://localhost:8000/health
```

### Test Database
```bash
docker compose exec postgres pg_isready -U nexsaas
```

### Test Redis
```bash
docker compose exec redis redis-cli -a secret ping
```

---

## 📦 Services Overview

| Service | Container | Port | Status Check |
|---------|-----------|------|--------------|
| Frontend | nexsaas_frontend | 80 | `curl localhost` |
| Backend | nexsaas_backend | 8080 | `curl localhost:8080/health` |
| FastAPI | nexsaas_fastapi | 8000 | `curl localhost:8000/health` |
| PostgreSQL | nexsaas_postgres | 5432 | `docker compose exec postgres pg_isready` |
| Redis | nexsaas_redis | 6379 | `docker compose exec redis redis-cli ping` |
| RabbitMQ | nexsaas_rabbitmq | 5672, 15672 | `docker compose exec rabbitmq rabbitmq-diagnostics ping` |
| Celery | nexsaas_celery | - | `docker compose logs celery-worker` |

---

## 🔑 Default Credentials

### RabbitMQ Management
- URL: http://localhost:15672
- User: `nexsaas`
- Pass: `secret`

### PostgreSQL
- Host: `localhost:5432`
- DB: `nexsaas`
- User: `nexsaas`
- Pass: `secret`

### Redis
- Host: `localhost:6379`
- Pass: `secret`

---

## 💡 Common Commands

```bash
# Start everything
./start.sh

# Run all tests
./run_tests.sh

# View all logs
docker compose logs -f

# View specific service logs
docker compose logs -f php-fpm
docker compose logs -f fastapi
docker compose logs -f celery-worker

# Restart specific service
docker compose restart php-fpm

# Stop everything
docker compose down

# Clean restart (removes volumes)
docker compose down -v && docker compose up -d

# Check service status
docker compose ps

# Execute command in container
docker compose exec php-fpm bash
docker compose exec postgres psql -U nexsaas -d nexsaas
```

---

## 🎓 Next Steps

1. ✅ Start application: `./start.sh`
2. ✅ Run tests: `./run_tests.sh`
3. ✅ Open browser: http://localhost
4. ✅ Check API docs: http://localhost:8000/docs
5. ✅ Review test results: See `TEST_ALL_PHASES.md`

---

## 📞 Support

If you encounter issues:
1. Check logs: `docker compose logs`
2. Verify services: `docker compose ps`
3. Check ports: `sudo lsof -i :<port>`
4. Restart: `docker compose restart`
5. Clean restart: `docker compose down -v && docker compose up -d`

---

## ✨ Features Implemented

### Phase 1: Platform Foundation ✅
- Multi-tenant architecture
- RBAC & permissions
- JWT authentication
- Module auto-discovery

### Phase 2: CRM Module ✅
- Contact & lead management
- Deal pipeline & forecasting
- Omnichannel inbox
- Workflow automation

### Phase 3: ERP Module ✅
- General ledger
- Invoicing & AR/AP
- Inventory management
- HR & payroll

### Phase 4: Accounting Module ✅
- Chart of accounts
- Voucher engine
- Multi-currency & FX
- Fixed assets
- Financial statements

### Phase 5: Platform Core Services ✅
- WebSocket notifications
- SaaS billing (Stripe)
- Audit logging
- Webhook management
- Internationalization (i18n)

### Phase 6: AI Engine ✅
- Lead scoring
- Win probability
- Churn prediction
- Sentiment analysis
- Semantic search

### Phase 7: Cross-Cutting ✅
- Performance & caching
- Security hardening
- Data export
- Integration wiring

---

**Total: 7 Phases Complete | 64 Tasks | 48 Test Scenarios**
