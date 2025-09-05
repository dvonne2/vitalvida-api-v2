<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): JsonResponse|Request
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
                'error' => 'Authentication required'
            ], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated',
                'error' => 'Account inactive'
            ], 403);
        }

        // Check if user has the required permission
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Insufficient permissions',
                'required_permission' => $permission,
                'user_permissions' => $user->getRolePermissions()
            ], 403);
        }

        return $next($request);
    }
} 