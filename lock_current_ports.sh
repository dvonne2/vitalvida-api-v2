#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="/Users/yesideasekun/vitalvida-api"
ADMIN_DIR="$BASE_DIR/secure-admin-center-33"

echo "Locking Current Port Assignments (No Port Hopping)"
echo "===================================================="
echo ""

# macOS bash-compatible list: portal|port
PORTALS=$(cat <<'EOF'
vitalvida-inventory|3004
financial-controller|3005
gm|3006
delivery-agent|3007
accountant|3008
inventory-management|3009
investor|3010
ceo|3011
hr|3012
logistics|3013
manufacturing|3014
media-buyer|3015
telesales|3016
vitalvida-books|3017
vitalvida-crm|3018
vitalvida-marketing|3019
EOF
)

cd "$BASE_DIR"

echo "Current working port assignments:"
printf "%b" "$PORTALS" | while IFS='|' read -r portal port; do
  [[ -z "${portal:-}" ]] && continue
  echo "  $portal → Port $port (LOCKED)"
done

echo ""
echo "Adding iframe headers to existing vite.config.ts files..."

printf "%b" "$PORTALS" | while IFS='|' read -r portal port; do
  [[ -z "${portal:-}" ]] && continue
  if [[ -d "$portal" ]]; then
    echo "Configuring $portal (port $port)..."
    cd "$portal"

    # Detect react plugin
    plugin_pkg="@vitejs/plugin-react-swc"
    if [[ -f package.json ]]; then
      if grep -q '"@vitejs/plugin-react"' package.json; then
        plugin_pkg="@vitejs/plugin-react"
      fi
    fi

    # Backup existing config if present
    if [[ -f vite.config.ts ]]; then
      cp -f vite.config.ts vite.config.ts.backup || true
      chmod u+w vite.config.ts || true
    fi

    cat > vite.config.ts <<EOF
import { defineConfig } from 'vite'
import react from '${plugin_pkg}'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: ${port},
    strictPort: true,
    host: '0.0.0.0',
    cors: true,
    headers: {
      'X-Frame-Options': 'SAMEORIGIN',
      'Content-Security-Policy': "frame-ancestors 'self' http://localhost:8080 http://127.0.0.1:8080;",
      'Access-Control-Allow-Origin': 'http://localhost:8080',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: true
  }
})
EOF

    echo "  ✓ $portal iframe headers configured"
    cd "$BASE_DIR"
  fi
done

echo ""
echo "Updating admin center roleBasedRoutes to use current ports..."
mkdir -p "$ADMIN_DIR/src/routes" || true
cd "$ADMIN_DIR"

cat > src/routes/roleBasedRoutes.tsx <<'EOF'
import React from "react";
import { Route } from "react-router-dom";
import { ProtectedRoute } from "@/components/auth/ProtectedRoute";

// CURRENT WORKING PORT MAP - LOCKED (No Port Hopping)
const PORTAL_MAP: Record<string, { url: string; allowedRoles: string[] }> = {
  "vitalvida-inventory": {
    url: "http://localhost:3004",
    allowedRoles: ["superadmin", "inventory"]
  },
  "financial-controller": {
    url: "http://localhost:3005",
    allowedRoles: ["superadmin", "financial-controller"]
  },
  "gm": {
    url: "http://localhost:3006",
    allowedRoles: ["superadmin", "general-manager"]
  },
  "delivery-agent": {
    url: "http://localhost:3007",
    allowedRoles: ["superadmin", "delivery"]
  },
  "accountant": {
    url: "http://localhost:3008",
    allowedRoles: ["superadmin", "accountant"]
  },
  "inventory-management": {
    url: "http://localhost:3009",
    allowedRoles: ["superadmin", "inventory"]
  },
  "investor": {
    url: "http://localhost:3010",
    allowedRoles: ["superadmin", "investor"]
  },
  "ceo": {
    url: "http://localhost:3011",
    allowedRoles: ["superadmin", "ceo"]
  },
  "hr": {
    url: "http://localhost:3012",
    allowedRoles: ["superadmin", "hr"]
  },
  "logistics": {
    url: "http://localhost:3013",
    allowedRoles: ["superadmin", "logistics"]
  },
  "manufacturing": {
    url: "http://localhost:3014",
    allowedRoles: ["superadmin", "manufacturing"]
  },
  "media-buyer": {
    url: "http://localhost:3015",
    allowedRoles: ["superadmin", "media-buyer"]
  },
  "telesales": {
    url: "http://localhost:3016",
    allowedRoles: ["superadmin", "telesales"]
  },
  "vitalvida-books": {
    url: "http://localhost:3017",
    allowedRoles: ["superadmin"] // System portals - superadmin only
  },
  "vitalvida-crm": {
    url: "http://localhost:3018",
    allowedRoles: ["superadmin"] // System portals - superadmin only
  },
  "vitalvida-marketing": {
    url: "http://localhost:3019",
    allowedRoles: ["superadmin"] // System portals - superadmin only
  }
};

