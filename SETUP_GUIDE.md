# 🚀 VitalVida Admin Setup Guide

## Quick Start: Create Superadmin User & Test System

### Option 1: Using Artisan Command (Recommended)

```bash
# Create superadmin with interactive prompts
php artisan admin:create-superadmin

# Or create with specific credentials
php artisan admin:create-superadmin --name="Your Name" --email="your@email.com" --password="yourpassword"
```

### Option 2: Using Database Seeder

```bash
# Run the seeder to create superadmin + test users
php artisan db:seed

# Or run just the superadmin seeder
php artisan db:seed --class=SuperAdminSeeder
```

**Default credentials from seeder:**
- Email: `admin@vitalvida.com`
- Password: `admin123456`

## 🧪 Testing Your Admin System

### Step 1: Start the Server
```bash
php artisan serve
```

### Step 2: Test with Automated Script
```bash
# Run the comprehensive test script
./test-admin-api.sh
```

### Step 3: Manual Testing with cURL

#### Login Test
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@vitalvida.com",
    "password": "admin123456"
  }'
```

#### Dashboard Test (replace YOUR_TOKEN)
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/admin/dashboard
```

#### User Management Test
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  "http://localhost:8000/api/admin/users?per_page=10"
```

#### KYC Management Test
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/admin/kyc/pending
```

### Step 4: Test with Postman

1. **Import this collection:**
```json
{
  "info": {
    "name": "VitalVida Admin API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Login",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "Accept",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"email\": \"admin@vitalvida.com\",\n  \"password\": \"admin123456\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/auth/login",
          "host": ["{{base_url}}"],
          "path": ["auth", "login"]
        }
      }
    },
    {
      "name": "Dashboard",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          },
          {
            "key": "Accept",
            "value": "application/json"
          }
        ],
        "url": {
          "raw": "{{base_url}}/admin/dashboard",
          "host": ["{{base_url}}"],
          "path": ["admin", "dashboard"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000/api"
    },
    {
      "key": "token",
      "value": "YOUR_TOKEN_HERE"
    }
  ]
}
```

2. **Set environment variables:**
   - `base_url`: `http://localhost:8000/api`
   - `token`: (extract from login response)

## 📋 Available Test Users

After running the seeder, you'll have these test users:

| Role | Email | Password | Access Level |
|------|-------|----------|--------------|
| Superadmin | `admin@vitalvida.com` | `admin123456` | Full access |
| CEO | `ceo@vitalvida.com` | `password` | Executive access |
| Production | `production@vitalvida.com` | `password` | Production access |
| Telesales | `telesales@vitalvida.com` | `password` | Sales access |

## 🔍 Testing Different Features

### Test User Management
```bash
# Get all users
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/users"

# Filter by role
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/users?role=production"

# Search users
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/users?search=admin"
```

### Test KYC Management
```bash
# Get pending KYC
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/kyc/pending"

# Get KYC statistics
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/kyc/stats"
```

### Test Role Management
```bash
# Get all roles
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/roles"

# Get users by role
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/roles/production/users"
```

### Test System Configuration
```bash
# Get system config (superadmin only)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/system/config"

# Get system health
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/system/health"

# Clear cache
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/system/clear-cache"
```

### Test Audit Logs
```bash
# Get audit logs
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/audit-logs?per_page=10"

# Filter by action
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/audit-logs?action=user.create"
```

## 🚨 Important Security Notes

1. **Change default passwords** after first login
2. **Use strong passwords** in production
3. **Enable HTTPS** in production
4. **Set up proper environment variables**
5. **Configure rate limiting** for production

## 🎯 Next Steps

1. ✅ Create superadmin user
2. ✅ Test authentication
3. ✅ Verify admin endpoints
4. 🔄 Build admin frontend
5. 🔄 Add more test data
6. 🔄 Configure production settings

## 🆘 Troubleshooting

### Common Issues

**Login fails:**
- Check if user exists: `php artisan tinker` then `User::where('email', 'admin@vitalvida.com')->first()`
- Verify password: `Hash::check('admin123456', $user->password)`

**Server not running:**
- Start server: `php artisan serve`
- Check port: `lsof -i :8000`

**Database issues:**
- Run migrations: `php artisan migrate`
- Check connection: `php artisan tinker` then `DB::connection()->getPdo()`

**Permission denied:**
- Check role middleware: `php artisan route:list --path=admin`
- Verify user role: `$user->role === 'superadmin'`

---

**🎉 Your admin portal is ready! Start building your frontend and adding more features.** 