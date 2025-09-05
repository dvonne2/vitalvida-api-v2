# VitalVida Admin Backend Foundation

A scalable Laravel backend foundation for the VitalVida admin portal with role-based access control, comprehensive user management, KYC processing, and system monitoring capabilities.

## 🏗️ Architecture Overview

The admin system is built with scalability in mind, featuring:

- **Modular Controller Structure**: Separate controllers for different admin functions
- **Versioned API Routes**: Support for multiple API versions (v1, v2, v3)
- **Role-Based Access Control**: Granular permissions for different user roles
- **Comprehensive Audit Logging**: Track all admin actions for security
- **Expandable Design**: Easy to add new features without breaking existing functionality

## 📁 Project Structure

```
app/Http/Controllers/Api/Admin/
├── AdminController.php           # Core admin dashboard and user management
├── KycManagementController.php   # KYC application processing
├── RoleManagementController.php  # Role and permission management
└── SystemConfigController.php    # System configuration and monitoring

routes/api.php                    # Versioned API routes
```

## 🔐 Authentication & Authorization

### User Roles
- **superadmin**: Full system access
- **ceo**: Executive-level access
- **cfo**: Financial management access
- **accountant**: Financial reporting access
- **production**: Production management
- **inventory**: Inventory management
- **telesales**: Sales and lead management
- **da**: Delivery agent operations

### Middleware
- `auth:sanctum`: Token-based authentication
- `check.role`: Role-based access control

## 🚀 Core Features (Phase 1)

### 1. Authentication System
```php
// Universal admin login
POST /api/auth/login
{
    "email": "admin@vitalvida.com",
    "password": "password"
}
```

### 2. User Management
```php
// Get all users with filters
GET /api/admin/users?role=production&kyc_status=pending&search=john

// Create new user
POST /api/admin/users
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08012345678",
    "password": "password",
    "password_confirmation": "password",
    "role": "production",
    "kyc_status": "pending"
}

// Update user
PUT /api/admin/users/{id}
{
    "name": "John Doe Updated",
    "role": "inventory",
    "is_active": true
}

// Bulk operations
POST /api/admin/users/{id}/activate
POST /api/admin/users/{id}/deactivate
DELETE /api/admin/users/{id}
```

### 3. KYC Management
```php
// Get pending KYC applications
GET /api/admin/kyc/pending?search=john&date_from=2024-01-01

// Approve KYC application
POST /api/admin/kyc/{user_id}/approve

// Reject KYC application
POST /api/admin/kyc/{user_id}/reject
{
    "reason": "Document quality insufficient"
}

// Bulk operations
POST /api/admin/kyc/bulk-approve
{
    "user_ids": [1, 2, 3, 4, 5]
}

POST /api/admin/kyc/bulk-reject
{
    "user_ids": [1, 2, 3],
    "reason": "Incomplete documentation"
}

// KYC statistics
GET /api/admin/kyc/stats
```

### 4. Role Management
```php
// Get all roles with user counts
GET /api/admin/roles

// Get users by role
GET /api/admin/roles/{role}/users?is_active=1&kyc_status=approved

// Bulk assign role
POST /api/admin/roles/bulk-assign
{
    "user_ids": [1, 2, 3, 4, 5],
    "role": "production"
}

// Role statistics
GET /api/admin/roles/stats

// Role permissions
GET /api/admin/roles/permissions
```

### 5. System Dashboard
```php
// Main dashboard
GET /api/admin/dashboard

// System metrics
GET /api/admin/system/metrics

// Audit logs
GET /api/admin/audit-logs?user_id=1&action=user.create&risk_level=high

// System configuration (Superadmin/CEO only)
GET /api/admin/system/config
GET /api/admin/system/health
GET /api/admin/system/performance
GET /api/admin/system/logs
POST /api/admin/system/clear-cache
GET /api/admin/system/backups
```

## 🔄 API Versioning

### Current Version (v1)
- Stable production features
- Core admin functionality
- Basic reporting and monitoring

### Planned Versions

#### V2 (Planned: June 2024)
```php
// Advanced Analytics
GET /api/admin/v2/analytics/dashboard
GET /api/admin/v2/analytics/reports
GET /api/admin/v2/analytics/performance

// Advanced User Management
GET /api/admin/v2/users/advanced
POST /api/admin/v2/users/bulk-operations

// Advanced System Management
GET /api/admin/v2/system/advanced-config
GET /api/admin/v2/system/monitoring
```

#### V3 (Planned: December 2024)
```php
// AI-Powered Features
GET /api/admin/v3/ai/insights
GET /api/admin/v3/ai/automation

// Advanced Security
GET /api/admin/v3/security/advanced
```

## 🛠️ Installation & Setup

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 4. Create Superadmin User
```bash
php artisan tinker
```
```php
User::create([
    'name' => 'Super Admin',
    'email' => 'admin@vitalvida.com',
    'password' => Hash::make('password'),
    'role' => 'superadmin',
    'is_active' => true,
    'email_verified_at' => now(),
]);
```

