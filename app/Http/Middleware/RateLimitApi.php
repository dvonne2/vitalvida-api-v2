<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RateLimitApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $key = 'api_rate_limit:' . $request->ip();
        $maxAttempts = 100; // per minute
        
        if (Cache::get($key, 0) >= $maxAttempts) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => 60
            ], 429);
        }
        
        Cache::increment($key, 1);
        Cache::expire($key, 60); // 1 minute
        
        return $next($request);
    }
} 