// Enhanced iframe component with loading and error states
const PortalIframe: React.FC<{ src: string; title: string; portalKey: string }> = ({ 
  src, 
  title, 
  portalKey 
}) => {
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState(false);

  return (
    <div style={{ width: "100%", height: "calc(100vh - 120px)", position: "relative" }}>
      {loading && !error && (
        <div style={{
          position: "absolute",
          top: "50%",
          left: "50%",
          transform: "translate(-50%, -50%)",
          textAlign: "center",
          fontSize: "18px",
          color: "#666"
        }}>
          <div style={{ fontSize: "48px", marginBottom: "1rem" }}>🔄</div>
          <div>Loading {title}...</div>
          <div style={{ fontSize: "14px", marginTop: "0.5rem", opacity: 0.7 }}>
            Connecting to {src}
          </div>
        </div>
      )}
      
      {error && (
        <div style={{
          position: "absolute",
          top: "50%",
          left: "50%",
          transform: "translate(-50%, -50%)",
          textAlign: "center",
          color: "#e74c3c"
        }}>
          <div style={{ fontSize: "48px", marginBottom: "1rem" }}>⚠️</div>
          <h3>Portal Unavailable</h3>
          <p>The {title} portal is not running.</p>
          <p style={{ fontSize: "14px", color: "#666", marginBottom: "1rem" }}>
            Expected URL: {src}
          </p>
          <div style={{ fontSize: "12px", color: "#999", marginBottom: "1rem" }}>
            Portal Key: {portalKey}
          </div>
          <button 
            onClick={() => window.location.reload()}
            style={{
              padding: "0.5rem 1rem",
              backgroundColor: "#3498db",
              color: "white",
              border: "none",
              borderRadius: "4px",
              cursor: "pointer"
            }}
          >
            Retry Connection
          </button>
        </div>
      )}
      
      <iframe
        title={title}
        src={src}
        style={{
          width: "100%",
          height: "100%",
          border: "none",
          borderRadius: "8px",
          display: error ? "none" : "block"
        }}
        allow="clipboard-read; clipboard-write; fullscreen; geolocation; microphone; camera"
        sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals"
        onLoad={() => setLoading(false)}
        onError={() => {
          setLoading(false);
          setError(true);
        }}
      />
    </div>
  );
};

// Generate portal routes with current port assignments
export const roleBasedRoutes = Object.entries(PORTAL_MAP).map(([portalKey, config]) => (
  <Route
    key={`portal-${portalKey}`}
    path={`/portal/${portalKey}`}
    element={
      <ProtectedRoute requiredRoles={config.allowedRoles}>
        <PortalIframe 
          src={config.url} 
          title={portalKey.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} 
          portalKey={portalKey}
        />
      </ProtectedRoute>
    }
  />
));

// Export portal map for navigation use
export { PORTAL_MAP };
EOF

echo "Admin center routes updated with current working ports"

echo "Updating AuthContext role definitions..."
mkdir -p "$ADMIN_DIR/src/contexts" || true

cat > src/contexts/AuthContext.tsx <<'EOF'
import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

// Role definitions matching current portal assignments
export const ROLE_DEFINITIONS = {
  superadmin: {
    name: 'superadmin',
    label: 'Super Administrator', 
    description: 'Full system access to all portals and user management',
    canAccessMultiplePortals: true,
    canManageUsers: true,
    assignedPortal: null,
    icon: '🛡️',
    level: 100
  },
  logistics: {
    name: 'logistics',
    label: 'Logistics',
    description: 'Logistics Operations',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'logistics', // Port 3013
    icon: '🚚',
    level: 10
  },
  inventory: {
    name: 'inventory',
    label: 'Inventory',
    description: 'Inventory Management',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'inventory-management', // Port 3009
    icon: '📦',
    level: 10
  },
  telesales: {
    name: 'telesales',
    label: 'Telesales',
    description: 'Telesales Operations',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'telesales', // Port 3016
    icon: '📞',
    level: 10
  },
  delivery: {
    name: 'delivery',
    label: 'Delivery',
    description: 'Delivery Operations',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'delivery-agent', // Port 3007
    icon: '🚛',
    level: 10
  },
  accountant: {
    name: 'accountant',
    label: 'Accountant',
    description: 'Accounting & Finance',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'accountant', // Port 3008
    icon: '📊',
    level: 10
  },
  'financial-controller': {
    name: 'financial-controller',
    label: 'Financial Controller',
    description: 'Financial Management',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'financial-controller', // Port 3005
    icon: '💰',
    level: 10
  },
  'general-manager': {
    name: 'general-manager',
    label: 'General Manager',
    description: 'Executive Management',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'gm', // Port 3006
    icon: '👔',
    level: 10
  },
  ceo: {
    name: 'ceo',
    label: 'CEO',
    description: 'Chief Executive Officer',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'ceo', // Port 3011
    icon: '🏆',
    level: 10
  },
  hr: {
    name: 'hr',
    label: 'HR',
    description: 'Human Resources',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'hr', // Port 3012
    icon: '👥',
    level: 10
  },
  manufacturing: {
    name: 'manufacturing',
    label: 'Manufacturing',
    description: 'Manufacturing Operations',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'manufacturing', // Port 3014
    icon: '⚙️',
    level: 10
  },
  'media-buyer': {
    name: 'media-buyer',
    label: 'Media Buyer',
    description: 'Media Buying & Marketing',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'media-buyer', // Port 3015
    icon: '📢',
    level: 10
  },
  investor: {
    name: 'investor',
    label: 'Investor',
    description: 'Investment Management',
    canAccessMultiplePortals: false,
    canManageUsers: false,
    assignedPortal: 'investor', // Port 3010
    icon: '📈',
    level: 10
  }
};

