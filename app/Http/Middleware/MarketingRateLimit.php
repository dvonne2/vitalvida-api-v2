<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MarketingRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required for rate limiting'
            ], 401);
        }

        // Get company subscription plan to determine rate limits
        $company = $user->company;
        $rateLimits = $this->getRateLimitsForCompany($company);
        
        // Override default limits based on subscription
        $maxAttempts = $rateLimits['requests_per_minute'];
        $decayMinutes = 1;

        // Create rate limit key based on user and company
        $key = $this->resolveRequestSignature($request, $user);

        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Try again in {$retryAfter} seconds.",
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'subscription_plan' => $company->subscription_plan ?? 'basic'
            ], 429);
        }

        // Increment the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - RateLimiter::attempts($key)),
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);

        return $response;
    }

    /**
     * Get rate limits based on company subscription plan
     */
    protected function getRateLimitsForCompany($company)
    {
        $subscriptionPlan = $company->subscription_plan ?? 'basic';
        
        return match($subscriptionPlan) {
            'enterprise' => [
                'requests_per_minute' => 300,
                'whatsapp_messages_per_hour' => 10000,
                'ai_generations_per_day' => 1000,
                'campaigns_per_month' => 100
            ],
            'professional' => [
                'requests_per_minute' => 150,
                'whatsapp_messages_per_hour' => 5000,
                'ai_generations_per_day' => 500,
                'campaigns_per_month' => 50
            ],
            'standard' => [
                'requests_per_minute' => 100,
                'whatsapp_messages_per_hour' => 2000,
                'ai_generations_per_day' => 200,
                'campaigns_per_month' => 20
            ],
            'basic' => [
                'requests_per_minute' => 60,
                'whatsapp_messages_per_hour' => 500,
                'ai_generations_per_day' => 50,
                'campaigns_per_month' => 5
            ],
            default => [
                'requests_per_minute' => 30,
                'whatsapp_messages_per_hour' => 100,
                'ai_generations_per_day' => 10,
                'campaigns_per_month' => 2
            ]
        };
    }

    /**
     * Resolve the rate limiting signature for the request
     */
    protected function resolveRequestSignature(Request $request, $user)
    {
        // Create unique key based on user, company, and endpoint type
        $endpoint = $this->getEndpointType($request);
        
        return sprintf(
            'marketing_rate_limit:%s:%s:%s',
            $user->company_id,
            $user->id,
            $endpoint
        );
    }

    /**
     * Determine the endpoint type for specific rate limiting
     */
    protected function getEndpointType(Request $request)
    {
        $path = $request->path();
        
        if (str_contains($path, 'whatsapp')) {
            return 'whatsapp';
        } elseif (str_contains($path, 'content/generate')) {
            return 'ai_generation';
        } elseif (str_contains($path, 'campaigns')) {
            return 'campaigns';
        } elseif (str_contains($path, 'analytics')) {
            return 'analytics';
        } else {
            return 'general';
        }
    }
}
