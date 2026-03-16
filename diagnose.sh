#!/bin/bash
echo "=== Nexa CRM Diagnostic Tool ==="
echo "Testing Docker Connection..."
docker version > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "[OK] Docker is installed and accessible."
else
    echo "[FAIL] Docker is not accessible. You may need 'sudo'."
fi

echo ""
echo "Testing Port 8080..."
lsof -i :8080 > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "[OK] Something is listening on port 8080."
else
    echo "[FAIL] Port 8080 is inactive. Backend is likely down."
fi

echo ""
echo "Container Status:"
sudo docker compose ps 2>/dev/null || echo "Run manually: sudo docker compose ps"
