#!/bin/bash

# NexSaaS Automated Test Runner
# Runs all property tests and integration tests

set -e

echo "=========================================="
echo "  NexSaaS Automated Test Suite"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
SKIPPED=0

run_test() {
    local test_name=$1
    local test_command=$2
    
    echo -n "Running: $test_name... "
    
    if eval "$test_command" &>/dev/null; then
        echo -e "${GREEN}✓ PASSED${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗ FAILED${NC}"
        FAILED=$((FAILED + 1))
    fi
}

echo -e "${BLUE}Phase 1: Platform Foundation Tests${NC}"
echo "----------------------------------------"
run_test "Tenant Isolation" "docker compose exec -T php-fpm php modular_core/tests/Properties/TenantIsolationTest.php"
run_test "Soft Delete Round Trip" "docker compose exec -T php-fpm php modular_core/tests/Properties/SoftDeleteRoundTripTest.php"
run_test "API Response Envelope" "docker compose exec -T php-fpm php modular_core/tests/Properties/ApiResponseEnvelopeTest.php"
run_test "RBAC Permission" "docker compose exec -T php-fpm php modular_core/tests/Properties/RBACPermissionTest.php"

echo ""
echo -e "${BLUE}Phase 2: CRM Module Tests${NC}"
echo "----------------------------------------"
run_test "Contact Validation" "docker compose exec -T php-fpm php modular_core/tests/Properties/ContactValidationTest.php"

echo ""
echo -e "${BLUE}Phase 3: ERP Module Tests${NC}"
echo "----------------------------------------"
run_test "Double Entry Balance" "docker compose exec -T php-fpm php modular_core/tests/Properties/DoubleEntryBalanceTest.php"
run_test "Voucher Code Assignment" "docker compose exec -T php-fpm php modular_core/tests/Properties/VoucherCodeAssignmentTest.php"
run_test "Company Code Isolation" "docker compose exec -T php-fpm php modular_core/tests/Properties/CompanyCodeQueryIsolationTest.php"
run_test "Closed Period Immutability" "docker compose exec -T php-fpm php modular_core/tests/Properties/ClosedPeriodImmutabilityTest.php"
run_test "Monetary Amount Precision" "docker compose exec -T php-fpm php modular_core/tests/Properties/MonetaryAmountPrecisionTest.php"

echo ""
echo -e "${BLUE}Phase 4: Accounting Module Tests${NC}"
echo "----------------------------------------"
run_test "Realized FX Gain/Loss" "docker compose exec -T php-fpm php modular_core/tests/Properties/RealizedFXGainLossTest.php"
run_test "FX Revaluation Round Trip" "docker compose exec -T php-fpm php modular_core/tests/Properties/FXRevaluationRoundTripTest.php"

echo ""
echo -e "${BLUE}API Health Checks${NC}"
echo "----------------------------------------"
run_test "Backend API Health" "curl -s http://localhost:8080/health | grep -q success"
run_test "FastAPI Health" "curl -s http://localhost:8000/health | grep -q status"
run_test "Frontend Accessible" "curl -s http://localhost | grep -q html"

echo ""
echo -e "${BLUE}Service Health Checks${NC}"
echo "----------------------------------------"
run_test "PostgreSQL" "docker compose exec -T postgres pg_isready -U nexsaas"
run_test "Redis" "docker compose exec -T redis redis-cli -a secret ping | grep -q PONG"
run_test "RabbitMQ" "docker compose exec -T rabbitmq rabbitmq-diagnostics ping"

echo ""
echo -e "${BLUE}AI Engine Tests${NC}"
echo "----------------------------------------"
run_test "Lead Score Prediction" "curl -s -X POST http://localhost:8000/predict/lead-score -H 'Content-Type: application/json' -d '{\"tenant_id\":\"test\",\"lead_data\":{}}' | grep -q result"
run_test "Win Probability" "curl -s -X POST http://localhost:8000/predict/win-probability -H 'Content-Type: application/json' -d '{\"tenant_id\":\"test\",\"deal_data\":{}}' | grep -q result"
run_test "Sentiment Analysis" "curl -s -X POST http://localhost:8000/predict/sentiment -H 'Content-Type: application/json' -d '{\"tenant_id\":\"test\",\"text\":\"Great product\"}' | grep -q result"

echo ""
echo "=========================================="
echo "  Test Results Summary"
echo "=========================================="
echo -e "${GREEN}Passed:  $PASSED${NC}"
echo -e "${RED}Failed:  $FAILED${NC}"
echo -e "${YELLOW}Skipped: $SKIPPED${NC}"
echo ""

TOTAL=$((PASSED + FAILED + SKIPPED))
echo "Total tests: $TOTAL"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Check logs for details.${NC}"
    exit 1
fi
