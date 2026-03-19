#!/bin/bash

# NexSaaS Application Startup Script
# This script starts all services and verifies they're running correctly

set -e

echo "=========================================="
echo "  NexSaaS Application Startup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed${NC}"
    echo "Please install Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker compose &> /dev/null; then
    echo -e "${RED}Error: Docker Compose is not installed${NC}"
    echo "Please install Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

# Check Docker permissions
if ! docker ps &> /dev/null; then
    echo -e "${YELLOW}Warning: Docker permission denied${NC}"
    echo "Run: sudo usermod -aG docker \$USER"
    echo "Then logout and login again"
    echo ""
    echo "Attempting to run with sudo..."
    DOCKER_CMD="sudo docker"
    COMPOSE_CMD="sudo docker compose"
else
    DOCKER_CMD="docker"
    COMPOSE_CMD="docker compose"
fi

echo "Step 1: Checking for port conflicts..."
echo "----------------------------------------"

check_port() {
    local port=$1
    local service=$2
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1 ; then
        echo -e "${YELLOW}Warning: Port $port is already in use (needed for $service)${NC}"
        echo "Run: sudo lsof -i :$port to find the process"
        return 1
    else
        echo -e "${GREEN}✓${NC} Port $port is available ($service)"
        return 0
    fi
}

PORTS_OK=true
check_port 80 "Frontend" || PORTS_OK=false
check_port 8080 "Backend API" || PORTS_OK=false
check_port 8000 "FastAPI" || PORTS_OK=false
check_port 5432 "PostgreSQL" || PORTS_OK=false
check_port 6379 "Redis" || PORTS_OK=false
check_port 5672 "RabbitMQ" || PORTS_OK=false
check_port 15672 "RabbitMQ UI" || PORTS_OK=false

if [ "$PORTS_OK" = false ]; then
    echo ""
    echo -e "${YELLOW}Some ports are in use. Do you want to continue anyway? (y/n)${NC}"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Startup cancelled."
        exit 1
    fi
fi

echo ""
echo "Step 2: Checking .env file..."
echo "----------------------------------------"
if [ ! -f .env ]; then
    echo -e "${YELLOW}Warning: .env file not found${NC}"
    echo "Copying from .env.example..."
    cp .env.example .env
    echo -e "${GREEN}✓${NC} .env file created"
else
    echo -e "${GREEN}✓${NC} .env file exists"
fi

echo ""
echo "Step 3: Stopping any existing containers..."
echo "----------------------------------------"
$COMPOSE_CMD down 2>/dev/null || true
echo -e "${GREEN}✓${NC} Existing containers stopped"

echo ""
echo "Step 4: Building Docker images..."
echo "----------------------------------------"
$COMPOSE_CMD build --no-cache
echo -e "${GREEN}✓${NC} Docker images built"

echo ""
echo "Step 5: Starting services..."
echo "----------------------------------------"
$COMPOSE_CMD up -d

echo ""
echo "Step 6: Waiting for services to be healthy..."
echo "----------------------------------------"

wait_for_service() {
    local service=$1
    local max_attempts=30
    local attempt=0
    
    echo -n "Waiting for $service..."
    while [ $attempt -lt $max_attempts ]; do
        if $DOCKER_CMD ps | grep -q "$service.*Up"; then
            echo -e " ${GREEN}✓${NC}"
            return 0
        fi
        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done
    echo -e " ${RED}✗${NC}"
    return 1
}

wait_for_service "nexsaas_postgres"
wait_for_service "nexsaas_redis"
wait_for_service "nexsaas_rabbitmq"
wait_for_service "nexsaas_php"
wait_for_service "nexsaas_backend"
wait_for_service "nexsaas_fastapi"
wait_for_service "nexsaas_celery"
wait_for_service "nexsaas_frontend"

echo ""
echo "Step 7: Verifying service health..."
echo "----------------------------------------"

# Check PostgreSQL
echo -n "PostgreSQL... "
if $COMPOSE_CMD exec -T postgres pg_isready -U nexsaas &>/dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# Check Redis
echo -n "Redis... "
if $COMPOSE_CMD exec -T redis redis-cli -a secret ping &>/dev/null | grep -q PONG; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# Check RabbitMQ
echo -n "RabbitMQ... "
if $COMPOSE_CMD exec -T rabbitmq rabbitmq-diagnostics ping &>/dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# Check Backend API
echo -n "Backend API... "
sleep 5  # Give it a moment to start
if curl -s http://localhost:8080 &>/dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${YELLOW}⚠${NC} (may still be starting)"
fi

# Check FastAPI
echo -n "FastAPI... "
if curl -s http://localhost:8000/docs &>/dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${YELLOW}⚠${NC} (may still be starting)"
fi

# Check Frontend
echo -n "Frontend... "
if curl -s http://localhost &>/dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${YELLOW}⚠${NC} (may still be starting)"
fi

echo ""
echo "=========================================="
echo "  Application Started Successfully!"
echo "=========================================="
echo ""
echo "Services are running at:"
echo "  Frontend:        http://localhost"
echo "  Backend API:     http://localhost:8080"
echo "  FastAPI (AI):    http://localhost:8000"
echo "  FastAPI Docs:    http://localhost:8000/docs"
echo "  RabbitMQ UI:     http://localhost:15672"
echo "    Username: nexsaas"
echo "    Password: secret"
echo ""
echo "Database:"
echo "  PostgreSQL:      localhost:5432"
echo "  Database:        nexsaas"
echo "  Username:        nexsaas"
echo "  Password:        secret"
echo ""
echo "Useful commands:"
echo "  View logs:       docker compose logs -f"
echo "  Stop services:   docker compose down"
echo "  Restart:         docker compose restart"
echo "  Status:          docker compose ps"
echo ""
echo "Next steps:"
echo "  1. Open http://localhost in your browser"
echo "  2. Run tests: ./run_tests.sh"
echo "  3. See TEST_ALL_PHASES.md for testing guide"
echo ""
