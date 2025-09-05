#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}     VITALVIDA ERP - PORTAL STATUS VERIFICATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

BASE_DIR="/Users/yesideasekun/vitalvida-api"
PORTALS_DIR="$BASE_DIR/secure-admin-center-33/portals"

# Arrays and port mappings
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

PORTAL_PORTS=(
    "vitalvida-accountability-portal:3004"
    "vitalvida-inventory-guardian:3005"
    "vitalvida-telesales:3006"
    "delivery-agent-logistics-hub:3007"
    "vitalvida-compliance-guard:3008"
    "vitalvida-command-center-elite:3009"
    "general-manager:3010"
    "vitalvida-ceo-center-africa:3011"
    "vitalvida-investor-cockpit:3013"
    "first-bank-fin-control:3014"
    "kemi-flow-ai:3015"
    "vitalvida-agent-suite:3016"
    "vitalvida-joy-books:3017"
    "vitalvida-shield-system:3018"
    "vitalvida-zenith-hr:3012"
    "vitalvida-cost-brain:3019"
    "nigeria-market-pulse:3020"
    "vital-brain-ng:3021"
)

# Function to check portal status
check_portal_status() {
    local portal=$1
    local portal_path=$2
    local portal_type=$3
    
    # Find port for this portal
    local port=""
    for portal_entry in "${PORTAL_PORTS[@]}"; do
        IFS=':' read -r portal_name portal_port <<< "$portal_entry"
        if [ "$portal_name" = "$portal" ]; then
            port="$portal_port"
            break
        fi
    done
    
    printf "%-35s" "$portal"
    
    # Check if cloned
    if [ -d "$portal_path" ]; then
        echo -ne "${GREEN}✅${NC} "
    else
        echo -ne "${RED}❌${NC} "
        echo ""
        return
    fi
    
    # Check dependencies
    if [ -f "$portal_path/package.json" ]; then
        if [ -d "$portal_path/node_modules" ]; then
            echo -ne "${GREEN}✅${NC} "
        else
            echo -ne "${YELLOW}⚠️${NC}  "
        fi
    elif [ -f "$portal_path/composer.json" ]; then
        if [ -d "$portal_path/vendor" ]; then
            echo -ne "${GREEN}✅${NC} "
        else
            echo -ne "${YELLOW}⚠️${NC}  "
        fi
    else
        echo -ne "${YELLOW}-${NC}  "
    fi
    
    # Check if running
    if [ -n "$port" ]; then
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
            echo -ne "${GREEN}✅${NC} "
            echo -ne "  ${GREEN}$port${NC}"
        else
            echo -ne "${RED}❌${NC} "
            echo -ne "  ${YELLOW}$port${NC}"
        fi
    else
        echo -ne "${YELLOW}-${NC}  "
        echo -ne "  ${RED}N/A${NC}"
    fi
    
    echo ""
}

# Print header
printf "%-35s %s %s %s %s\n" "Portal Name" "Cloned" "Deps" "Running" "Port"
echo "═══════════════════════════════════════════════════════════════════════"

# Check Role Portals
echo -e "${BLUE}ROLE PORTALS:${NC}"
for portal in "${ROLE_PORTALS[@]}"; do
    check_portal_status "$portal" "$PORTALS_DIR/role-portals/$portal" "role"
done

echo ""
echo -e "${BLUE}AUTOMATION PORTALS:${NC}"
for portal in "${AUTOMATION_PORTALS[@]}"; do
    check_portal_status "$portal" "$PORTALS_DIR/automation-portals/$portal" "automation"
done

echo ""
echo -e "${BLUE}ADMIN CENTER:${NC}"
check_portal_status "secure-admin-center-33" "$BASE_DIR/secure-admin-center-33" "admin"

# Summary
echo ""
echo "═══════════════════════════════════════════════════════════════════════"

# Count statistics
CLONED_COUNT=0
DEPS_COUNT=0
RUNNING_COUNT=0

for portal in "${ROLE_PORTALS[@]}" "${AUTOMATION_PORTALS[@]}"; do
    portal_path="$PORTALS_DIR/role-portals/$portal"
    [ ! -d "$portal_path" ] && portal_path="$PORTALS_DIR/automation-portals/$portal"
    
    [ -d "$portal_path" ] && ((CLONED_COUNT++))
    [ -d "$portal_path/node_modules" ] || [ -d "$portal_path/vendor" ] && ((DEPS_COUNT++))
    
    # Find port for this portal
    port=""
    for portal_entry in "${PORTAL_PORTS[@]}"; do
        IFS=':' read -r portal_name portal_port <<< "$portal_entry"
        if [ "$portal_name" = "$portal" ]; then
            port="$portal_port"
            break
        fi
    done
    
    [ -n "$port" ] && lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1 && ((RUNNING_COUNT++))
done

TOTAL_PORTALS=$((${#ROLE_PORTALS[@]} + ${#AUTOMATION_PORTALS[@]}))

echo -e "${GREEN}SUMMARY:${NC}"
echo -e "  Cloned:       ${CLONED_COUNT}/${TOTAL_PORTALS}"
echo -e "  Dependencies: ${DEPS_COUNT}/${TOTAL_PORTALS}"
echo -e "  Running:      ${RUNNING_COUNT}/${TOTAL_PORTALS}"
echo ""

# Recommendations
if [ $DEPS_COUNT -lt $TOTAL_PORTALS ]; then
    echo -e "${YELLOW}⚠️  Some portals are missing dependencies.${NC}"
    echo -e "   Run: ${GREEN}bash setup-complete.sh${NC} to install all dependencies"
fi

if [ $RUNNING_COUNT -eq 0 ]; then
    echo -e "${YELLOW}⚠️  No portals are currently running.${NC}"
    echo -e "   Run: ${GREEN}bash start-all-portals.sh${NC} to start all portals"
fi

echo ""
