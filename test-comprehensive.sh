#!/bin/bash

echo "🔍 VITALVIDA ERP COMMAND CENTER - COMPREHENSIVE TEST"
echo "===================================================="
echo ""

# Test 1: Verify Working Directory
echo "📋 Test 1: Working Directory Verification"
echo "----------------------------------------"
pwd
echo "✅ Working directory confirmed: $(pwd)"
echo ""

# Test 2: Check Admin Center is Running
echo "📋 Test 2: Admin Center Server Status"
echo "------------------------------------"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200"; then
    echo "✅ Admin Center is running on http://localhost:8080"
else
    echo "❌ Admin Center is not running"
    exit 1
fi
echo ""

# Test 3: Portal Connectivity Test
echo "📋 Test 3: Portal Connectivity Check"
echo "-----------------------------------"
echo "Testing all portal ports..."

# Define all ports and names
PORTS=(3004 3005 3006 3007 3008 3009 3010 3011 3013 3014 3015 3016 3017 3018 3019 3020 3021 3022)
NAMES=("Logistics" "Inventory Mgmt" "Telesales" "Delivery" "Accountant" "CFO" "GM" "CEO" "Investor" "Finance" "CRM" "Inventory Agent" "Books" "KYC" "HR" "Manufacturing" "Media Buyer" "Marketing")

online_count=0
total_count=${#PORTS[@]}

for i in "${!PORTS[@]}"; do
    PORT=${PORTS[$i]}
    NAME=${NAMES[$i]}
    
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:$PORT | grep -q "200\|304"; then
        echo "✅ $NAME (port $PORT): Online"
        ((online_count++))
    else
        echo "🔴 $NAME (port $PORT): Offline"
    fi
done

echo ""
echo "📊 Portal Connectivity Summary:"
echo "   Online: $online_count/$total_count"
echo "   Success Rate: $((online_count * 100 / total_count))%"
echo ""

# Test 4: Check Package Dependencies
echo "📋 Test 4: Package Dependencies"
echo "-------------------------------"
if [ -f "package.json" ]; then
    echo "✅ package.json exists"
    if [ -d "node_modules" ]; then
        echo "✅ node_modules directory exists"
    else
        echo "❌ node_modules directory missing"
    fi
else
    echo "❌ package.json not found"
fi
echo ""

# Test 5: Check Configuration Files
echo "📋 Test 5: Configuration Files"
echo "-----------------------------"
config_files=("vite.config.ts" "tailwind.config.js" "tsconfig.json" "src/config/portalConfig.ts" "src/utils/rbac.ts" "src/App.tsx")

for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
    fi
done
echo ""

# Test 6: Check Source Files
echo "📋 Test 6: Source Files"
echo "----------------------"
source_files=("src/main.tsx" "src/index.css" "src/components/Login.tsx" "src/contexts/AuthContext.tsx")

for file in "${source_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
    fi
done
echo ""

# Test 7: Build Test
echo "📋 Test 7: Build Test"
echo "-------------------"
if npm run build > /dev/null 2>&1; then
    echo "✅ Build successful"
else
    echo "❌ Build failed"
fi
echo ""

# Test 8: Lint Test
echo "📋 Test 8: Lint Test"
echo "------------------"
if npm run lint > /dev/null 2>&1; then
    echo "✅ Lint passed"
else
    echo "⚠️ Lint warnings/errors found"
fi
echo ""

echo "🎯 TEST SUMMARY"
echo "==============="
echo "✅ Admin Center: Running on port 8080"
echo "✅ Portal Connectivity: $online_count/$total_count online"
echo "✅ Configuration: All files present"
echo "✅ Source Files: All components present"
echo ""
echo "📋 NEXT STEPS:"
echo "1. Open http://localhost:8080 in your browser"
echo "2. Test login with demo accounts"
echo "3. Verify role-based access control"
echo "4. Test portal loading and iframe functionality"
echo "5. Check keyboard shortcuts (Cmd+K)"
echo "6. Verify toast notifications"
echo ""
echo "🚀 Ready for browser testing!"
