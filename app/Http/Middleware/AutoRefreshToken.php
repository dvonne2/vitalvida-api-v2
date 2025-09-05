<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AutoRefreshToken
{
    public function handle(Request $request, Closure $next)
    {
        // Only process if user is authenticated
        if (!Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        $currentToken = $user->currentAccessToken();

        // If no current token, continue without refresh
        if (!$currentToken) {
            return $next($request);
        }

        // Check if token is close to expiry (refresh if less than 1 hour remaining)
        $tokenCreatedAt = $currentToken->created_at;
        $tokenLifetime = config('sanctum.expiration', 60 * 24); // Default 24 hours in minutes
        $refreshThreshold = 60; // Refresh if less than 1 hour remaining (in minutes)

        $minutesSinceCreation = $tokenCreatedAt->diffInMinutes(now());
        $minutesUntilExpiry = $tokenLifetime - $minutesSinceCreation;

        // If token expires soon, create a new one
        if ($minutesUntilExpiry <= $refreshThreshold) {
            
            // Create new token
            $newToken = $user->createToken('auth_token')->plainTextToken;
            
            // Delete old token
            $currentToken->delete();
            
            // Process the request
            $response = $next($request);
            
            // Add the new token to response headers
            $response->headers->set('X-New-Token', $newToken);
            $response->headers->set('X-Token-Refreshed', 'true');
            
            // Also add to response body if it's JSON
            if ($response->headers->get('content-type') === 'application/json' || 
                str_contains($response->headers->get('content-type', ''), 'application/json')) {
                
                $content = json_decode($response->getContent(), true);
                
                if (is_array($content)) {
                    $content['token_refreshed'] = true;
                    $content['new_access_token'] = $newToken;
                    $content['token_type'] = 'Bearer';
                    $response->setContent(json_encode($content));
                }
            }
            
            return $response;
        }

        // Token is still fresh, continue normally
        return $next($request);
    }
}
