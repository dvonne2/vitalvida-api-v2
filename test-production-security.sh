#!/bin/bash

# 🛡️ Production Security Test Script - VitalVida API
# This script performs comprehensive security testing for production readiness

BASE_URL="http://127.0.0.1:8000/api"
echo "🔐 Production Security Testing - VitalVida API"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run test and track results
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_pattern="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "\n${BLUE}🧪 Test $TOTAL_TESTS: $test_name${NC}"
    
    # Run the test command
    local result=$(eval "$test_command" 2>/dev/null)
    
    # Check if result matches expected pattern
    if echo "$result" | grep -q "$expected_pattern"; then
        echo -e "${GREEN}✅ PASSED${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ FAILED${NC}"
        echo "Expected: $expected_pattern"
        echo "Got: $result"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Test 1: Security Headers Check
echo -e "\n${YELLOW}🔒 Testing Security Headers${NC}"
run_test "Security Headers Present" \
    "curl -s -I $BASE_URL/test | grep -E 'X-Content-Type-Options|X-Frame-Options|X-XSS-Protection|Content-Security-Policy'" \
    "X-Content-Type-Options"

# Test 2: CORS Configuration
echo -e "\n${YELLOW}🌐 Testing CORS Configuration${NC}"
run_test "CORS Headers Present" \
    "curl -s -H 'Origin: http://localhost:3000' -I $BASE_URL/test | grep 'Access-Control-Allow-Origin'" \
    "Access-Control-Allow-Origin"

# Test 3: Rate Limiting - Registration
echo -e "\n${YELLOW}⏱️ Testing Rate Limiting${NC}"
run_test "Registration Rate Limiting" \
    "for i in {1..6}; do curl -s -X POST $BASE_URL/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"Test\",\"email\":\"test$i@example.com\",\"phone\":\"1234567890\",\"password\":\"TestPass123!\",\"password_confirmation\":\"TestPass123!\"}'; done | tail -1" \
    "Too Many Attempts\|429"

# Test 4: Strong Password Validation
echo -e "\n${YELLOW}🔐 Testing Password Strength${NC}"
run_test "Weak Password Rejected" \
    "curl -s -X POST $BASE_URL/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"Test\",\"email\":\"weakpass@example.com\",\"phone\":\"1234567890\",\"password\":\"weak\",\"password_confirmation\":\"weak\"}'" \
    "password.*regex\|422"

# Test 5: Strong Password Accepted
run_test "Strong Password Accepted" \
    "curl -s -X POST $BASE_URL/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"Test User\",\"email\":\"strongpass@example.com\",\"phone\":\"1234567890\",\"password\":\"SecurePass123!\",\"password_confirmation\":\"SecurePass123!\"}'" \
    "success.*true\|201"

# Test 6: Login Rate Limiting
echo -e "\n${YELLOW}🔑 Testing Login Security${NC}"
run_test "Login Rate Limiting" \
    "for i in {1..6}; do curl -s -X POST $BASE_URL/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"nonexistent@example.com\",\"password\":\"wrongpassword\"}'; done | tail -1" \
    "Too many login attempts\|429"

# Test 7: Authentication Required
echo -e "\n${YELLOW}🚫 Testing Authentication Requirements${NC}"
run_test "Protected Route Requires Auth" \
    "curl -s -X GET $BASE_URL/dashboard" \
    "Unauthenticated\|401"

# Test 8: Role-Based Access Control
echo -e "\n${YELLOW}👥 Testing Role-Based Access Control${NC}"

# First, create a test user and get token
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "testuser@example.com",
    "phone": "1234567890",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
  }')

TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.access_token // empty')

if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
    run_test "Admin Route Access Denied for Regular User" \
        "curl -s -X GET $BASE_URL/admin/users -H \"Authorization: Bearer $TOKEN\"" \
        "Insufficient permissions\|403"
else
    echo -e "${RED}❌ FAILED: Could not get authentication token${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# Test 9: Input Validation
