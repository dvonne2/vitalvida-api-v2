#!/bin/bash

echo "🎯 VITALVIDA PORTAL CLONE VERIFICATION"
echo "======================================"
echo ""

# Base directory
BASE_DIR="/Users/yesideasekun/vitalvida-api"
cd "$BASE_DIR"

# Portal definitions
declare -A PORTALS=(
  ["logistics"]="vitalvida-accountability-portal:3004"
  ["inventoryManagement"]="vitalvida-inventory-guardian:3005"
  ["telesales"]="vitalvida-telesales:3006"
  ["delivery"]="delivery-agent-logistics-hub:3007"
  ["accountant"]="vitalvida-compliance-guard:3008"
  ["cfo"]="vitalvida-command-center-elite:3009"
  ["gm"]="general-manager:3010"
  ["ceo"]="vitalvida-ceo-center-africa:3011"
  ["investor"]="vitalvida-investor-cockpit:3013"
  ["finance"]="first-bank-fin-control:3014"
  ["hr"]="vitalvida-zenith-hr:3012"
  ["manufacturing"]="vitalvida-cost-brain:3019"
  ["mediaBuyer"]="nigeria-market-pulse:3020"
  ["crm"]="kemi-flow-ai:3015"
  ["inventoryAgent"]="vitalvida-agent-suite:3016"
  ["books"]="vitalvida-joy-books:3017"
  ["kyc"]="vitalvida-shield-system:3018"
  ["marketing"]="vital-brain-ng:3021"
)

# Initialize counters
cloned=0
running=0
notCloned=0
clonedButNotRunning=0

echo "🔍 Checking each portal..."
echo ""

for key in "${!PORTALS[@]}"; do
  IFS=':' read -r folder port <<< "${PORTALS[$key]}"
  
  echo "Checking: $key"
  echo "  Folder: $folder"
  echo "  Port: $port"
  
  # Check if folder exists
  if [ -d "$folder" ]; then
    echo "  ✅ Folder exists"
    folderExists=true
    
    # Check if package.json exists
    if [ -f "$folder/package.json" ]; then
      echo "  ✅ package.json exists"
      packageJsonExists=true
    else
      echo "  ❌ package.json missing"
      packageJsonExists=false
    fi
    
    # Check if server is running
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost:$port" | grep -q "200\|304"; then
      echo "  ✅ Server running on port $port"
      serverRunning=true
      ((running++))
    else
      echo "  ⚠️ Server not running on port $port"
      serverRunning=false
      ((clonedButNotRunning++))
    fi
    
    ((cloned++))
    
    # Determine status
    if [ "$serverRunning" = true ]; then
      status="✅ Cloned & Running"
    else
      status="⚠️ Cloned but Not Running"
    fi
    
  else
    echo "  ❌ Folder does not exist"
    folderExists=false
    packageJsonExists=false
    serverRunning=false
    status="❌ Not Cloned"
    ((notCloned++))
  fi
  
  echo "  Status: $status"
  echo ""
done

echo "📊 SUMMARY"
echo "==========="
echo "✅ Cloned & Running: $running"
echo "⚠️ Cloned but Not Running: $clonedButNotRunning"
echo "❌ Not Cloned: $notCloned"
echo "📁 Total Portals: ${#PORTALS[@]}"
echo ""

echo "🎯 RECOMMENDATIONS:"
echo "==================="

if [ $notCloned -gt 0 ]; then
  echo "1. Clone missing portals:"
  for key in "${!PORTALS[@]}"; do
    IFS=':' read -r folder port <<< "${PORTALS[$key]}"
    if [ ! -d "$folder" ]; then
      echo "   git clone https://github.com/dvonne2/$folder.git"
    fi
  done
  echo ""
fi

if [ $clonedButNotRunning -gt 0 ]; then
  echo "2. Start servers for cloned portals:"
  for key in "${!PORTALS[@]}"; do
    IFS=':' read -r folder port <<< "${PORTALS[$key]}"
    if [ -d "$folder" ] && ! curl -s -o /dev/null -w "%{http_code}" "http://localhost:$port" | grep -q "200\|304"; then
      echo "   cd $folder && npm run dev"
    fi
  done
  echo ""
fi

echo "3. All portals should be accessible on their respective ports (3004-3021)"
echo "4. Each portal should have a package.json file for npm scripts"
echo ""

echo "🚀 Ready for portal setup!"
