# INVENTORY PORTAL ENDPOINT SPECIFICATIONS

## UI Analysis Summary
Based on reverse-engineering the InventoryPortal.tsx component, here are the exact data requirements for each UI component:

---

## /api/v1/dashboard/login-status
**UI Component**: Login Status badge in header
**Current Display**: 
- Shows "On time" badge with green CheckCircle icon
- OR shows "⚠️ {minutesLate} mins late" with red AlertCircle icon
**Data Source**: `inventoryData.isOnTime`, `inventoryData.minutesLate`
**Required Response**:
```json
{
  "isOnTime": true,
  "minutesLate": 0,
  "loginTime": "8:14 AM",
  "status": "On time",
  "penaltyAmount": 0
}
```

---

## /api/v1/dashboard/reviews  
**UI Component**: DA Reviews data (used in Dashboard tab content)
**Current Display**: Based on `inventoryData.daReviews`
**Data Source**: `{ completed: 23, total: 70, remaining: '1h 42m' }`
**Required Response**:
```json
{
  "completed": 23,
  "total": 70,
  "remaining": "1h 42m",
  "progressPercentage": 33,
  "status": "in_progress"
}
```

---

## /api/v1/dashboard/system-actions
**UI Component**: System Actions data (used in Dashboard tab content)
**Current Display**: Based on `inventoryData.systemActions`
**Data Source**: `{ pending: 5, risk: 30000 }`
**Required Response**:
```json
{
  "pending": 5,
  "risk": 30000,
  "riskLevel": "medium",
  "urgentActions": 2
}
```

---

## /api/v1/dashboard/weekly-metrics
**UI Component**: Weekly Performance badge in header
**Current Display**: 
- Shows "Net: +₦5,000" with green success styling when positive
- OR shows "Net: -₦5,000" with red destructive styling when negative
**Data Source**: `inventoryData.weeklyNet`
**Required Response**:
```json
{
  "weeklyNet": 5000,
  "isPositive": true,
  "metricsAchieved": 3,
  "totalMetrics": 5,
  "performanceRating": "good"
}
```

---

## /api/v1/alerts/critical
**UI Component**: Critical Actions badge in header (animated pulse)
**Current Display**: 
- Shows "{criticalActions} Critical Actions Required" with red badge and AlertTriangle icon
- Only displays when `criticalActions > 0`
**Data Source**: `inventoryData.criticalActions`
**Required Response**:
```json
{
  "criticalActions": 3,
  "totalAlerts": 7,
  "highPriority": 3,
  "mediumPriority": 2,
  "alerts": [
    {
      "id": 1,
      "type": "stock_shortage",
      "message": "Critical stock shortage in Zone A",
      "priority": "high",
      "timeRemaining": "2h 15m"
    }
  ]
}
```

---

## /api/v1/alerts/enforcement-tasks
**UI Component**: Daily Enforcement Tasks (used in Dashboard tab content)
**Current Display**: Task list with timers and risk amounts
**Data Source**: Used in Dashboard component
**Required Response**:
```json
{
  "tasks": [
    {
      "id": 1,
      "title": "Photo Audit - Zone A",
      "deadline": "2h 34m remaining",
      "riskAmount": 2500,
      "status": "pending",
      "priority": "high"
    }
  ],
  "totalTasks": 5,
  "completedTasks": 2,
  "overdueTasks": 1
}
```

---

## /api/v1/dashboard/overview
**UI Component**: Main dashboard metrics and overview data
**Current Display**: Overall portal statistics and summaries
**Data Source**: Multiple `inventoryData` fields
**Required Response**:
```json
{
  "revenueAtRisk": 47500,
  "protectedToday": 892000,
  "penaltyRisk": 2500,
  "timeToDeadline": "2h 34m remaining",
  "totalOrders": 156,
  "activeAgents": 23,
  "inventoryValue": 1250000,
  "summary": {
    "status": "operational",
    "lastUpdated": "2025-08-14T20:02:17Z"
  }
}
```

---

## UI Component Mapping

### Header Components:
1. **Current Time**: Uses `currentTime` state (updated every minute)
2. **Login Status**: Uses `isOnTime` and `minutesLate` → `/dashboard/login-status`
3. **Friday Photo Audit**: Uses `timeToDeadline` → `/alerts/enforcement-tasks`
4. **Weekly Performance**: Uses `weeklyNet` → `/dashboard/weekly-metrics`
5. **Penalty Risk**: Uses `penaltyRisk` → `/dashboard/overview`
6. **Critical Actions**: Uses `criticalActions` → `/alerts/critical`

### Dashboard Tab Components:
- Uses imported components: `Dashboard`, `InventoryFlowPanel`, `InventoryEnforcement`, `StockOverview`, `DeliveryAgentEnforcement`
- These components receive data from the main endpoints above

---

## Implementation Priority:
1. `/dashboard/overview` - Core dashboard data
2. `/alerts/critical` - Critical actions badge
3. `/dashboard/login-status` - Login status badge  
4. `/dashboard/weekly-metrics` - Weekly performance badge
5. `/alerts/enforcement-tasks` - Enforcement tasks
6. `/dashboard/reviews` - DA reviews data
7. `/dashboard/system-actions` - System actions data

All endpoints should return data that matches the exact structure expected by the React components to ensure seamless integration.
