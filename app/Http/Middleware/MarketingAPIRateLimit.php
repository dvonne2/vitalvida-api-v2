<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MarketingAPIRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $key = $this->resolveRequestSignature($request, $user);
        
        // Define rate limits based on user role
        $maxAttempts = $this->getMaxAttempts($user);
        $decayMinutes = $this->getDecayMinutes($user);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Please try again in {$seconds} seconds.",
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
            'X-RateLimit-Reset' => time() + RateLimiter::availableIn($key)
        ]);

        return $response;
    }

    protected function resolveRequestSignature($request, $user)
    {
        $userId = $user ? $user->id : 'guest';
        $ip = $request->ip();
        $route = $request->route()->getName() ?? $request->path();
        
        return "marketing_api:{$userId}:{$ip}:{$route}";
    }

    protected function getMaxAttempts($user)
    {
        if (!$user) {
            return 60; // Guest users: 60 requests per hour
        }

        switch ($user->role) {
            case 'admin':
            case 'ceo':
                return 1000; // Admin: 1000 requests per hour
                
            case 'marketing_manager':
                return 500; // Marketing manager: 500 requests per hour
                
            case 'marketing_agent':
                return 200; // Marketing agent: 200 requests per hour
                
            default:
                return 100; // Default: 100 requests per hour
        }
    }

    protected function getDecayMinutes($user)
    {
        if (!$user) {
            return 60; // 1 hour for guests
        }

        switch ($user->role) {
            case 'admin':
            case 'ceo':
                return 60; // 1 hour for admins
                
            case 'marketing_manager':
                return 60; // 1 hour for managers
                
            case 'marketing_agent':
                return 60; // 1 hour for agents
                
            default:
                return 60; // 1 hour default
        }
    }
}