interface User {
  id: string;
  username: string;
  role: string;
  fullName: string;
  email: string;
  lastLogin?: string;
  isActive: boolean;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  login: (username: string, password: string) => Promise<boolean>;
  logout: () => void;
  loading: boolean;
  canAccessMultiplePortals: () => boolean;
  canManageUsers: () => boolean;
  getAssignedPortal: () => string | null;
  getUserRoleInfo: () => typeof ROLE_DEFINITIONS[keyof typeof ROLE_DEFINITIONS] | null;
  getAccessiblePortals: () => string[];
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Demo users matching current system
  const demoUsers = [
    {
      id: '1',
      username: 'superadmin',
      password: 'superadmin',
      role: 'superadmin',
      fullName: 'Super Administrator',
      email: 'admin@vitalvida.com',
      isActive: true
    },
    {
      id: '2',
      username: 'admin',
      password: 'admin',
      role: 'general-manager',
      fullName: 'General Manager',
      email: 'gm@vitalvida.com',
      isActive: true
    },
    {
      id: '3',
      username: 'manager',
      password: 'manager',
      role: 'logistics',
      fullName: 'Logistics Manager',
      email: 'logistics@vitalvida.com',
      isActive: true
    },
    {
      id: '4',
      username: 'user',
      password: 'user',
      role: 'telesales',
      fullName: 'Telesales User',
      email: 'telesales@vitalvida.com',
      isActive: true
    }
  ];

  // Check for existing session on mount
  useEffect(() => {
    const checkExistingSession = () => {
      try {
        const storedUser = localStorage.getItem('vitalvida_user');
        if (storedUser) {
          const parsedUser = JSON.parse(storedUser);
          setUser(parsedUser);
        }
      } catch (error) {
        console.error('Error loading stored user:', error);
        localStorage.removeItem('vitalvida_user');
      } finally {
        setLoading(false);
      }
    };

    checkExistingSession();
  }, []);

