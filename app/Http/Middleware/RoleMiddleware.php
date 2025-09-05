<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $user = $request->user();
        
        // Convert comma-separated string to array if needed
        if (count($roles) === 1 && str_contains($roles[0], ',')) {
            $roles = explode(',', $roles[0]);
        }
        
        // Superadmin can access everything
        if ($user->role === 'superadmin' || $user->hasRole('admin')) {
            return $next($request);
        }
        
        // Check if user has any of the required roles (both old and new system)
        $hasPermission = false;
        foreach ($roles as $role) {
            if ($user->role === $role || $user->hasRole($role)) {
                $hasPermission = true;
                break;
            }
        }
        
        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles),
                'user_role' => $user->role,
                'user_roles' => $user->roles->pluck('name')->toArray()
            ], 403);
        }
        
        return $next($request);
    }
}
