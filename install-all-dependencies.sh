#!/bin/bash

echo "📦 Installing Dependencies for All Portals..."
echo "============================================="

BASE_DIR="/Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals"

# Role Portals
echo ""
echo "🎯 Installing Role Portal Dependencies..."
for dir in $BASE_DIR/role-portals/*/; do
    if [ -d "$dir" ]; then
        PORTAL_NAME=$(basename "$dir")
        echo "📦 Installing dependencies for $PORTAL_NAME..."
        cd "$dir"
        if [ -f "package.json" ]; then
            npm install --legacy-peer-deps --silent
            echo "✅ $PORTAL_NAME dependencies installed"
        else
            echo "⚠️ No package.json in $PORTAL_NAME"
        fi
    fi
done

# Automation Portals
echo ""
echo "🤖 Installing Automation Portal Dependencies..."
for dir in $BASE_DIR/automation-portals/*/; do
    if [ -d "$dir" ]; then
        PORTAL_NAME=$(basename "$dir")
        echo "📦 Installing dependencies for $PORTAL_NAME..."
        cd "$dir"
        if [ -f "package.json" ]; then
            npm install --legacy-peer-deps --silent
            echo "✅ $PORTAL_NAME dependencies installed"
        else
            echo "⚠️ No package.json in $PORTAL_NAME"
        fi
    fi
done

echo ""
echo "✅ All dependencies installed!"
