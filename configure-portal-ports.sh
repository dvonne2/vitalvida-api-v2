#!/bin/bash

echo "⚙️ Configuring Ports for All Portals..."
echo "========================================"

# Portal to Port Mapping
PORT_MAP=(
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
    "kemi-flow-ai:3015"
    "vitalvida-agent-suite:3016"
    "vitalvida-joy-books:3017"
    "vitalvida-shield-system:3018"
    "vitalvida-cost-brain:3019"
    "nigeria-market-pulse:3020"
    "vital-brain-ng:3021"
)

BASE_DIR="/Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals"

# Configure each portal
for PORTAL_ENTRY in "${PORT_MAP[@]}"; do
    IFS=':' read -r PORTAL PORT <<< "$PORTAL_ENTRY"
    
    # Find portal in either role or automation directory
    if [ -d "$BASE_DIR/role-portals/$PORTAL" ]; then
        PORTAL_DIR="$BASE_DIR/role-portals/$PORTAL"
    elif [ -d "$BASE_DIR/automation-portals/$PORTAL" ]; then
        PORTAL_DIR="$BASE_DIR/automation-portals/$PORTAL"
    else
        echo "⚠️ Portal $PORTAL not found"
        continue
    fi
    
    echo "⚙️ Configuring $PORTAL for port $PORT..."
    cd "$PORTAL_DIR"
    
    # Update package.json to use correct port
    if [ -f "package.json" ]; then
        node -e "
        const fs = require('fs');
        const pkg = JSON.parse(fs.readFileSync('package.json'));
        pkg.scripts = pkg.scripts || {};
        pkg.scripts.dev = 'vite --port $PORT --host';
        fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2));
        "
        echo "✅ $PORTAL configured for port $PORT"
    fi
done

echo ""
echo "✅ All portal ports configured!"
