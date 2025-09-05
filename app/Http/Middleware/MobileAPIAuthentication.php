<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\APIGatewayService;

class MobileAPIAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        // Check for mobile API key
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'API key required for mobile access'
            ], 401);
        }

        // Validate API key
        $keyRecord = \App\Models\ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired API key'
            ], 401);
        }

        // Set authenticated user
        auth()->setUser($keyRecord->user);

        // Update last used timestamp
        $keyRecord->update(['last_used_at' => now()]);

        return $next($request);
    }
} 