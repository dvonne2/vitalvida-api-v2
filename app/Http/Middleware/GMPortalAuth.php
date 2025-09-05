<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GMPortalAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !in_array(auth()->user()->role, ['gm', 'coo', 'finance', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to GM Portal. Only GM, COO, Finance, and Admin roles are allowed.',
                'code' => 'UNAUTHORIZED_ACCESS'
            ], 403);
        }
        
        return $next($request);
    }
}