### 5. Start Development Server
```bash
php artisan serve
```

## 🧪 Testing

### API Testing with Postman

1. **Authentication**
```http
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
    "email": "admin@vitalvida.com",
    "password": "password"
}
```

2. **Dashboard Access**
```http
GET http://localhost:8000/api/admin/dashboard
Authorization: Bearer YOUR_TOKEN
```

3. **User Management**
```http
GET http://localhost:8000/api/admin/users?role=production&per_page=20
Authorization: Bearer YOUR_TOKEN
```

4. **KYC Management**
```http
GET http://localhost:8000/api/admin/kyc/pending
Authorization: Bearer YOUR_TOKEN
```

### Testing with cURL

```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@vitalvida.com","password":"password"}'

# Get dashboard (replace YOUR_TOKEN)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/admin/dashboard

# Get users
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/admin/users?role=production&per_page=10"
```

## 🔧 Configuration

### Role Permissions
Edit `app/Http/Middleware/CheckRole.php` to modify role-based access:

```php
protected $roleHierarchy = [
    'superadmin' => ['all'],
    'ceo' => ['user_management', 'kyc_management', 'system_config'],
    'cfo' => ['financial_reports', 'payment_management'],
    // ... other roles
];
```

### Audit Logging
Configure audit logging in `app/Models/ActionLog.php`:

```php
protected $fillable = [
    'user_id', 'action', 'model_type', 'model_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
    'metadata', 'risk_level', 'is_suspicious'
];
```

## 📈 Monitoring & Analytics

### System Health Monitoring
```php
GET /api/admin/system/health
```
Returns:
- Database connection status
- Storage usage
- Cache status
- Queue status
- Service health

### Performance Metrics
```php
GET /api/admin/system/performance
```
Returns:
- Memory usage
- Execution time
- Database query count
- Cache hit rate

### Audit Trail
```php
GET /api/admin/audit-logs?user_id=1&action=user.create&date_from=2024-01-01
```
Track all admin actions with:
- User who performed action
- Action type and details
- Old and new values
- IP address and user agent
- Risk level assessment

## 🔮 Future Expansion

### Phase 2 Features (V2)
- Advanced reporting and analytics
- Bulk operations for all entities
- Real-time system monitoring
- Custom role permissions
- Data export/import functionality
- Scheduled reports

### Phase 3 Features (V3)
- AI-powered insights
- Predictive analytics
- Advanced security features
- Automated workflows
- Machine learning integration

### Adding New Features

1. **Create Controller**
```php
// app/Http/Controllers/Api/Admin/NewFeatureController.php
namespace App\Http\Controllers\Api\Admin;

class NewFeatureController extends Controller
{
    public function index(Request $request)
    {
        // Implementation
    }
}
```

2. **Add Routes**
```php
// routes/api.php
Route::prefix('admin')->middleware(['auth:sanctum', 'check.role:superadmin,ceo'])->group(function () {
    Route::get('/new-feature', [NewFeatureController::class, 'index']);
});
```

3. **Add to API Service**
```javascript
// admin-portal-example.js
export const AdminAPI = {
    // ... existing methods
    getNewFeature: (params = {}) => axios.get('/admin/new-feature', { params }),
};
```

## 🛡️ Security Features

### Authentication Security
- Token-based authentication with Sanctum
- Automatic token expiration
- Secure password hashing
- Rate limiting on login attempts

### Authorization Security
- Role-based access control
- Granular permissions per endpoint
- Protection against privilege escalation
- Superadmin role protection

### Audit Security
- Comprehensive action logging
- Risk level assessment
- Suspicious activity detection
- IP address tracking
- User agent logging

### Data Security
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF protection
- Secure file uploads

## 📊 Database Schema

### Key Tables
- `users`: User accounts and roles
- `action_logs`: Audit trail
- `kyc_logs`: KYC document tracking
- `orders`: Order management
- `payment_logs`: Payment tracking
- `leads`: Lead management
- `purchase_orders`: Purchase order tracking

### Relationships
- Users have many action logs
- Users have many KYC logs
- Users have many orders (assigned)
- Users have many leads (assigned)

## 🚀 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Configure database connections
- [ ] Set up SSL certificates
- [ ] Configure caching (Redis recommended)
- [ ] Set up queue workers
- [ ] Configure backup system
- [ ] Set up monitoring and logging
- [ ] Configure rate limiting
- [ ] Set up firewall rules

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

## 📞 Support & Maintenance

### Monitoring
- System health checks
- Performance monitoring
- Error tracking
- User activity monitoring

### Backup Strategy
- Daily database backups
- File system backups
- Configuration backups
- Disaster recovery plan

### Updates
- Regular security updates
- Feature updates
- Bug fixes
- Performance optimizations

## 🤝 Contributing

1. Follow Laravel coding standards
2. Write comprehensive tests
3. Document new features
4. Update API documentation
5. Test thoroughly before deployment

## 📄 License

This project is proprietary software for VitalVida. All rights reserved.

---

**Built with ❤️ for VitalVida MVP**

For questions or support, contact the development team. 