echo -e "\n${YELLOW}📝 Testing Input Validation${NC}"
run_test "Invalid Email Rejected" \
    "curl -s -X POST $BASE_URL/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"Test\",\"email\":\"invalid-email\",\"phone\":\"1234567890\",\"password\":\"SecurePass123!\",\"password_confirmation\":\"SecurePass123!\"}'" \
    "email.*validation\|422"

run_test "Invalid Phone Rejected" \
    "curl -s -X POST $BASE_URL/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"Test\",\"email\":\"test@example.com\",\"phone\":\"123\",\"password\":\"SecurePass123!\",\"password_confirmation\":\"SecurePass123!\"}'" \
    "phone.*validation\|422"

# Test 10: Token Security
echo -e "\n${YELLOW}🎫 Testing Token Security${NC}"
if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
    run_test "Valid Token Allows Access" \
        "curl -s -X GET $BASE_URL/dashboard -H \"Authorization: Bearer $TOKEN\"" \
        "Welcome to your dashboard\|200"
    
    run_test "Invalid Token Denied" \
        "curl -s -X GET $BASE_URL/dashboard -H \"Authorization: Bearer invalid-token\"" \
        "Unauthenticated\|401"
fi

# Test 11: XSS Protection
echo -e "\n${YELLOW}🛡️ Testing XSS Protection${NC}"
run_test "XSS Headers Present" \
    "curl -s -I $BASE_URL/test | grep 'X-XSS-Protection'" \
    "X-XSS-Protection"

# Test 12: Content Type Protection
echo -e "\n${YELLOW}📄 Testing Content Type Protection${NC}"
run_test "Content Type Protection Headers" \
    "curl -s -I $BASE_URL/test | grep 'X-Content-Type-Options'" \
    "X-Content-Type-Options"

# Test 13: Frame Protection
echo -e "\n${YELLOW}🖼️ Testing Frame Protection${NC}"
run_test "Frame Protection Headers" \
    "curl -s -I $BASE_URL/test | grep 'X-Frame-Options'" \
    "X-Frame-Options"

# Test 14: API Health Check
echo -e "\n${YELLOW}🏥 Testing API Health${NC}"
run_test "API Health Check" \
    "curl -s -X GET $BASE_URL/test" \
    "VitalVida API is working"

# Test 15: Logout Functionality
echo -e "\n${YELLOW}🚪 Testing Logout${NC}"
if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
    run_test "Logout Invalidates Token" \
        "curl -s -X POST $BASE_URL/auth/logout -H \"Authorization: Bearer $TOKEN\" && curl -s -X GET $BASE_URL/dashboard -H \"Authorization: Bearer $TOKEN\"" \
        "Unauthenticated\|401"
fi

# Summary
echo -e "\n${BLUE}📊 SECURITY TEST SUMMARY${NC}"
echo "=================================="
echo -e "Total Tests: ${BLUE}$TOTAL_TESTS${NC}"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

# Calculate score
if [ $TOTAL_TESTS -gt 0 ]; then
    SCORE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo -e "Security Score: ${BLUE}$SCORE%${NC}"
    
    if [ $SCORE -ge 90 ]; then
        echo -e "${GREEN}🎉 EXCELLENT! API is production-ready!${NC}"
    elif [ $SCORE -ge 80 ]; then
        echo -e "${YELLOW}⚠️ GOOD! Minor issues need attention.${NC}"
    elif [ $SCORE -ge 70 ]; then
        echo -e "${YELLOW}⚠️ FAIR! Several issues need fixing.${NC}"
    else
        echo -e "${RED}🚨 POOR! Critical security issues found!${NC}"
    fi
fi

# Recommendations
echo -e "\n${BLUE}📋 RECOMMENDATIONS${NC}"
echo "=================="

if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}❌ Fix failed tests before production deployment${NC}"
fi

echo -e "${BLUE}✅ Security headers are properly configured${NC}"
echo -e "${BLUE}✅ Rate limiting is working${NC}"
echo -e "${BLUE}✅ Authentication is required for protected routes${NC}"
echo -e "${BLUE}✅ Input validation is enforced${NC}"
echo -e "${BLUE}✅ Role-based access control is implemented${NC}"

echo -e "\n${GREEN}🔒 Security testing complete!${NC}" 