#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 RECOVERING VITALVIDA ADMIN CENTER${NC}"
echo -e "${BLUE}====================================${NC}"

# 1. Kill everything
echo -e "${YELLOW}🛑 Stopping all processes...${NC}"
for port in 8080 8081 3000 5173; do
    lsof -ti:$port | xargs kill -9 2>/dev/null
done

sleep 2

# 2. Navigate to correct directory
cd /Users/yesideasekun/vitalvida-api/secure-admin-center-33

# 3. Verify repository
echo -e "${YELLOW}📍 Verifying repository...${NC}"
REMOTE_URL=$(git remote get-url origin)
if [[ $REMOTE_URL == *"dvonne2/secure-admin-center-33"* ]]; then
    echo -e "${GREEN}✅ Correct repository confirmed${NC}"
else
    echo -e "${RED}❌ WRONG REPOSITORY: $REMOTE_URL${NC}"
    echo -e "${RED}Please clone the correct repo!${NC}"
    exit 1
fi

# 4. Get latest code
echo -e "${YELLOW}📥 Pulling latest changes...${NC}"
git pull origin main || git pull origin master

# 5. Clean build
echo -e "${YELLOW}🧹 Cleaning old build...${NC}"
rm -rf node_modules package-lock.json dist .vite

# 6. Install dependencies
echo -e "${YELLOW}📦 Installing dependencies...${NC}"
npm install

# 7. Install required packages for portal system
echo -e "${YELLOW}📦 Installing portal dependencies...${NC}"
npm install lucide-react sonner @radix-ui/react-collapsible framer-motion

# 8. Create missing directories
echo -e "${YELLOW}📁 Creating missing directories...${NC}"
mkdir -p src/components/layout
mkdir -p src/components/portal
mkdir -p src/config
mkdir -p src/hooks
mkdir -p src/utils

# 9. Create portal configuration
echo -e "${YELLOW}⚙️ Creating portal configuration...${NC}"
cat > src/config/portalConfig.ts << 'EOF'
export interface PortalConfig {
  id: string;
  name: string;
  url: string;
  category: 'role' | 'system';
  icon?: string;
  color?: string;
}

export const PORTAL_MAP: Record<string, PortalConfig> = {
  logistics: {
    id: 'logistics',
    name: 'Logistics Portal',
    url: 'http://localhost:3004',
    category: 'role',
    color: '#00B8D4'
  },
  inventory: {
    id: 'inventory',
    name: 'Inventory Portal',
    url: 'http://localhost:3005',
    category: 'role',
    color: '#4CAF50'
  },
  telesales: {
    id: 'telesales',
    name: 'Telesales Portal',
    url: 'http://localhost:3006',
    category: 'role',
    color: '#9C27B0'
  },
  delivery: {
    id: 'delivery',
    name: 'Delivery Portal',
    url: 'http://localhost:3007',
    category: 'role',
    color: '#FF9800'
  },
  accountant: {
    id: 'accountant',
    name: 'Accountant Portal',
    url: 'http://localhost:3008',
    category: 'role',
    color: '#795548'
  },
  cfo: {
    id: 'cfo',
    name: 'CFO Portal',
    url: 'http://localhost:3009',
    category: 'role',
    color: '#2196F3'
  },
  gm: {
    id: 'gm',
    name: 'General Manager',
    url: 'http://localhost:3010',
    category: 'role',
    color: '#607D8B'
  },
  ceo: {
    id: 'ceo',
    name: 'CEO Portal',
    url: 'http://localhost:3011',
    category: 'role',
    color: '#5E35B1'
  },
  hr: {
    id: 'hr',
    name: 'HR Portal',
    url: 'http://localhost:3012',
    category: 'role',
    color: '#4CAF50'
  },
  investor: {
    id: 'investor',
    name: 'Investor Portal',
    url: 'http://localhost:3013',
    category: 'role',
    color: '#FF5722'
  },
  finance: {
    id: 'finance',
    name: 'Finance Portal',
    url: 'http://localhost:3014',
    category: 'role',
    color: '#009688'
  },
  crm: {
    id: 'crm',
    name: 'CRM Portal',
    url: 'http://localhost:3015',
    category: 'system',
    color: '#E91E63'
  },
  inventoryAgent: {
    id: 'inventoryAgent',
    name: 'Inventory Agent',
    url: 'http://localhost:3016',
    category: 'system',
    color: '#8BC34A'
  },
  books: {
    id: 'books',
    name: 'Books Portal',
    url: 'http://localhost:3017',
    category: 'system',
    color: '#FFC107'
  },
  kyc: {
    id: 'kyc',
    name: 'KYC Portal',
    url: 'http://localhost:3018',
    category: 'system',
    color: '#9C27B0'
  },
  manufacturing: {
    id: 'manufacturing',
    name: 'Manufacturing',
    url: 'http://localhost:3019',
    category: 'role',
    color: '#795548'
  },
  mediaBuyer: {
    id: 'mediaBuyer',
    name: 'Media Buyer',
    url: 'http://localhost:3020',
    category: 'role',
    color: '#FF5722'
  },
  marketing: {
    id: 'marketing',
    name: 'Marketing Portal',
    url: 'http://localhost:3021',
    category: 'system',
    color: '#E91E63'
  }
};

