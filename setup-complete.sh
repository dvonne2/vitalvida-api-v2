#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}     VITALVIDA ERP - COMPLETE PORTAL SETUP${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# Base directory
BASE_DIR="/Users/yesideasekun/vitalvida-api"
PORTALS_DIR="$BASE_DIR/secure-admin-center-33/portals"

# Arrays of portals
ROLE_PORTALS=(
    "vitalvida-accountability-portal"
    "vitalvida-inventory-guardian"
    "vitalvida-telesales"
    "delivery-agent-logistics-hub"
    "vitalvida-compliance-guard"
    "vitalvida-command-center-elite"
    "general-manager"
    "vitalvida-ceo-center-africa"
    "vitalvida-zenith-hr"
    "vitalvida-investor-cockpit"
    "first-bank-fin-control"
    "vitalvida-cost-brain"
    "nigeria-market-pulse"
)

AUTOMATION_PORTALS=(
    "kemi-flow-ai"
    "vitalvida-agent-suite"
    "vitalvida-joy-books"
    "vitalvida-shield-system"
    "vital-brain-ng"
)

# Port assignments
declare -A PORTAL_PORTS=(
    ["vitalvida-accountability-portal"]=3004
    ["vitalvida-inventory-guardian"]=3005
    ["vitalvida-telesales"]=3006
    ["delivery-agent-logistics-hub"]=3007
    ["vitalvida-compliance-guard"]=3008
    ["vitalvida-command-center-elite"]=3009
    ["general-manager"]=3010
    ["vitalvida-ceo-center-africa"]=3011
    ["vitalvida-investor-cockpit"]=3013
    ["first-bank-fin-control"]=3014
    ["kemi-flow-ai"]=3015
    ["vitalvida-agent-suite"]=3016
    ["vitalvida-joy-books"]=3017
    ["vitalvida-shield-system"]=3018
    ["vitalvida-zenith-hr"]=3012
    ["vitalvida-cost-brain"]=3019
    ["nigeria-market-pulse"]=3020
    ["vital-brain-ng"]=3021
)

# Function to install dependencies
install_dependencies() {
    local portal=$1
    local portal_path=$2
    
    echo -e "${YELLOW}📦 Installing dependencies for ${portal}...${NC}"
    
    cd "$portal_path" || return 1
    
    # Check if it's a Node.js project
    if [ -f "package.json" ]; then
        if [ ! -d "node_modules" ] || [ ! -f "package-lock.json" ]; then
            echo -e "${BLUE}   Running npm install...${NC}"
            npm install --legacy-peer-deps 2>/dev/null || npm install
        else
            echo -e "${GREEN}   ✓ Dependencies already installed${NC}"
        fi
    fi
    
    # Check if it's a Laravel project
    if [ -f "composer.json" ]; then
        if [ ! -d "vendor" ]; then
            echo -e "${BLUE}   Running composer install...${NC}"
            composer install --no-interaction 2>/dev/null
        else
            echo -e "${GREEN}   ✓ Composer dependencies already installed${NC}"
        fi
        
        # Laravel specific setup
        if [ -f "artisan" ]; then
            # Copy .env if not exists
            if [ ! -f ".env" ] && [ -f ".env.example" ]; then
                cp .env.example .env
                php artisan key:generate 2>/dev/null
            fi
        fi
    fi
    
    return 0
}

# Function to update Vite config for correct port
update_vite_config() {
    local portal=$1
    local port=$2
    local portal_path=$3
    
    if [ -f "$portal_path/vite.config.js" ] || [ -f "$portal_path/vite.config.ts" ]; then
        echo -e "${BLUE}   Configuring Vite to use port ${port}...${NC}"
        
        # Create a backup if not exists
        if [ -f "$portal_path/vite.config.js" ]; then
            CONFIG_FILE="$portal_path/vite.config.js"
        else
            CONFIG_FILE="$portal_path/vite.config.ts"
        fi
        
        # Check if port is already configured
        if ! grep -q "port: ${port}" "$CONFIG_FILE"; then
            # Add server config if not present
            if ! grep -q "server:" "$CONFIG_FILE"; then
                # Add server configuration before the last closing brace
                sed -i.bak '/export default/,/^}/{ 
                    /^}/i\
  server: {\
    port: '"${port}"',\
    host: true,\
    strictPort: true\
  },
                }' "$CONFIG_FILE"
            fi
        fi
    fi
}

