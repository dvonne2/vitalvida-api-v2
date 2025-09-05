# VitalVida API - Fix Existing System Issues

## 🔍 **Current Status:**

✅ **Database tables exist** - products, warehouses, inventory_movements all migrated  
❌ **No data in database** - All counts are 0  
❌ **No routes registered** - inventory-movements routes missing  
❌ **Old controller exists** - Need to update with new functionality  
❌ **Strange output issue** - Zoho command showing up in responses  

---

## 🛠️ **Step 1: Fix the Strange Output Issue**

There seems to be an issue with your terminal showing the Zoho command. Let's fix this first:

```bash
# Check if there's a problem with your shell profile
echo $SHELL

# Clear any cached artisan commands
php artisan optimize:clear
composer dump-autoload
```

---

## 🛠️ **Step 2: Check Current Routes File**

```bash
# Let's see what's in your current routes file
cat routes/api.php
```

**Expected:** Should show your auth routes but probably missing inventory-movements routes.

---

## 🛠️ **Step 3: Seed the Database**

Since your tables exist but are empty, let's add test data:

**Create the seeder:**

```bash
# Create a new seeder
php artisan make:seeder TestDataSeeder
```

**Filename: `database/seeders/TestDataSeeder.php`**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test warehouses
        Warehouse::updateOrCreate(
            ['code' => 'LG01'],
            [
                'name' => 'Lagos Main Warehouse',
                'address' => 'Lagos, Nigeria',
                'contact_person' => 'John Doe',
                'phone' => '+234-800-000-0001',
                'is_active' => true
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'AB01'],
            [
                'name' => 'Abuja Distribution Center',
                'address' => 'Abuja, Nigeria',
                'contact_person' => 'Jane Smith',
                'phone' => '+234-800-000-0002',
                'is_active' => true
            ]
        );

        // Create test products
        Product::updateOrCreate(
            ['sku' => 'TEST-001'],
            [
                'name' => 'Test Product A',
                'description' => 'A test product for inventory management',
                'price' => 100.00,
                'stock_quantity' => 0,
                'unit' => 'pieces',
                'is_active' => true
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'TEST-002'],
            [
                'name' => 'Test Product B',
                'description' => 'Another test product',
                'price' => 250.50,
                'stock_quantity' => 0,
                'unit' => 'kg',
                'is_active' => true
            ]
        );

        // Create a delivery agent user for testing
        User::updateOrCreate(
            ['email' => 'agent@vitalvida.com'],
            [
                'name' => 'Delivery Agent',
                'phone' => '1234567890',
                'password' => Hash::make('password123'),
                'role' => 'delivery_agent',
                'kyc_status' => 'approved',
                'is_active' => 1
            ]
        );

        echo "✅ Test data seeded successfully!\n";
    }
}
```

**Run the seeder:**

```bash
php artisan db:seed --class=TestDataSeeder
```

---

## 🛠️ **Step 4: Update Your Routes File**

**Backup and update routes:**

```bash
# Backup current routes
cp routes/api.php routes/api.php.backup3

# Check current content
head -20 routes/api.php
```

**If routes are missing inventory-movements, add them. Create this complete routes file:**

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

    // Legacy inventory routes (if needed)
    Route::get('/inventory/items', [App\Http\Controllers\InventoryController::class, 'items']);
    Route::get('/inventory/overview', [App\Http\Controllers\InventoryController::class, 'overview']);
});
```

---

## 🛠️ **Step 5: Update InventoryMovementController**

**Backup existing controller:**

```bash
cp app/Http/Controllers/InventoryMovementController.php app/Http/Controllers/InventoryMovementController.php.backup
```

**Replace with new implementation:**

