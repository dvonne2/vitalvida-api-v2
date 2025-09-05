import { LogisticsPortalConfig, StatusType } from '../types/logistics-portal-types';

export const LOGISTICS_PORTAL_CONFIG: LogisticsPortalConfig = {
  API_BASE_URL: 'http://localhost:8000/api/inventory-portal/logistics/',
  
  ENDPOINTS: {
    DASHBOARD_OVERVIEW: 'dashboard/overview',
    ORDER_PIPELINE: 'dashboard/pipeline',
    KPI_PERFORMANCE: 'dashboard/kpi-performance',
    LIVE_ACTIVITY: 'activity/live',
    CONSIGNMENTS: 'consignments',
    FRAUD_ALERTS: 'fraud/alerts',
    MOVEMENT_TRACKING: 'movements/tracking',
    REPORTS: 'reports'
  },
  
  REFRESH_INTERVALS: {
    DASHBOARD: 30000, // 30 seconds
    ACTIVITY: 30000, // 30 seconds
    FRAUD_ALERTS: 300000, // 5 minutes
    MOVEMENTS: 60000 // 1 minute
  },
  
  STATUS_COLORS: {
    success: '#10B981',
    warning: '#F59E0B',
    critical: '#EF4444',
    info: '#3B82F6'
  },
  
  DEMO_CREDENTIALS: {
    username: 'demo@vitalvida.com',
    password: 'demo123'
  }
};

// WebSocket Configuration
export const WEBSOCKET_CONFIG = {
  URL: 'ws://localhost:8000/ws/logistics',
  RECONNECT_INTERVAL: 5000,
  MAX_RECONNECT_ATTEMPTS: 5,
  HEARTBEAT_INTERVAL: 30000
};

// UI Configuration
export const UI_CONFIG = {
  THEME: {
    primary: '#4F46E5',
    secondary: '#6B7280',
    success: '#10B981',
    warning: '#F59E0B',
    error: '#EF4444',
    background: '#F8FAFC',
    surface: '#FFFFFF',
    text: {
      primary: '#1F2937',
      secondary: '#6B7280',
      disabled: '#9CA3AF'
    }
  },
  
  BREAKPOINTS: {
    mobile: 768,
    tablet: 1024,
    desktop: 1280
  },
  
  ANIMATIONS: {
    duration: {
      fast: 150,
      normal: 300,
      slow: 500
    },
    easing: {
      ease: 'ease',
      easeIn: 'ease-in',
      easeOut: 'ease-out',
      easeInOut: 'ease-in-out'
    }
  },
  
  SPACING: {
    xs: '4px',
    sm: '8px',
    md: '16px',
    lg: '24px',
    xl: '32px',
    xxl: '48px'
  },
  
  BORDER_RADIUS: {
    sm: '4px',
    md: '8px',
    lg: '12px',
    xl: '16px',
    full: '9999px'
  },
  
  SHADOWS: {
    sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
    md: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
    lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
    xl: '0 20px 25px -5px rgba(0, 0, 0, 0.1)'
  }
};

// Status Configuration
export const STATUS_CONFIG = {
  ORDER_STATUS: {
    pending: {
      label: 'Pending',
      color: '#F59E0B',
      icon: '⏳'
    },
    in_progress: {
      label: 'In Progress',
      color: '#3B82F6',
      icon: '🔄'
    },
    completed: {
      label: 'Completed',
      color: '#10B981',
      icon: '✅'
    },
    overdue: {
      label: 'Overdue',
      color: '#EF4444',
      icon: '⚠️'
    }
  },
  
  PRIORITY_LEVELS: {
    low: {
      label: 'Low',
      color: '#6B7280',
      icon: '🔵'
    },
    medium: {
      label: 'Medium',
      color: '#F59E0B',
      icon: '🟡'
    },
    high: {
      label: 'High',
      color: '#EF4444',
      icon: '🔴'
    },
    critical: {
      label: 'Critical',
      color: '#7F1D1D',
      icon: '🚨'
    }
  },
  
  FRAUD_SEVERITY: {
    low: {
      label: 'Low',
      color: '#10B981',
      icon: '🟢'
    },
    medium: {
      label: 'Medium',
      color: '#F59E0B',
      icon: '🟡'
    },
    high: {
      label: 'High',
      color: '#EF4444',
      icon: '🔴'
    },
    critical: {
      label: 'Critical',
      color: '#7F1D1D',
      icon: '🚨'
    }
  },
  
  ACTIVITY_TYPES: {
    delivery: {
      label: 'Delivery',
      color: '#10B981',
      icon: '📦'
    },
    pickup: {
      label: 'Pickup',
      color: '#3B82F6',
      icon: '🚚'
    },
    scan: {
      label: 'Scan',
      color: '#8B5CF6',
      icon: '📱'
    },
    alert: {
      label: 'Alert',
      color: '#EF4444',
      icon: '⚠️'
    }
  }
};

