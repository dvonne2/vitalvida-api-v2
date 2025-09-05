// Inventory Manager Configuration
// Centralized configuration for API endpoints, intervals, and settings

export const INVENTORY_MANAGER_CONFIG = {
  // API Configuration
  API_BASE_URL: process.env.REACT_APP_API_BASE_URL || 'http://localhost:8000/api/inventory-portal/',
  API_TIMEOUT: 30000, // 30 seconds
  API_RETRY_ATTEMPTS: 3,
  
  // Authentication
  AUTH_TOKEN_KEY: 'auth_token',
  AUTH_REFRESH_KEY: 'refresh_token',
  
  // Endpoints
  ENDPOINTS: {
    // Dashboard
    DASHBOARD_OVERVIEW: '/dashboard/enhanced-overview',
    REGIONAL_OVERVIEW: '/dashboard/regional-overview',
    
    // Alerts
    CRITICAL_ALERTS: '/alerts/critical',
    LOW_STOCK_ALERTS: '/stock/alerts/low-stock',
    AGING_INVENTORY_ALERTS: '/stock/alerts/aging-inventory',
    
    // Inventory Flow
    INVENTORY_FLOW: '/inventory/flow/summary',
    PURCHASE_ORDERS: '/purchase-orders',
    STOCK_MOVEMENTS: '/inventory/movements',
    RECEIVE_STOCK: '/inventory/receive',
    
    // DA Compliance
    DA_COMPLIANCE: '/da/compliance/overview',
    DA_AGENTS: '/da/agents',
    WEEKLY_COMPLIANCE: '/da/compliance/weekly',
    PHOTO_COMPLIANCE: '/da/compliance/photos',
    DA_VIOLATIONS: '/da/violations',
    
    // Stock Management
    STOCK_OVERVIEW: '/stock/overview',
    BIN_MANAGEMENT: '/bins',
    LIVE_STOCK_LEVELS: '/stock/live-levels',
    STATE_STOCK_OVERVIEW: '/stock/state-overview',
    
    // Performance
    DA_PERFORMANCE: '/da/performance/rankings',
    PERFORMANCE_ANALYTICS: '/da/performance/analytics',
    RECENT_VIOLATIONS: '/da/violations/recent',
    
    // Regional
    REGIONAL_OVERVIEW: '/regional/overview',
    REGIONAL_STOCK_SUMMARY: '/regional/stock-summary',
    AGENT_DISTRIBUTION: '/regional/agent-distribution',
    
    // Utilities
    HEALTH_CHECK: '/health',
    EXPORT_DATA: '/export-data',
    UPLOAD_FILE: '/upload'
  },
  
  // Refresh Intervals (in milliseconds)
  REFRESH_INTERVALS: {
    DASHBOARD: 30000,      // 30 seconds
    INVENTORY_FLOW: 15000, // 15 seconds  
    DA_COMPLIANCE: 60000,  // 1 minute
    STOCK_LEVELS: 30000,   // 30 seconds
    PERFORMANCE: 300000,   // 5 minutes
    ALERTS: 30000,         // 30 seconds
    REGIONAL: 120000       // 2 minutes
  },
  
  // Status Colors
  STATUS_COLORS: {
    CRITICAL: '#EF4444',
    WARNING: '#F59E0B', 
    GOOD: '#10B981',
    OPTIMAL: '#3B82F6',
    EXCELLENT: '#8B5CF6',
    PENDING: '#6B7280',
    COMPLETED: '#059669'
  },
  
  // Status Labels
  STATUS_LABELS: {
    CRITICAL: 'Critical',
    WARNING: 'Warning',
    GOOD: 'Good',
    OPTIMAL: 'Optimal',
    EXCELLENT: 'Excellent',
    PENDING: 'Pending',
    COMPLETED: 'Completed'
  },
  
  // Priority Levels
  PRIORITY_LEVELS: {
    LOW: 'low',
    MEDIUM: 'medium',
    HIGH: 'high',
    CRITICAL: 'critical'
  },
  
  // Compliance Status
  COMPLIANCE_STATUS: {
    ON_TIME: 'on_time',
    LATE: 'late',
    MISSING: 'missing',
    PENDING: 'pending'
  },
  
  // Photo Quality
  PHOTO_QUALITY: {
    CLEAR: 'clear',
    BLURRY: 'blurry',
    INCOMPLETE: 'incomplete'
  },
  
  // System Actions
  SYSTEM_ACTIONS: {
    NONE: 'none',
    WARNING: 'warning',
    STRIKE_DEDUCTION: 'strike_deduction',
    SUSPENSION: 'suspension'
  },
  
  // File Upload
  UPLOAD: {
    MAX_FILE_SIZE: 10 * 1024 * 1024, // 10MB
    ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
    MAX_FILES: 5
  },
  
  // Export Formats
  EXPORT_FORMATS: {
    CSV: 'csv',
    EXCEL: 'excel',
    PDF: 'pdf'
  },
  
  // Pagination
  PAGINATION: {
    DEFAULT_PAGE_SIZE: 20,
    MAX_PAGE_SIZE: 100,
    PAGE_SIZE_OPTIONS: [10, 20, 50, 100]
  },
  
  // Date Formats
  DATE_FORMATS: {
    DISPLAY: 'MMM dd, yyyy',
    API: 'yyyy-MM-dd',
    DATETIME: 'MMM dd, yyyy HH:mm',
    TIME: 'HH:mm'
  },
  
  // Currency
  CURRENCY: {
    SYMBOL: '₦',
    CODE: 'NGN',
    LOCALE: 'en-NG'
  },
  
  // Validation Rules
  VALIDATION: {
    MIN_PASSWORD_LENGTH: 8,
    MAX_TITLE_LENGTH: 100,
    MAX_DESCRIPTION_LENGTH: 500,
    MIN_QUANTITY: 1,
    MAX_QUANTITY: 999999
  },
  
  // Error Messages
  ERROR_MESSAGES: {
    NETWORK_ERROR: 'Network error. Please check your connection.',
    AUTH_ERROR: 'Authentication failed. Please login again.',
    PERMISSION_ERROR: 'You do not have permission to perform this action.',
    VALIDATION_ERROR: 'Please check your input and try again.',
    SERVER_ERROR: 'Server error. Please try again later.',
    TIMEOUT_ERROR: 'Request timed out. Please try again.',
    UNKNOWN_ERROR: 'An unexpected error occurred.'
  },
  
  // Success Messages
  SUCCESS_MESSAGES: {
    DATA_SAVED: 'Data saved successfully.',
    DATA_DELETED: 'Data deleted successfully.',
    ACTION_COMPLETED: 'Action completed successfully.',
    FILE_UPLOADED: 'File uploaded successfully.',
    EXPORT_COMPLETED: 'Export completed successfully.'
  },
  
  // Loading Messages
  LOADING_MESSAGES: {
    FETCHING_DATA: 'Fetching data...',
    SAVING_DATA: 'Saving data...',
    UPLOADING_FILE: 'Uploading file...',
    EXPORTING_DATA: 'Exporting data...',
    PROCESSING: 'Processing...'
  },
  
  // Local Storage Keys
  STORAGE_KEYS: {
    USER_PREFERENCES: 'inventory_manager_preferences',
    DASHBOARD_FILTERS: 'dashboard_filters',
    TABLE_SORT: 'table_sort_settings',
    EXPANDED_SECTIONS: 'expanded_sections'
  },
  
  // Feature Flags
  FEATURES: {
    REAL_TIME_UPDATES: true,
    EXPORT_FUNCTIONALITY: true,
    FILE_UPLOAD: true,
    ADVANCED_FILTERS: true,
    BULK_ACTIONS: true,
    NOTIFICATIONS: true,
    DARK_MODE: false,
    OFFLINE_MODE: false
  },
  
  // Performance Settings
  PERFORMANCE: {
    DEBOUNCE_DELAY: 300,
    THROTTLE_DELAY: 1000,
    CACHE_DURATION: 5 * 60 * 1000, // 5 minutes
    MAX_CACHE_SIZE: 50
  },
  
  // Notification Settings
  NOTIFICATIONS: {
    ENABLED: true,
    POSITION: 'top-right',
    AUTO_HIDE: true,
    AUTO_HIDE_DELAY: 5000,
    MAX_NOTIFICATIONS: 5
  },
  
  // Chart Configuration
  CHARTS: {
    COLORS: [
      '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
      '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'
    ],
    ANIMATION_DURATION: 1000,
    RESPONSIVE: true,
    MAINTAIN_ASPECT_RATIO: false
  },
  
  // Table Configuration
  TABLE: {
    DEFAULT_SORT_FIELD: 'created_at',
    DEFAULT_SORT_DIRECTION: 'desc',
    ROWS_PER_PAGE_OPTIONS: [10, 25, 50, 100],
    DEFAULT_ROWS_PER_PAGE: 25,
    STICKY_HEADER: true,
    SELECTABLE_ROWS: true,
    EXPANDABLE_ROWS: false
  },
  
  // Form Configuration
  FORM: {
    VALIDATE_ON_CHANGE: true,
    VALIDATE_ON_BLUR: true,
    SHOW_VALIDATION_MESSAGES: true,
    AUTO_SAVE: false,
    AUTO_SAVE_DELAY: 2000
  },
  
  // Modal Configuration
  MODAL: {
    BACKDROP_CLICK_TO_CLOSE: true,
    ESC_KEY_TO_CLOSE: true,
    ANIMATION_DURATION: 300,
    MAX_WIDTH: '600px'
  },
  
  // Search Configuration
  SEARCH: {
    MIN_SEARCH_LENGTH: 2,
    SEARCH_DELAY: 300,
    HIGHLIGHT_RESULTS: true,
    SEARCH_IN_FIELDS: ['name', 'description', 'id']
  }
};

