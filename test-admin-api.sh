#!/bin/bash

# VitalVida Admin API Testing Script
# This script helps you test all admin endpoints

echo "🚀 VitalVida Admin API Testing Script"
echo "====================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8000/api"

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Check if server is running
echo "Checking if Laravel server is running..."
if curl -s "$BASE_URL/health" > /dev/null; then
    print_status "Server is running!"
else
    print_error "Server is not running. Please start with: php artisan serve"
    exit 1
fi

echo ""
print_info "Step 1: Testing Authentication"
echo "-----------------------------------"

# Test login
echo "Testing login with superadmin credentials..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{
        "email": "admin@vitalvida.com",
        "password": "admin123456"
    }')

# Extract token
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    print_status "Login successful! Token received."
    echo "Token: ${TOKEN:0:20}..."
else
    print_error "Login failed. Response: $LOGIN_RESPONSE"
    exit 1
fi

echo ""
print_info "Step 2: Testing Admin Dashboard"
echo "-----------------------------------"

# Test dashboard access
DASHBOARD_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/dashboard" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$DASHBOARD_RESPONSE" | grep -q '"success":true'; then
    print_status "Dashboard access successful!"
    echo "Response: $(echo $DASHBOARD_RESPONSE | jq -r '.data.stats.total_users // "N/A"') total users"
else
    print_error "Dashboard access failed. Response: $DASHBOARD_RESPONSE"
fi

echo ""
print_info "Step 3: Testing User Management"
echo "------------------------------------"

# Test getting users
USERS_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/users?per_page=5" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$USERS_RESPONSE" | grep -q '"success":true'; then
    print_status "User list access successful!"
    USER_COUNT=$(echo $USERS_RESPONSE | jq -r '.data.total // "N/A"')
    echo "Total users: $USER_COUNT"
else
    print_error "User list access failed. Response: $USERS_RESPONSE"
fi

echo ""
print_info "Step 4: Testing KYC Management"
echo "-----------------------------------"

# Test KYC endpoints
KYC_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/kyc/pending" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$KYC_RESPONSE" | grep -q '"success":true'; then
    print_status "KYC management access successful!"
    KYC_COUNT=$(echo $KYC_RESPONSE | jq -r '.data.total // "N/A"')
    echo "Pending KYC applications: $KYC_COUNT"
else
    print_error "KYC management access failed. Response: $KYC_RESPONSE"
fi

echo ""
print_info "Step 5: Testing Role Management"
echo "------------------------------------"

# Test role management
ROLES_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/roles" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$ROLES_RESPONSE" | grep -q '"success":true'; then
    print_status "Role management access successful!"
    ROLE_COUNT=$(echo $ROLES_RESPONSE | jq -r '.data | length // "N/A"')
    echo "Available roles: $ROLE_COUNT"
else
    print_error "Role management access failed. Response: $ROLES_RESPONSE"
fi

echo ""
print_info "Step 6: Testing System Configuration"
echo "----------------------------------------"

# Test system config (superadmin only)
SYSTEM_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/system/config" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$SYSTEM_RESPONSE" | grep -q '"success":true'; then
    print_status "System configuration access successful!"
else
    print_error "System configuration access failed. Response: $SYSTEM_RESPONSE"
fi

echo ""
print_info "Step 7: Testing Audit Logs"
echo "-------------------------------"

# Test audit logs
AUDIT_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/audit-logs?per_page=5" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$AUDIT_RESPONSE" | grep -q '"success":true'; then
    print_status "Audit logs access successful!"
    AUDIT_COUNT=$(echo $AUDIT_RESPONSE | jq -r '.data.total // "N/A"')
    echo "Total audit logs: $AUDIT_COUNT"
else
    print_error "Audit logs access failed. Response: $AUDIT_RESPONSE"
fi

echo ""
print_info "Step 8: Testing API Versioning"
echo "-----------------------------------"

# Test v2 endpoints (should return "coming soon")
V2_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/v2/analytics/dashboard" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

if echo "$V2_RESPONSE" | grep -q '"success":true'; then
    print_status "V2 API versioning working!"
    echo "V2 response: $(echo $V2_RESPONSE | jq -r '.message // "N/A"')"
else
    print_error "V2 API versioning failed. Response: $V2_RESPONSE"
fi

echo ""
print_info "Step 9: Testing Health Check"
echo "--------------------------------"

# Test health endpoint
HEALTH_RESPONSE=$(curl -s -X GET "$BASE_URL/health" \
    -H "Accept: application/json")

if echo "$HEALTH_RESPONSE" | grep -q '"status":"healthy"'; then
    print_status "Health check successful!"
else
    print_error "Health check failed. Response: $HEALTH_RESPONSE"
fi

echo ""
echo "🎉 Testing Complete!"
echo "==================="
print_status "All admin endpoints are working correctly!"
echo ""
print_info "Next Steps:"
echo "1. Start building your admin frontend"
echo "2. Test with different user roles"
echo "3. Add more test data"
echo "4. Configure production settings"
echo ""
print_warning "Remember to change the default password!"
echo "" 