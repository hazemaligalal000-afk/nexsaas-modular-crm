#!/bin/bash

# ============================================================================
# FIX DOCKER PERMISSIONS
# ============================================================================
# This script fixes Docker permission issues
# Run this ONCE, then you can run the application
# ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════════════╗"
echo "║                                                                              ║"
echo "║                    🔧 FIXING DOCKER PERMISSIONS 🔧                           ║"
echo "║                                                                              ║"
echo "╚══════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${YELLOW}This will add your user to the docker group.${NC}"
echo ""
echo "Step 1: Adding user to docker group..."
sudo usermod -aG docker $USER
echo -e "${GREEN}✓ User added to docker group${NC}"
echo ""

echo "Step 2: Applying group changes..."
echo ""
echo -e "${CYAN}You have 2 options:${NC}"
echo ""
echo "  Option A (Recommended): Run this command now:"
echo -e "    ${CYAN}newgrp docker${NC}"
echo ""
echo "  Option B: Logout and login again"
echo ""
echo -e "${YELLOW}After choosing an option, run:${NC}"
echo -e "  ${CYAN}./RUN_NOW.sh${NC}"
echo ""
echo "╔══════════════════════════════════════════════════════════════════════════════╗"
echo "║                                                                              ║"
echo "║                    ✅ PERMISSIONS FIXED! ✅                                   ║"
echo "║                                                                              ║"
echo "║                  Now run: newgrp docker                                      ║"
echo "║                  Then run: ./RUN_NOW.sh                                      ║"
echo "║                                                                              ║"
echo "╚══════════════════════════════════════════════════════════════════════════════╝"
