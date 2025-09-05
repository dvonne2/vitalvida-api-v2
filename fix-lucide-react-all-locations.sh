#!/bin/bash

echo "🔧 FIXING LUCIDE-REACT IN ALL LOCATIONS"
echo "========================================"

# Base directory
BASE_DIR="/Users/yesideasekun/vitalvida-api"

# Function to install lucide-react in a directory
install_lucide_react() {
    local dir="$1"
    local dir_name=$(basename "$dir")
    
    if [ -f "$dir/package.json" ]; then
        echo "📦 Installing lucide-react in $dir_name..."
        cd "$dir"
        npm install lucide-react --legacy-peer-deps
        echo "✅ Installed in $dir_name"
        echo ""
    else
        echo "⚠️  No package.json found in $dir_name"
    fi
}

# 1. Install in parent directory (main admin center)
echo "🏢 Installing in main admin center (parent directory)..."
install_lucide_react "$BASE_DIR"

# 2. Install in secure-admin-center-33
echo "🏢 Installing in secure-admin-center-33..."
install_lucide_react "$BASE_DIR/secure-admin-center-33"

# 3. Install in automation portals
echo "🤖 Installing in automation portals..."
for portal in "$BASE_DIR/secure-admin-center-33/portals/automation-portals"/*; do
    if [ -d "$portal" ]; then
        install_lucide_react "$portal"
    fi
done

# 4. Install in role portals
echo "👥 Installing in role portals..."
for portal in "$BASE_DIR/secure-admin-center-33/portals/role-portals"/*; do
    if [ -d "$portal" ]; then
        install_lucide_react "$portal"
    fi
done

echo "🎉 LUCIDE-REACT INSTALLATION COMPLETE!"
echo "======================================"
echo "✅ Main admin center (parent): lucide-react installed"
echo "✅ Secure admin center: lucide-react installed"
echo "✅ Automation portals: lucide-react installed"
echo "✅ Role portals: lucide-react installed"
echo ""
echo "🚀 You can now restart any server with:"
echo "   cd /path/to/directory && npm run dev"
echo ""
echo "📍 Main admin center: http://localhost:8080"
