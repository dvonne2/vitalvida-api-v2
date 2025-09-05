#!/bin/bash

echo "🚀 Starting All Portals with PM2..."
echo "===================================="

# Install PM2 globally if not installed
if ! command -v pm2 &> /dev/null; then
    echo "Installing PM2..."
    npm install -g pm2
fi

# Clear any existing PM2 processes
pm2 delete all 2>/dev/null

BASE_DIR="/Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals"

# Portal configurations
PORTALS=(
    "Logistics:role-portals/vitalvida-accountability-portal:3004"
    "Inventory:role-portals/vitalvida-inventory-guardian:3005"
    "Telesales:role-portals/vitalvida-telesales:3006"
    "Delivery:role-portals/delivery-agent-logistics-hub:3007"
    "Accountant:role-portals/vitalvida-compliance-guard:3008"
    "CFO:role-portals/vitalvida-command-center-elite:3009"
    "GM:role-portals/general-manager:3010"
    "CEO:role-portals/vitalvida-ceo-center-africa:3011"
    "HR:role-portals/vitalvida-zenith-hr:3012"
    "Investor:role-portals/vitalvida-investor-cockpit:3013"
    "Finance:role-portals/first-bank-fin-control:3014"
    "Manufacturing:role-portals/vitalvida-cost-brain:3019"
    "MediaBuyer:role-portals/nigeria-market-pulse:3020"
    "CRM:automation-portals/kemi-flow-ai:3015"
    "InventoryAgent:automation-portals/vitalvida-agent-suite:3016"
    "Books:automation-portals/vitalvida-joy-books:3017"
    "KYC:automation-portals/vitalvida-shield-system:3018"
    "Marketing:automation-portals/vital-brain-ng:3021"
)

for PORTAL_ENTRY in "${PORTALS[@]}"; do
    IFS=':' read -r NAME PATH PORT <<< "$PORTAL_ENTRY"
    FULL_PATH="$BASE_DIR/$PATH"
    
    if [ -d "$FULL_PATH" ]; then
        echo "🚀 Starting $NAME on port $PORT..."
        cd "$FULL_PATH"
        pm2 start npm --name "$NAME-$PORT" -- run dev
    else
        echo "⚠️ $NAME directory not found at $FULL_PATH"
    fi
done

# Save PM2 configuration
pm2 save
pm2 startup

echo ""
echo "✅ All portals started!"
echo "📊 View status: pm2 status"
echo "📋 View logs: pm2 logs"
echo "🔄 Restart all: pm2 restart all"
echo "🛑 Stop all: pm2 stop all"