# Counter for progress
TOTAL_PORTALS=$((${#ROLE_PORTALS[@]} + ${#AUTOMATION_PORTALS[@]}))
CURRENT=0

echo -e "${BLUE}📋 Total portals to setup: ${TOTAL_PORTALS}${NC}\n"

# Process Role Portals
echo -e "${GREEN}═══ SETTING UP ROLE PORTALS ═══${NC}\n"
for portal in "${ROLE_PORTALS[@]}"; do
    CURRENT=$((CURRENT + 1))
    echo -e "${BLUE}[${CURRENT}/${TOTAL_PORTALS}] Processing: ${portal}${NC}"
    
    PORTAL_PATH="$PORTALS_DIR/role-portals/$portal"
    
    if [ -d "$PORTAL_PATH" ]; then
        install_dependencies "$portal" "$PORTAL_PATH"
        
        # Update Vite config if port is assigned
        if [ -n "${PORTAL_PORTS[$portal]}" ]; then
            update_vite_config "$portal" "${PORTAL_PORTS[$portal]}" "$PORTAL_PATH"
        fi
        
        echo -e "${GREEN}   ✅ ${portal} setup complete${NC}\n"
    else
        echo -e "${RED}   ❌ Directory not found: $PORTAL_PATH${NC}\n"
    fi
done

# Process Automation Portals
echo -e "${GREEN}═══ SETTING UP AUTOMATION PORTALS ═══${NC}\n"
for portal in "${AUTOMATION_PORTALS[@]}"; do
    CURRENT=$((CURRENT + 1))
    echo -e "${BLUE}[${CURRENT}/${TOTAL_PORTALS}] Processing: ${portal}${NC}"
    
    PORTAL_PATH="$PORTALS_DIR/automation-portals/$portal"
    
    if [ -d "$PORTAL_PATH" ]; then
        install_dependencies "$portal" "$PORTAL_PATH"
        
        # Update Vite config if port is assigned
        if [ -n "${PORTAL_PORTS[$portal]}" ]; then
            update_vite_config "$portal" "${PORTAL_PORTS[$portal]}" "$PORTAL_PATH"
        fi
        
        echo -e "${GREEN}   ✅ ${portal} setup complete${NC}\n"
    else
        echo -e "${RED}   ❌ Directory not found: $PORTAL_PATH${NC}\n"
    fi
done

# Setup Admin Center
echo -e "${YELLOW}═══ SETTING UP ADMIN CENTER ═══${NC}\n"
ADMIN_PATH="$BASE_DIR/secure-admin-center-33"
if [ -d "$ADMIN_PATH" ]; then
    echo -e "${BLUE}Setting up secure-admin-center-33...${NC}"
    cd "$ADMIN_PATH"
    
    if [ ! -d "node_modules" ]; then
        npm install
    fi
    
    # Ensure it runs on port 8080
    update_vite_config "secure-admin-center-33" "8080" "$ADMIN_PATH"
    
    echo -e "${GREEN}✅ Admin Center setup complete${NC}\n"
else
    echo -e "${RED}❌ Admin Center not found at: $ADMIN_PATH${NC}\n"
fi

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ SETUP COMPLETE!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

echo -e "${YELLOW}Next steps:${NC}"
echo -e "1. Run ${GREEN}bash start-all-portals.sh${NC} to start all portals"
echo -e "2. Or run ${GREEN}bash verify-portals.sh${NC} to check status"
echo -e "3. Access Admin Center at ${BLUE}http://localhost:8080${NC}\n"
