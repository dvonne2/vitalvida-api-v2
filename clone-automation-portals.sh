#!/bin/bash

# Navigate to automation portals directory
cd /Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals/automation-portals

echo "🤖 Cloning AUTOMATION PORTALS (System Automation)..."
echo "===================================================="

# Define automation portals
AUTOMATION_PORTALS=(
    "kemi-flow-ai:3015"
    "vitalvida-agent-suite:3016"
    "vitalvida-joy-books:3017"
    "vitalvida-shield-system:3018"
    "vital-brain-ng:3021"
)

GITHUB_USER="dvonne2"
SUCCESS_COUNT=0
FAIL_COUNT=0

for PORTAL in "${AUTOMATION_PORTALS[@]}"; do
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
echo "📊 Automation Portals Summary: $SUCCESS_COUNT successful, $FAIL_COUNT failed"
