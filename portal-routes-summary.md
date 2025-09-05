# Portal Routes Summary

## All 13 Portal Routes Configured

### Enter As Role Routes:
1. `/dashboard/logistics` → `http://localhost:3004` (delivery_agent, superadmin)
2. `/dashboard/inventory-management` → `http://localhost:3005` (inventory, superadmin)
3. `/dashboard/telesales` → `http://localhost:3006` (telesales, superadmin)
4. `/dashboard/delivery` → `http://localhost:3007` (delivery_agent, superadmin)
5. `/dashboard/accountant` → `http://localhost:3008` (accountant, superadmin)
6. `/dashboard/cfo` → `http://localhost:3009` (cfo, superadmin)
7. `/dashboard/gm` → `http://localhost:3010` (ceo, superadmin)
8. `/dashboard/ceo` → `http://localhost:3011` (ceo, superadmin)
9. `/dashboard/investor` → `http://localhost:3013` (investor, superadmin)
10. `/dashboard/finance` → `http://localhost:3014` (cfo, superadmin)

### System Automation Routes:
11. `/dashboard/crm` → `http://localhost:3015` (superadmin only)
12. `/dashboard/books` → `http://localhost:3017` (superadmin only)
13. `/dashboard/kyc` → `http://localhost:3018` (superadmin only)

## Portal Mapping:
- `logistics` → `http://localhost:3004`
- `inventoryManagement` → `http://localhost:3005`
- `telesales` → `http://localhost:3006`
- `delivery` → `http://localhost:3007`
- `accountant` → `http://localhost:3008`
- `cfo` → `http://localhost:3009`
- `gm` → `http://localhost:3010`
- `ceo` → `http://localhost:3011`
- `investor` → `http://localhost:3013`
- `finance` → `http://localhost:3014`
- `crm` → `http://localhost:3015`
- `books` → `http://localhost:3017`
- `kyc` → `http://localhost:3018`

## Testing:
- Visit `http://localhost:8080`
- Login as any user
- Navigate to `/dashboard/[role]` to test each portal
- Each route has proper role-based access control
- Iframes load the correct localhost port for each portal 