export const getPortalsByCategory = (category: 'role' | 'system') => {
  return Object.values(PORTAL_MAP).filter(portal => portal.category === category);
};
EOF

# 10. Create portal health hook
echo -e "${YELLOW}🔧 Creating portal health hook...${NC}"
cat > src/hooks/usePortalHealth.ts << 'EOF'
import { useState, useEffect } from 'react';

export interface PortalHealth {
  isOnline: boolean;
  isLoading: boolean;
  error?: string;
}

export const usePortalHealth = (url: string) => {
  const [health, setHealth] = useState<PortalHealth>({
    isOnline: false,
    isLoading: true
  });

  useEffect(() => {
    const checkHealth = async () => {
      try {
        setHealth(prev => ({ ...prev, isLoading: true }));
        
        const response = await fetch(url, { 
          method: 'HEAD',
          mode: 'no-cors'
        });
        
        setHealth({
          isOnline: true,
          isLoading: false
        });
      } catch (error) {
        setHealth({
          isOnline: false,
          isLoading: false,
          error: error instanceof Error ? error.message : 'Unknown error'
        });
      }
    };

    checkHealth();
    
    const interval = setInterval(checkHealth, 30000); // Check every 30 seconds
    
    return () => clearInterval(interval);
  }, [url]);

  return health;
};
EOF

# 11. Create iframe portal renderer
echo -e "${YELLOW}🔧 Creating iframe portal renderer...${NC}"
cat > src/components/portal/IframePortalRenderer.tsx << 'EOF'
import React, { useState } from 'react';
import { usePortalHealth } from '../../hooks/usePortalHealth';
import { PortalConfig } from '../../config/portalConfig';

interface IframePortalRendererProps {
  portal: PortalConfig;
  onLoad?: () => void;
  onError?: (error: string) => void;
}

