#!/bin/bash

echo "🚀 VITALVIDA ERP COMPLETE SETUP SCRIPT"
echo "======================================"
echo ""

# Set base directory
BASE_DIR="/Users/yesideasekun/vitalvida-api/secure-admin-center-33"
cd "$BASE_DIR"

# Step 1: Create directory structure
echo "📁 Step 1: Creating directory structure..."
bash ../setup-portal-structure.sh

# Step 2: Clone role portals
echo ""
echo "🎯 Step 2: Cloning role-based portals..."
bash ../clone-role-portals.sh

# Step 3: Clone automation portals
echo ""
echo "🤖 Step 3: Cloning automation portals..."
bash ../clone-automation-portals.sh

# Step 4: Install dependencies
echo ""
echo "📦 Step 4: Installing dependencies..."
bash ../install-all-dependencies.sh

# Step 5: Configure ports
echo ""
echo "⚙️ Step 5: Configuring portal ports..."
bash ../configure-portal-ports.sh

# Step 6: Start all portals
echo ""
echo "🚀 Step 6: Starting all portals..."
bash ../start-all-portals-pm2.sh

# Step 7: Verify status
echo ""
echo "✅ Step 7: Verifying portal status..."
sleep 10
pm2 status

echo ""
echo "======================================"
echo "✅ SETUP COMPLETE!"
echo ""
echo "📊 Portal Locations:"
echo "  Role Portals: $BASE_DIR/portals/role-portals/"
echo "  Automation: $BASE_DIR/portals/automation-portals/"
echo ""
echo "🌐 Admin Center: http://localhost:8080"
echo "📋 Portal Status: pm2 status"
echo "📝 Portal Logs: pm2 logs"
echo ""
echo "🎯 The admin interface will now show:"
echo "  - Enter As Role: 13 portals"
echo "  - System Automation: 6 portals"
