#!/bin/bash

# Start NexSaaS with sudo (Docker permission workaround)
# Use this if you're getting "permission denied" errors

echo "🚀 Starting NexSaaS with sudo..."
echo ""

# Check if docker-compose.yml exists
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Error: docker-compose.yml not found"
    echo "Please run this script from the project root directory"
    exit 1
fi

# Stop any existing containers
echo "🛑 Stopping existing containers..."
sudo docker compose down 2>/dev/null || true

# Start services
echo "🐳 Starting Docker services..."
sudo docker compose up -d

# Wait for services to start
echo "⏳ Waiting for services to start..."
sleep 10

# Check status
echo ""
echo "📊 Service Status:"
sudo docker compose ps

# Health check
echo ""
echo "🏥 Running health checks..."

# Check if containers are running
if sudo docker compose ps | grep -q "Up"; then
    echo "✅ Services are running!"
    echo ""
    echo "🌐 Access your application:"
    echo "   Frontend:  http://localhost"
    echo "   Backend:   http://localhost:8080"
    echo "   AI Engine: http://localhost:8000"
    echo "   RabbitMQ:  http://localhost:15672 (user: nexsaas, pass: secret)"
    echo ""
    echo "📝 To view logs:"
    echo "   sudo docker compose logs -f"
    echo ""
    echo "🛑 To stop:"
    echo "   sudo docker compose down"
else
    echo "⚠️  Some services may not be running properly"
    echo "Check logs with: sudo docker compose logs"
fi

echo ""
echo "✨ Done!"
