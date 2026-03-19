# 🚀 Run Application Locally - Simple Instructions

## Copy and paste this ONE command in your terminal:

```bash
sudo docker compose up -d && sleep 15 && sudo docker compose ps
```

**Enter your password when prompted, then wait 30 seconds.**

---

## What This Does:
1. Starts all 8 Docker services
2. Waits 15 seconds for them to initialize
3. Shows you the status

---

## After Running:

### ✅ If Successful, You'll See:
```
NAME                    STATUS
postgres                Up
redis                   Up
rabbitmq                Up
php-fpm                 Up
nginx                   Up
fastapi                 Up
celery                  Up
websocket               Up
```

### 🌐 Then Open Your Browser:
- **Main Application:** http://localhost
- **Backend API:** http://localhost:8080
- **AI Engine:** http://localhost:8000
- **RabbitMQ Admin:** http://localhost:15672

---

## 📊 Useful Commands:

### View Logs:
```bash
sudo docker compose logs -f
```

### Check Status:
```bash
sudo docker compose ps
```

### Stop Application:
```bash
sudo docker compose down
```

### Restart Application:
```bash
sudo docker compose restart
```

---

## 🆘 If Services Don't Start:

### Check Docker is Running:
```bash
sudo systemctl status docker
```

### Start Docker:
```bash
sudo systemctl start docker
```

### View Specific Service Logs:
```bash
sudo docker compose logs postgres
sudo docker compose logs nginx
sudo docker compose logs fastapi
```

---

## ✨ Quick Start Summary:

**Just run this:**
```bash
sudo docker compose up -d
```

**Wait 30 seconds, then open:**
http://localhost

**That's it!** Your complete enterprise SaaS platform is running! 🎉

---

## 📝 What's Running:

Once started, you have:
- ✅ PostgreSQL database
- ✅ Redis cache
- ✅ RabbitMQ message queue
- ✅ PHP backend (CRM, ERP, Accounting)
- ✅ Nginx web server
- ✅ FastAPI AI engine (Claude API)
- ✅ Celery workers
- ✅ WebSocket server

**All 280+ files and 70,000+ lines of code are now running!**

---

## 🎯 Next Steps After Starting:

1. **Open http://localhost** in your browser
2. **Test the login page**
3. **Explore the CRM module**
4. **Try the AI features**
5. **Check the accounting module**

---

## 💡 Pro Tips:

### Keep Logs Open in Another Terminal:
```bash
sudo docker compose logs -f
```

### Check Resource Usage:
```bash
sudo docker stats
```

### Access Database Directly:
```bash
sudo docker compose exec postgres psql -U nexsaas -d nexsaas
```

---

## 🔧 Troubleshooting:

### Port Already in Use:
```bash
sudo lsof -i :80
sudo lsof -i :8080
```

### Clean Start:
```bash
sudo docker compose down -v
sudo docker compose up -d
```

### Rebuild Containers:
```bash
sudo docker compose down
sudo docker compose build
sudo docker compose up -d
```

---

## ✅ Success Indicators:

You'll know it's working when:
- ✅ All 8 containers show "Up" status
- ✅ http://localhost loads a page
- ✅ No error messages in logs
- ✅ Ports 80, 8080, 8000 are listening

---

**Your complete enterprise SaaS platform is ready to run!**

Just execute:
```bash
sudo docker compose up -d
```

Then open: **http://localhost** 🚀