  const login = async (username: string, password: string): Promise<boolean> => {
    try {
      setLoading(true);
      
      const foundUser = demoUsers.find(u => 
        u.username === username && u.password === password && u.isActive
      );
      
      if (foundUser) {
        const userWithoutPassword = {
          id: foundUser.id,
          username: foundUser.username,
          role: foundUser.role,
          fullName: foundUser.fullName,
          email: foundUser.email,
          lastLogin: new Date().toISOString(),
          isActive: foundUser.isActive
        };
        
        setUser(userWithoutPassword);
        localStorage.setItem('vitalvida_user', JSON.stringify(userWithoutPassword));
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('Login error:', error);
      return false;
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem('vitalvida_user');
  };

  const canAccessMultiplePortals = (): boolean => {
    if (!user) return false;
    const roleInfo = ROLE_DEFINITIONS[user.role as keyof typeof ROLE_DEFINITIONS];
    return roleInfo?.canAccessMultiplePortals || false;
  };

  const canManageUsers = (): boolean => {
    if (!user) return false;
    const roleInfo = ROLE_DEFINITIONS[user.role as keyof typeof ROLE_DEFINITIONS];
    return roleInfo?.canManageUsers || false;
  };

  const getAssignedPortal = (): string | null => {
    if (!user) return null;
    const roleInfo = ROLE_DEFINITIONS[user.role as keyof typeof ROLE_DEFINITIONS];
    return roleInfo?.assignedPortal || null;
  };

  const getUserRoleInfo = () => {
    if (!user) return null;
    return ROLE_DEFINITIONS[user.role as keyof typeof ROLE_DEFINITIONS] || null;
  };

  const getAccessiblePortals = (): string[] => {
    if (!user) return [];
    
    if (canAccessMultiplePortals()) {
      return [
        'vitalvida-inventory', 'financial-controller', 'gm', 'delivery-agent',
        'accountant', 'inventory-management', 'investor', 'ceo', 'hr', 'logistics',
        'manufacturing', 'media-buyer', 'telesales', 'vitalvida-books',
        'vitalvida-crm', 'vitalvida-marketing'
      ];
    } else {
      const assignedPortal = getAssignedPortal();
      return assignedPortal ? [assignedPortal] : [];
    }
  };

  const contextValue: AuthContextType = {
    user,
    isAuthenticated: !!user,
    login,
    logout,
    loading,
    canAccessMultiplePortals,
    canManageUsers,
    getAssignedPortal,
    getUserRoleInfo,
    getAccessiblePortals
  };

  return (
    <AuthContext.Provider value={contextValue}>
      {children}
    </AuthContext.Provider>
  );
};
EOF

echo "AuthContext updated with current portal assignments"

cd "$BASE_DIR"

echo "Creating start-all-portals.sh for current configuration..."
cat > start-all-portals.sh <<'EOF'
#!/usr/bin/env bash
# Start All VitalVida Portals (Current Port Configuration)

BASE_DIR="/Users/yesideasekun/vitalvida-api"
cd "$BASE_DIR"

echo "Starting All VitalVida Portals (Current Configuration)"
echo "========================================================"
echo ""

PORTALS=("vitalvida-inventory" "financial-controller" "gm" "delivery-agent" "accountant" "inventory-management" "investor" "ceo" "hr" "logistics" "manufacturing" "media-buyer" "telesales" "vitalvida-books" "vitalvida-crm" "vitalvida-marketing")

mkdir -p logs

for portal in "${PORTALS[@]}"; do
    if [[ -d "$portal" ]]; then
        echo "Starting $portal..."
        cd "$portal"
        nohup ./start-portal.sh > "../logs/${portal}.log" 2>&1 &
        echo "  ✓ $portal started (PID: $!)"
        cd "$BASE_DIR"
        sleep 1
    else
        echo "  ⚠️  Portal $portal not found"
    fi
done

echo ""
echo "All portals started with current port configuration!"
echo ""
echo "Port Assignments (LOCKED - No Port Hopping):"
echo "  vitalvida-inventory   → http://localhost:3004"
echo "  financial-controller  → http://localhost:3005"
echo "  gm                    → http://localhost:3006"
echo "  delivery-agent        → http://localhost:3007"
echo "  accountant            → http://localhost:3008"
echo "  inventory-management  → http://localhost:3009"
echo "  investor              → http://localhost:3010"
echo "  ceo                   → http://localhost:3011"
echo "  hr                    → http://localhost:3012"
echo "  logistics             → http://localhost:3013"
echo "  manufacturing         → http://localhost:3014"
echo "  media-buyer           → http://localhost:3015"
echo "  telesales             → http://localhost:3016"
echo "  vitalvida-books       → http://localhost:3017"
echo "  vitalvida-crm         → http://localhost:3018"
echo "  vitalvida-marketing   → http://localhost:3019"
echo ""
echo "Health Checks:"
echo "  curl http://localhost:3008/health.json  # accountant"
echo "  curl http://localhost:3016/health.json  # telesales"
echo ""
echo "View Logs:"
echo "  tail -f logs/accountant.log"
echo "  tail -f logs/telesales.log"
echo ""
echo "Admin Center: http://localhost:8080"
EOF

chmod +x start-all-portals.sh

echo ""
echo "Current Port Configuration Locked!"
echo "====================================="
echo ""
echo "What was done:"
echo "  - Kept all current working port assignments"
echo "  - Added iframe headers to all portal vite.config.ts files"
echo "  - Updated admin center routes to match current ports"
echo "  - Updated AuthContext with current portal mappings"
echo "  - Created start-all-portals.sh for current configuration"
echo ""
echo "Current Port Map (LOCKED - No Port Hopping):"
printf "%b" "$PORTALS" | while IFS='|' read -r portal port; do
  [[ -z "${portal:-}" ]] && continue
  echo "  $portal → Port $port"
done

echo ""
echo "Ready to Test:"
echo "  1. cd /Users/yesideasekun/vitalvida-api/secure-admin-center-33"
echo "  2. npm run dev"
echo "  3. ./start-all-portals.sh (in another terminal)"
echo "  4. Visit http://localhost:8080"
echo "  5. Login as superadmin/superadmin"
echo "  6. Test portal navigation"
echo ""
echo "Security: All ports are locked to current assignments - no port hopping allowed!"