// Environment-specific configurations
export const getConfig = () => {
  const env = process.env.NODE_ENV || 'development';
  
  const configs = {
    development: {
      ...INVENTORY_MANAGER_CONFIG,
      API_BASE_URL: 'http://localhost:8000/api/inventory-portal/',
      DEBUG: true,
      LOG_LEVEL: 'debug'
    },
    staging: {
      ...INVENTORY_MANAGER_CONFIG,
      API_BASE_URL: process.env.REACT_APP_API_BASE_URL || 'https://staging-api.vitalvida.com/api/inventory-portal/',
      DEBUG: true,
      LOG_LEVEL: 'info'
    },
    production: {
      ...INVENTORY_MANAGER_CONFIG,
      API_BASE_URL: process.env.REACT_APP_API_BASE_URL || 'https://api.vitalvida.com/api/inventory-portal/',
      DEBUG: false,
      LOG_LEVEL: 'error'
    }
  };
  
  return configs[env as keyof typeof configs] || configs.development;
};

// Utility functions
export const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN'
  }).format(amount);
};

export const formatDate = (date: string | Date, format: string = 'display'): string => {
  const dateObj = typeof date === 'string' ? new Date(date) : date;
  const config = getConfig();
  
  const formats = {
    display: 'MMM dd, yyyy',
    api: 'yyyy-MM-dd',
    datetime: 'MMM dd, yyyy HH:mm',
    time: 'HH:mm'
  };
  
  // Simple date formatting (you can use a library like date-fns for more robust formatting)
  const options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: format.includes('MMM') ? 'short' : '2-digit',
    day: '2-digit',
    hour: format.includes('HH:mm') ? '2-digit' : undefined,
    minute: format.includes('HH:mm') ? '2-digit' : undefined
  };
  
  return dateObj.toLocaleDateString('en-US', options);
};

