#!/bin/bash

# Script to fix iframe headers for all portals
echo "🔧 Fixing iframe headers for all portals..."

# Array of portal directories and their ports
declare -A portals=(
    ["accountant"]="3008"
    ["logistics"]="3013"
    ["investor"]="3014"
    ["vitalvida-crm"]="3015"
    ["ceo"]="3011"
    ["vitalvida-books"]="3016"
    ["manufacturing"]="3017"
    ["gm"]="3018"
    ["vitalvida-inventory"]="3019"
    ["hr"]="3020"
    ["financial-controller"]="3021"
    ["delivery-agent"]="3022"
    ["telesales"]="3023"
    ["media-buyer"]="3024"
)

# Fix each portal
for portal in "${!portals[@]}"; do
    port="${portals[$portal]}"
    config_file="$portal/vite.config.ts"
    
    if [ -f "$config_file" ]; then
        echo "📝 Updating $portal (port $port)..."
        
        # Replace X-Frame-Options and update CSP
        sed -i.bak "s/'X-Frame-Options': 'SAMEORIGIN'/'X-Frame-Options': 'ALLOWALL'/g" "$config_file"
        sed -i.bak "s/'Access-Control-Allow-Origin': 'http:\/\/localhost:8080'/'Access-Control-Allow-Origin': '\*'/g" "$config_file"
        sed -i.bak "s/frame-ancestors 'self' http:\/\/localhost:8080 http:\/\/127.0.0.1:8080;/frame-ancestors 'self' http:\/\/localhost:8080 http:\/\/127.0.0.1:8080 http:\/\/localhost:$port;/g" "$config_file"
        
        echo "✅ $portal updated"
    else
        echo "⚠️  $config_file not found, skipping..."
    fi
done

echo "🎉 All portals updated! Restart your portals to apply changes."
