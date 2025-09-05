#!/bin/bash

# Navigate to role portals directory
cd /Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals/role-portals

echo "🎯 Cloning ROLE-BASED PORTALS (Enter As Role)..."
echo "================================================"

# Define role portals with their repos and ports
ROLE_PORTALS=(
    "vitalvida-accountability-portal:3004"
    "vitalvida-inventory-guardian:3005"
    "vitalvida-telesales:3006"
    "delivery-agent-logistics-hub:3007"
    "vitalvida-compliance-guard:3008"
    "vitalvida-command-center-elite:3009"
    "general-manager:3010"
    "vitalvida-ceo-center-africa:3011"
    "vitalvida-zenith-hr:3012"
    "vitalvida-investor-cockpit:3013"
    "first-bank-fin-control:3014"
    "vitalvida-cost-brain:3019"
    "nigeria-market-pulse:3020"
)

GITHUB_USER="dvonne2"
SUCCESS_COUNT=0
FAIL_COUNT=0

for PORTAL in "${ROLE_PORTALS[@]}"; do
    IFS=':' read -r REPO PORT <<< "$PORTAL"
    echo ""
    echo "📦 Cloning $REPO (Port: $PORT)..."
    
    if [ -d "$REPO" ]; then
        echo "✅ $REPO already exists"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        git clone "https://github.com/$GITHUB_USER/$REPO.git"
        if [ $? -eq 0 ]; then
            echo "✅ Successfully cloned $REPO"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
        else
            echo "❌ Failed to clone $REPO"
            FAIL_COUNT=$((FAIL_COUNT + 1))
        fi
    fi
done

echo ""
echo "📊 Role Portals Summary: $SUCCESS_COUNT successful, $FAIL_COUNT failed"
