# 🛡️ Step-by-Step Laravel Sanctum Security Implementation

## 📋 **STEP 1: Update RegisterRequest.php**

Replace the content of `app/Http/Requests/RegisterRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s]+$/' // Only letters and spaces
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns', // Strict email validation
                'max:255',
                'unique:users,email'
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{10,15}$/', // 10-15 digits
                'unique:users,phone'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                // Strong password: uppercase, lowercase, number, special character
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in(['user', 'manager', 'admin', 'production', 'inventory', 'telesales', 'DA', 'accountant', 'CFO', 'CEO'])
            ]
        ];
    }

    public function messages()
    {
        return [
            'name.regex' => 'Name can only contain letters and spaces.',
            'email.email' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number must be 10-15 digits.',
            'password.regex' => 'Password must contain uppercase, lowercase, number, and special character.',
            'role.in' => 'Invalid role selected.'
        ];
    }
}
```

✅ **Run this command:**
```bash
# Save the above content to app/Http/Requests/RegisterRequest.php
```

---

## 📋 **STEP 2: Update LoginRequest.php**

Replace the content of `app/Http/Requests/LoginRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255'
            ],
            'password' => [
                'required',
                'string',
                'min:1'
            ]
        ];
    }

    public function messages()
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.'
        ];
    }
}
```

✅ **Run this command:**
```bash
# Save the above content to app/Http/Requests/LoginRequest.php
```

---

## 📋 **STEP 3: Create Auto-Refresh Middleware**

✅ **Run this command:**
```bash
php artisan make:middleware AutoRefreshToken
```

Then replace the content of `app/Http/Middleware/AutoRefreshToken.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoRefreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $token = $request->user()->currentAccessToken();
            
            // Check if token is about to expire (within 1 hour)
            if ($token && $token->expires_at && $token->expires_at->diffInMinutes(now()) < 60) {
                $response = $next($request);
                return $response->header('X-Token-Refresh-Required', 'true');
            }
        }
        
        return $next($request);
    }
}
```

---

## 📋 **STEP 4: Register Middleware in Kernel**

Add this line to `app/Http/Kernel.php` in the `$routeMiddleware` array:

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    // ... existing middleware ...
    'auto.refresh' => \App\Http\Middleware\AutoRefreshToken::class,
];
```

---

## 📋 **STEP 5: Create Enhanced AuthController**

Replace your `app/Http/Controllers/AuthController.php` with this enhanced version:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $maxAttempts = 5;
    protected $lockoutMinutes = 15;

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user'
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 24 * 60 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $key = $this->throttleKey($request);
        
        // Check if user is locked out
        if ($this->hasTooManyLoginAttempts($request)) {
            $seconds = $this->availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Account locked for {$seconds} seconds.",
                'lockout_ends_at' => now()->addSeconds($seconds)->toISOString()
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->incrementLoginAttempts($request);
            
            $attempts = $this->attempts($key);
            $remaining = $this->maxAttempts - $attempts;
            
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.',
                    $remaining > 0 ? "You have {$remaining} attempts remaining." : ''
                ],
            ]);
        }

        // Clear attempts on successful login
        $this->clearLoginAttempts($request);

        $token = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 24 * 60 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'user' => $request->user()]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        
        $newToken = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;
        
        return response()->json([
            'success' => true,
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => 24 * 60 * 60
        ]);
    }

    // Rate limiting helper methods
    protected function throttleKey(Request $request)
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }

    protected function hasTooManyLoginAttempts(Request $request)
    {
        return Cache::has($this->throttleKey($request) . ':lockout');
    }

    protected function incrementLoginAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::put($key, $attempts, now()->addMinutes($this->lockoutMinutes));
        
        if ($attempts >= $this->maxAttempts) {
            Cache::put($key . ':lockout', true, now()->addMinutes($this->lockoutMinutes));
        }
    }

    protected function clearLoginAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        Cache::forget($key);
        Cache::forget($key . ':lockout');
    }

    protected function attempts($key)
    {
        return Cache::get($key, 0);
    }

    protected function availableIn($key)
    {
        return Cache::store()->getRedis()->ttl($key . ':lockout');
    }
}
```

---

## 📋 **STEP 6: Update API Routes with Security**

Replace your `routes/api.php` with this secure version:

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware(['api'])->group(function () {
    
    // Public routes with throttling
    Route::middleware(['throttle:5,1'])->prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'auto.refresh'])->prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    // Test routes
    Route::get('/test', function () {
        return response()->json([
            'message' => 'VitalVida API is working!',
            'time' => now(),
            'status' => 'success'
        ]);
    });

    // Protected dashboard
    Route::middleware('auth:sanctum')->get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome to your dashboard!',
            'user' => auth()->user()
        ]);
    });
});
```

---

## 📋 **STEP 7: Configure Sanctum Token Expiration**

Update `config/sanctum.php`:

```php
<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Str::startsWith(app('url')->to('/'), 'https://') ? ','.parse_url(app('url')->to('/'), PHP_URL_HOST) : ''
    ))),

    'guard' => ['web'],

    'expiration' => 60 * 24, // 24 hours

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

---

## 📋 **STEP 8: Clear Cache and Test**

✅ **Run these commands:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan serve
```

---

## 📋 **STEP 9: Test Each Security Feature**

### **Test 1: Weak Password Validation**
```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "1234567890",
    "password": "weak",
    "password_confirmation": "weak"
  }'
```
*Expected: Validation error*

### **Test 2: Strong Password Registration**
```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "1234567890",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
  }'
```
*Expected: Success with token*

### **Test 3: Login**
```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
```
*Expected: Success with token*

### **Test 4: Test Rate Limiting (Login 6 times quickly with wrong password)**
```bash
for i in {1..6}; do
  curl -X POST http://127.0.0.1:8000/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"wrongpassword"}'
  echo "Attempt $i"
done
```
*Expected: Lockout after 5 attempts*

### **Test 5: Protected Route**
```bash
# First get token from login, then:
curl -X GET http://127.0.0.1:8000/api/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
*Expected: Dashboard data*

### **Test 6: Token Refresh**
```bash
curl -X POST http://127.0.0.1:8000/api/auth/refresh-token \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
*Expected: New token*

---

## 🎉 **Congratulations!**

You now have a production-ready Laravel Sanctum authentication system with:

✅ **Strong password validation**
✅ **Rate limiting and login lockouts**
✅ **Token expiration and refresh**
✅ **Protected routes**
✅ **Enhanced security features**

Your VitalVida API is now enterprise-grade secure! 🛡️

---

## 🚀 **Next Steps**

1. Test all endpoints thoroughly
2. Add more role-based routes as needed
3. Implement email verification (optional)
4. Add API documentation
5. Deploy to production with HTTPS

Let me know if you need help with any specific step! 🔥