export const getStatusColor = (status: string): string => {
  const config = getConfig();
  return config.STATUS_COLORS[status as keyof typeof config.STATUS_COLORS] || config.STATUS_COLORS.PENDING;
};

export const getStatusLabel = (status: string): string => {
  const config = getConfig();
  return config.STATUS_LABELS[status as keyof typeof config.STATUS_LABELS] || status;
};

export const isValidFileType = (file: File): boolean => {
  const config = getConfig();
  return config.UPLOAD.ALLOWED_TYPES.includes(file.type);
};

export const isValidFileSize = (file: File): boolean => {
  const config = getConfig();
  return file.size <= config.UPLOAD.MAX_FILE_SIZE;
};

export const getErrorMessage = (errorCode: string): string => {
  const config = getConfig();
  return config.ERROR_MESSAGES[errorCode as keyof typeof config.ERROR_MESSAGES] || config.ERROR_MESSAGES.UNKNOWN_ERROR;
};

export const getSuccessMessage = (messageCode: string): string => {
  const config = getConfig();
  return config.SUCCESS_MESSAGES[messageCode as keyof typeof config.SUCCESS_MESSAGES] || 'Operation completed successfully.';
};

export const getLoadingMessage = (messageCode: string): string => {
  const config = getConfig();
  return config.LOADING_MESSAGES[messageCode as keyof typeof config.LOADING_MESSAGES] || 'Loading...';
};

// Export the main config
export default getConfig(); 