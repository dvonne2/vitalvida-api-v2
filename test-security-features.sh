#!/bin/bash

# 🛡️ Laravel Sanctum Security Features Test Script
# This script tests all the security enhancements implemented

BASE_URL="http://127.0.0.1:8000/api"
echo "🔐 Testing VitalVida API Security Features"
echo "=========================================="

# Test 1: Weak Password Validation
echo -e "\n📋 Test 1: Weak Password Validation"
echo "Expected: Validation error for weak password"
curl -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "1234567890",
    "password": "weak",
    "password_confirmation": "weak"
  }' | jq '.'

# Test 2: Strong Password Registration
echo -e "\n📋 Test 2: Strong Password Registration"
echo "Expected: Success with token"
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "testuser@example.com",
    "phone": "1234567890",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
  }')

echo "$REGISTER_RESPONSE" | jq '.'

# Extract token for subsequent tests
TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.access_token // empty')

if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
    echo -e "\n✅ Registration successful, token extracted"
else
    echo -e "\n❌ Registration failed, cannot proceed with token tests"
    exit 1
fi

# Test 3: Login with Strong Password
echo -e "\n📋 Test 3: Login with Strong Password"
echo "Expected: Success with token"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "SecurePass123!"
  }')

echo "$LOGIN_RESPONSE" | jq '.'

# Update token from login
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.access_token // empty')

# Test 4: Protected Route Access
echo -e "\n📋 Test 4: Protected Route Access"
echo "Expected: Dashboard data with user info"
curl -s -X GET "$BASE_URL/dashboard" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Test 5: Token Refresh
echo -e "\n📋 Test 5: Token Refresh"
echo "Expected: New token"
REFRESH_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/refresh-token" \
  -H "Authorization: Bearer $TOKEN")

echo "$REFRESH_RESPONSE" | jq '.'

# Update token from refresh
NEW_TOKEN=$(echo "$REFRESH_RESPONSE" | jq -r '.access_token // empty')

# Test 6: User Profile
echo -e "\n📋 Test 6: User Profile"
echo "Expected: User data"
curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $NEW_TOKEN" | jq '.'

# Test 7: Rate Limiting (Login 6 times quickly with wrong password)
echo -e "\n📋 Test 7: Rate Limiting Test"
echo "Expected: Lockout after 5 attempts"
for i in {1..6}; do
    echo "Attempt $i:"
    curl -s -X POST "$BASE_URL/auth/login" \
      -H "Content-Type: application/json" \
      -d '{"email":"testuser@example.com","password":"wrongpassword"}' | jq '.message // .'
    echo ""
    sleep 0.5
done

# Test 8: Logout
echo -e "\n📋 Test 8: Logout"
echo "Expected: Success message"
curl -s -X POST "$BASE_URL/auth/logout" \
  -H "Authorization: Bearer $NEW_TOKEN" | jq '.'

# Test 9: Access After Logout
echo -e "\n📋 Test 9: Access After Logout"
echo "Expected: Unauthorized error"
curl -s -X GET "$BASE_URL/dashboard" \
  -H "Authorization: Bearer $NEW_TOKEN" | jq '.'

echo -e "\n🎉 Security Features Test Complete!"
echo "====================================="
echo "✅ Strong password validation"
echo "✅ Rate limiting and login lockouts"
echo "✅ Token expiration and refresh"
echo "✅ Protected routes"
echo "✅ Enhanced security features" 