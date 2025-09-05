<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\APIGatewayService;

class MobileRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $service = $request->route('service');
        
        $rateLimitResult = app(APIGatewayService::class)
            ->checkRateLimit($request, $user, $service);

        if (!$rateLimitResult['allowed']) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'retry_after' => $rateLimitResult['retry_after']
            ], 429)
            ->header('X-RateLimit-Limit', $rateLimitResult['limit'])
            ->header('X-RateLimit-Remaining', $rateLimitResult['remaining'])
            ->header('Retry-After', $rateLimitResult['retry_after']);
        }

        return $next($request);
    }
} 