// API Configuration
export const API_CONFIG = {
  TIMEOUT: 30000,
  RETRY_ATTEMPTS: 3,
  RETRY_DELAY: 1000,
  
  HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  
  ERROR_CODES: {
    UNAUTHORIZED: 401,
    FORBIDDEN: 403,
    NOT_FOUND: 404,
    VALIDATION_ERROR: 422,
    SERVER_ERROR: 500
  },
  
  RATE_LIMITS: {
    STANDARD: 100, // requests per minute
    REPORTS: 10, // requests per minute
    REALTIME: 1000 // events per minute
  }
};

// Local Storage Keys
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  USER_PREFERENCES: 'user_preferences',
  DASHBOARD_SETTINGS: 'dashboard_settings',
  THEME: 'theme',
  LANGUAGE: 'language'
};

// Feature Flags
export const FEATURE_FLAGS = {
  REAL_TIME_UPDATES: true,
  WEBSOCKET_ENABLED: true,
  FRAUD_DETECTION: true,
  ADVANCED_REPORTING: true,
  MOBILE_OPTIMIZATION: true,
  DARK_MODE: false,
  MULTI_LANGUAGE: false
};

// Validation Rules
export const VALIDATION_RULES = {
  CONSIGNMENT: {
    customer_name: {
      required: true,
      minLength: 2,
      maxLength: 100
    },
    total_items: {
      required: true,
      min: 1,
      max: 10000
    },
    total_value: {
      required: true,
      min: 0
    },
    estimated_delivery: {
      required: true,
      futureDate: true
    }
  },
  
  MOVEMENT: {
    items_count: {
      required: true,
      min: 1
    },
    total_value: {
      required: true,
      min: 0
    },
    estimated_arrival: {
      required: true,
      futureDate: true
    }
  },
  
  FRAUD_ALERT: {
    resolution_notes: {
      required: true,
      minLength: 10,
      maxLength: 500
    }
  }
};

// Notification Configuration
export const NOTIFICATION_CONFIG = {
  TYPES: {
    SUCCESS: {
      icon: '✅',
      duration: 5000,
      position: 'top-right'
    },
    ERROR: {
      icon: '❌',
      duration: 8000,
      position: 'top-right'
    },
    WARNING: {
      icon: '⚠️',
      duration: 6000,
      position: 'top-right'
    },
    INFO: {
      icon: 'ℹ️',
      duration: 4000,
      position: 'top-right'
    }
  },
  
  SOUNDS: {
    enabled: true,
    volume: 0.5,
    files: {
      success: '/sounds/success.mp3',
      error: '/sounds/error.mp3',
      alert: '/sounds/alert.mp3'
    }
  }
};

// Export all configurations
export default {
  LOGISTICS_PORTAL_CONFIG,
  WEBSOCKET_CONFIG,
  UI_CONFIG,
  STATUS_CONFIG,
  API_CONFIG,
  STORAGE_KEYS,
  FEATURE_FLAGS,
  VALIDATION_RULES,
  NOTIFICATION_CONFIG
}; 