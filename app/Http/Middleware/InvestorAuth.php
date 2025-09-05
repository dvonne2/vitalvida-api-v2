<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;

class InvestorAuth
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
        $user = $request->user();

        if (!$user instanceof Investor) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Investor access required.'
            ], 403);
        }

        // Check if investor is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive. Please contact administrator.'
            ], 403);
        }

        // Add investor info to request for easy access
        $request->merge(['investor' => $user]);

        return $next($request);
    }
}
