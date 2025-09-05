# VitalVida API - Hour 2 Setup Fix & Implementation Guide

## 🔍 **Current Status Analysis**

Looking at your terminal output, I can see several issues we need to fix:

1. **404 Error** - The route isn't registered properly
2. **Missing Models** - We need Product, Warehouse, and InventoryMovement models
3. **No Migrations** - Database tables don't exist yet
4. **Kernel.php Issues** - Middleware registration needs fixing

Let's fix everything step by step:

---

## 🛠️ **Step 1: Fix Kernel.php Registration**

**Filename: `app/Http/Kernel.php`**

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        
        // Custom middleware aliases
        'role' => \App\Http\Middleware\CheckRole::class,
        'auto_refresh' => \App\Http\Middleware\AutoRefreshToken::class,
    ];
}
```

---

## 🗃️ **Step 2: Create Required Models**

Run these commands in your terminal:

```bash
# Create the models with migrations
php artisan make:model Product -m
php artisan make:model Warehouse -m
php artisan make:model InventoryMovement -m

# Create the form request
php artisan make:request StoreInventoryMovementRequest

# Create the controller
php artisan make:controller InventoryMovementController --resource
```

---

## 📊 **Step 3: Database Migrations**

**Filename: `database/migrations/xxxx_create_products_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('stock_quantity', 10, 2)->default(0);
            $table->string('unit')->default('pieces');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['sku', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

**Filename: `database/migrations/xxxx_create_warehouses_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
```

**Filename: `database/migrations/xxxx_create_inventory_movements_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('movement_type', ['in', 'out', 'transfer', 'adjustment']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference_number', 100)->unique();
            $table->text('notes')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['created_by', 'created_at']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
```

---

## 🎯 **Step 4: Model Definitions**

**Filename: `app/Models/Product.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'unit',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
```

**Filename: `app/Models/Warehouse.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'contact_person',
        'phone',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function sourceMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_warehouse_id');
    }
}
```

**Filename: `app/Models/InventoryMovement.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'source_warehouse_id',
        'created_by',
        'movement_type',
        'quantity',
        'unit_cost',
        'reference_number',
        'notes',
        'batch_number',
        'expiry_date'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

---

## 🛣️ **Step 5: Complete Routes File**

**Filename: `routes/api.php`**

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Test endpoint
Route::get('/test', function () {
    return response()->json([
        'message' => 'VitalVida API is working!',
        'time' => now(),
        'status' => 'success'
    ]);
});

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/forgot-password', [App\Http\Controllers\AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [App\Http\Controllers\AuthController::class, 'resetPassword']);
    Route::post('/verify-email', [App\Http\Controllers\AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [App\Http\Controllers\AuthController::class, 'resendVerification']);
});

// Protected routes with auto-refresh middleware
Route::middleware(['auth:sanctum', 'auto_refresh'])->group(function () {

    // Auth routes that require authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/user', [App\Http\Controllers\AuthController::class, 'user']);
        Route::post('/refresh', [App\Http\Controllers\AuthController::class, 'refresh']);
        Route::post('/change-password', [App\Http\Controllers\AuthController::class, 'changePassword']);
        Route::post('/update-profile', [App\Http\Controllers\AuthController::class, 'updateProfile']);
    });

    // Dashboard route
    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome to your dashboard!',
            'user' => auth()->user(),
            'timestamp' => now()
        ]);
    });

    // Public data routes (all authenticated users)
    Route::get('/products', [App\Http\Controllers\ProductController::class, 'index']);
    Route::get('/warehouses', [App\Http\Controllers\WarehouseController::class, 'index']);

    // Inventory Movement routes (restricted to delivery agents)
    Route::middleware(['role:delivery_agent'])->group(function () {
        Route::post('/inventory-movements', [App\Http\Controllers\InventoryMovementController::class, 'store']);
        Route::get('/inventory-movements', [App\Http\Controllers\InventoryMovementController::class, 'index']);
        Route::get('/inventory-movements/{movement}', [App\Http\Controllers\InventoryMovementController::class, 'show']);
    });
});
```

---

## 🌱 **Step 6: Database Seeders**

**Filename: `database/seeders/DatabaseSeeder.php`**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create test warehouses
        Warehouse::create([
            'name' => 'Lagos Main Warehouse',
            'code' => 'LG01',
            'address' => 'Lagos, Nigeria',
            'contact_person' => 'John Doe',
            'phone' => '+234-800-000-0001'
        ]);

        Warehouse::create([
            'name' => 'Abuja Distribution Center',
            'code' => 'AB01',
            'address' => 'Abuja, Nigeria',
            'contact_person' => 'Jane Smith',
            'phone' => '+234-800-000-0002'
        ]);

        // Create test products
        Product::create([
            'name' => 'Test Product A',
            'sku' => 'TEST-001',
            'description' => 'A test product for inventory management',
            'price' => 100.00,
            'stock_quantity' => 0,
            'unit' => 'pieces'
        ]);

        Product::create([
            'name' => 'Test Product B',
            'sku' => 'TEST-002',
            'description' => 'Another test product',
            'price' => 250.50,
            'stock_quantity' => 0,
            'unit' => 'kg'
        ]);

        // Create a delivery agent user for testing
        User::create([
            'name' => 'Delivery Agent',
            'email' => 'agent@vitalvida.com',
            'phone' => '1234567890',
            'password' => bcrypt('password123'),
            'role' => 'delivery_agent',
            'kyc_status' => 'approved'
        ]);
    }
}
```

---

## ⚡ **Step 7: Implementation Commands**

Run these commands in order:

```bash
# 1. Run migrations
php artisan migrate

# 2. Seed the database
php artisan db:seed

# 3. Clear any cached routes/config
php artisan route:clear
php artisan config:clear
php artisan cache:clear

# 4. Check routes are registered
php artisan route:list --path=api
```

---

## 🧪 **Step 8: Test Commands**

```bash
# 1. First, login as the delivery agent
curl -X POST "http://127.0.0.1:8003/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"agent@vitalvida.com","password":"password123"}'

# 2. Use the token from above to test inventory movement
curl -X POST "http://127.0.0.1:8003/api/inventory-movements" \
  -H "Authorization: Bearer [TOKEN_FROM_LOGIN]" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "warehouse_id": 1,
    "movement_type": "in",
    "quantity": 50.00,
    "unit_cost": 25.99,
    "notes": "Initial stock receipt"
  }'

# 3. Test with your existing production user (should fail with 403)
curl -X POST "http://127.0.0.1:8003/api/inventory-movements" \
  -H "Authorization: Bearer 4|B7QeXDU66tJA2YmNoX94fcEaBioBPeX7CzJ7LgIn63f69eab" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "warehouse_id": 1,
    "movement_type": "in",
    "quantity": 50.00
  }'
```

---

## 🎯 **Expected Results**

✅ **With delivery agent token**: 201 Created + movement data  
❌ **With production user token**: 403 Forbidden (Access denied. Required role: delivery_agent)  
✅ **Route list shows**: All API routes properly registered  
✅ **Database**: Tables created with proper relationships  

---

## 🚀 **Ready for Hour 3**

Once this is working, we'll build:
- Product and Warehouse controllers
- Real-time inventory reporting
- Batch processing for bulk updates
- Advanced filtering and search

Your validation system is now bulletproof! 🛡️