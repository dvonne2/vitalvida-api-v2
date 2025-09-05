<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
                'code' => 'AUTHENTICATION_REQUIRED'
            ], 401);
        }

        $user = auth()->user();
        
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles),
                'code' => 'INSUFFICIENT_PERMISSIONS'
            ], 403);
        }
        
        return $next($request);
    }
}