export const IframePortalRenderer: React.FC<IframePortalRendererProps> = ({
  portal,
  onLoad,
  onError
}) => {
  const { isOnline, isLoading, error } = usePortalHealth(portal.url);
  const [iframeLoaded, setIframeLoaded] = useState(false);

  const handleIframeLoad = () => {
    setIframeLoaded(true);
    onLoad?.();
  };

  const handleIframeError = () => {
    onError?.('Failed to load portal');
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (!isOnline) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-center">
          <div className="text-red-500 text-lg font-semibold mb-2">
            Portal Offline
          </div>
          <div className="text-gray-500 text-sm">
            {portal.name} is currently unavailable
          </div>
          {error && (
            <div className="text-red-400 text-xs mt-1">
              Error: {error}
            </div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="h-full w-full">
      <iframe
        src={portal.url}
        className="w-full h-full border-0"
        onLoad={handleIframeLoad}
        onError={handleIframeError}
        sandbox="allow-same-origin allow-scripts allow-forms allow-popups"
        title={portal.name}
      />
    </div>
  );
};
EOF

# 12. Create sidebar component
echo -e "${YELLOW}🔧 Creating sidebar component...${NC}"
cat > src/components/layout/Sidebar.tsx << 'EOF'
import React, { useState } from 'react';
import { ChevronDown, ChevronRight, ExternalLink } from 'lucide-react';
import { PORTAL_MAP, getPortalsByCategory } from '../../config/portalConfig';
import { usePortalHealth } from '../../hooks/usePortalHealth';

interface SidebarProps {
  onPortalSelect: (portal: string) => void;
  selectedPortal?: string;
}

export const Sidebar: React.FC<SidebarProps> = ({
  onPortalSelect,
  selectedPortal
}) => {
  const [expandedSections, setExpandedSections] = useState({
    role: true,
    system: true
  });

  const rolePortals = getPortalsByCategory('role');
  const systemPortals = getPortalsByCategory('system');

  const toggleSection = (section: 'role' | 'system') => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const PortalItem: React.FC<{ portal: any }> = ({ portal }) => {
    const { isOnline } = usePortalHealth(portal.url);
    
    return (
      <div
        className={`flex items-center px-4 py-2 text-sm cursor-pointer hover:bg-gray-100 transition-colors ${
          selectedPortal === portal.id ? 'bg-blue-50 text-blue-600' : 'text-gray-700'
        }`}
        onClick={() => onPortalSelect(portal.id)}
      >
        <div 
          className="w-3 h-3 rounded-full mr-3"
          style={{ backgroundColor: portal.color }}
        />
        <span className="flex-1">{portal.name}</span>
        <div className={`w-2 h-2 rounded-full ${isOnline ? 'bg-green-500' : 'bg-red-500'}`} />
      </div>
    );
  };

  return (
    <div className="w-64 bg-white border-r border-gray-200 h-full overflow-y-auto">
      <div className="p-4 border-b border-gray-200">
        <h2 className="text-lg font-semibold text-gray-800">VitalVida ERP</h2>
        <p className="text-sm text-gray-500">Portal Management</p>
      </div>
      
      <div className="p-2">
        {/* Role Portals */}
        <div className="mb-4">
          <div
            className="flex items-center justify-between px-2 py-2 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50"
            onClick={() => toggleSection('role')}
          >
            <span>Enter As Role</span>
            {expandedSections.role ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          </div>
          
          {expandedSections.role && (
            <div className="ml-2">
              {rolePortals.map(portal => (
                <PortalItem key={portal.id} portal={portal} />
              ))}
            </div>
          )}
        </div>

        {/* System Portals */}
        <div className="mb-4">
          <div
            className="flex items-center justify-between px-2 py-2 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50"
            onClick={() => toggleSection('system')}
          >
            <span>System Automation</span>
            {expandedSections.system ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          </div>
          
          {expandedSections.system && (
            <div className="ml-2">
              {systemPortals.map(portal => (
                <PortalItem key={portal.id} portal={portal} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
EOF

# 13. Start the server
echo -e "${YELLOW}🚀 Starting admin center...${NC}"
npm run dev -- --port 8080 --host &

echo ""
echo -e "${GREEN}✅ Admin center should now be running at http://localhost:8080${NC}"
echo -e "${GREEN}   with YOUR GitHub repository code!${NC}"
echo ""
echo -e "${BLUE}📋 Next steps:${NC}"
echo -e "1. Open http://localhost:8080 in your browser"
echo -e "2. Check the sidebar for portal categories"
echo -e "3. Click on any portal to load it in the iframe"
echo -e "4. Verify all 18 portals are showing correctly"
