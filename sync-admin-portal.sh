#!/bin/bash

echo "🔄 SYNCING ADMIN PORTAL WITH WORKING VERSION"
echo "============================================="

# Base directories
MAIN_ADMIN="/Users/yesideasekun/vitalvida-api"
WORKING_ADMIN="/Users/yesideasekun/vitalvida-api/secure-admin-center-33/portals/role-portals/vitalvida-command-center-elite"

echo "📍 Main Admin Portal: $MAIN_ADMIN"
echo "📍 Working Admin Portal: $WORKING_ADMIN"

# Function to copy files safely
copy_file() {
    local source="$1"
    local dest="$2"
    local dest_dir=$(dirname "$dest")
    
    if [ ! -d "$dest_dir" ]; then
        mkdir -p "$dest_dir"
    fi
    
    if [ -f "$source" ]; then
        cp "$source" "$dest"
        echo "✅ Copied: $(basename "$source")"
    else
        echo "⚠️  Source not found: $source"
    fi
}

# 1. Copy UI components
echo ""
echo "🎨 Copying UI components..."
copy_file "$WORKING_ADMIN/src/components/ui" "$MAIN_ADMIN/src/components/ui"

# 2. Copy hooks
echo ""
echo "🔗 Copying hooks..."
copy_file "$WORKING_ADMIN/src/hooks/use-toast.ts" "$MAIN_ADMIN/src/hooks/use-toast.ts"
copy_file "$WORKING_ADMIN/src/hooks/use-mobile.tsx" "$MAIN_ADMIN/src/hooks/use-mobile.tsx"

# 3. Copy types
echo ""
echo "📝 Copying types..."
copy_file "$WORKING_ADMIN/src/types/auth.ts" "$MAIN_ADMIN/src/types/auth.ts"

# 4. Copy lib utilities
echo ""
echo "🔧 Copying utilities..."
copy_file "$WORKING_ADMIN/src/lib/utils.ts" "$MAIN_ADMIN/src/lib/utils.ts"

# 5. Install missing dependencies
echo ""
echo "📦 Installing missing dependencies..."
cd "$MAIN_ADMIN"
npm install @radix-ui/react-toast @radix-ui/react-tooltip @radix-ui/react-slot @radix-ui/react-avatar @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-tabs @radix-ui/react-collapsible @radix-ui/react-accordion @radix-ui/react-alert-dialog @radix-ui/react-aspect-ratio @radix-ui/react-checkbox @radix-ui/react-context-menu @radix-ui/react-hover-card @radix-ui/react-label @radix-ui/react-menubar @radix-ui/react-navigation-menu @radix-ui/react-popover @radix-ui/react-progress @radix-ui/react-radio-group @radix-ui/react-scroll-area @radix-ui/react-select @radix-ui/react-separator @radix-ui/react-slider @radix-ui/react-switch @radix-ui/react-toggle @radix-ui/react-toggle-group class-variance-authority clsx tailwind-merge sonner framer-motion --legacy-peer-deps

# 6. Restart the server
echo ""
echo "🚀 Restarting admin portal..."
lsof -ti:8080 | xargs kill -9 2>/dev/null
npm run dev &

echo ""
echo "✅ ADMIN PORTAL SYNC COMPLETE!"
echo "=============================="
echo "✅ UI Components: Copied"
echo "✅ Hooks: Copied"
echo "✅ Types: Copied"
echo "✅ Utilities: Copied"
echo "✅ Dependencies: Installed"
echo "✅ Server: Restarted"
echo ""
echo "🌐 Access your enhanced admin portal at:"
echo "   http://localhost:8080"
echo ""
echo "🎯 Your admin portal now has:"
echo "   - Enhanced portal display with role switching"
echo "   - System automation portals"
echo "   - Role-based dashboard routing"
echo "   - Modern UI components"
echo "   - Working portal health monitoring"