**Filename: `app/Http/Controllers/InventoryMovementController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementController extends Controller
{
    /**
     * Store a new inventory movement.
     */
    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $validatedData['created_by'] = auth()->id();
            
            // Generate reference number if not provided
            if (empty($validatedData['reference_number'])) {
                $validatedData['reference_number'] = $this->generateReferenceNumber($validatedData['movement_type']);
            }

            $movement = InventoryMovement::create($validatedData);
            $this->updateProductStock($movement);

            DB::commit();

            $movement->load(['product', 'warehouse', 'sourceWarehouse', 'creator']);

            return response()->json([
                'message' => 'Inventory movement created successfully',
                'data' => $movement,
                'success' => true
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create inventory movement', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create inventory movement',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Get inventory movements for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $movements = InventoryMovement::with(['product', 'warehouse', 'sourceWarehouse', 'creator'])
            ->where('created_by', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Inventory movements retrieved successfully',
            'data' => $movements,
            'success' => true
        ]);
    }

    /**
     * Get a specific inventory movement.
     */
    public function show(InventoryMovement $movement): JsonResponse
    {
        if ($movement->created_by !== auth()->id()) {
            return response()->json([
                'message' => 'Access denied',
                'error' => 'unauthorized_access',
                'success' => false
            ], 403);
        }

        $movement->load(['product', 'warehouse', 'sourceWarehouse', 'creator']);

        return response()->json([
            'message' => 'Inventory movement retrieved successfully',
            'data' => $movement,
            'success' => true
        ]);
    }

    private function generateReferenceNumber(string $movementType): string
    {
        $prefix = match($movementType) {
            'in' => 'IN',
            'out' => 'OUT',
            'transfer' => 'TRF',
            'adjustment' => 'ADJ',
            default => 'MOV'
        };

        $timestamp = now()->format('YmdHis');
        $random = str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$timestamp}-{$random}";
    }

    private function updateProductStock(InventoryMovement $movement): void
    {
        $product = Product::find($movement->product_id);
        
        if (!$product) {
            throw new \Exception('Product not found for stock update');
        }

        switch ($movement->movement_type) {
            case 'in':
                $product->increment('stock_quantity', $movement->quantity);
                break;
            case 'out':
                $product->decrement('stock_quantity', $movement->quantity);
                break;
            case 'adjustment':
                $product->increment('stock_quantity', $movement->quantity);
                break;
        }
    }
}
```

---

## 🛠️ **Step 6: Create Missing Controllers**

```bash
# Create Product and Warehouse controllers
php artisan make:controller ProductController --resource
php artisan make:controller WarehouseController --resource
```

**Quick Product Controller:**

**Filename: `app/Http/Controllers/ProductController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)->get();
        
        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $products,
            'success' => true
        ]);
    }
}
```

**Quick Warehouse Controller:**

**Filename: `app/Http/Controllers/WarehouseController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        
        return response()->json([
            'message' => 'Warehouses retrieved successfully',
            'data' => $warehouses,
            'success' => true
        ]);
    }
}
```

---

## 🛠️ **Step 7: Clear Caches and Test**

```bash
# Clear all caches
php artisan optimize:clear
php artisan route:clear
php artisan config:clear

# Verify everything is working
echo "=== Routes Check ==="
php artisan route:list | grep "inventory-movements"

echo -e "\n=== Database Check ==="
php artisan tinker --execute="
echo 'Products: ' . \App\Models\Product::count();
echo ' | Warehouses: ' . \App\Models\Warehouse::count();
echo ' | Delivery Agents: ' . \App\Models\User::where('role', 'delivery_agent')->count() . PHP_EOL;
"
```

---

## 🧪 **Step 8: Test API Endpoints**

**1. Test login as delivery agent:**

```bash
curl -X POST "http://127.0.0.1:8003/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"agent@vitalvida.com","password":"password123"}' \
  -w "\n"
```

**2. Test inventory movement creation (replace TOKEN):**

```bash
curl -X POST "http://127.0.0.1:8003/api/inventory-movements" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "product_id": 1,
    "warehouse_id": 1,
    "movement_type": "in",
    "quantity": 50.00,
    "unit_cost": 25.99,
    "notes": "Initial stock receipt"
  }' \
  -w "\n"
```

---

## ✅ **Expected Results After Fixes**

```
=== Routes Check ===
POST     api/inventory-movements
GET|HEAD api/inventory-movements
GET|HEAD api/inventory-movements/{movement}

=== Database Check ===
Products: 2 | Warehouses: 2 | Delivery Agents: 1
```

**Please run the fixes step by step and let me know the results!** 🎯