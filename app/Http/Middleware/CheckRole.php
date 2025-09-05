<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Please login.',
                'error_code' => 'UNAUTHORIZED'
            ], 401);
        }

        $user = auth()->user();

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated. Please contact administrator.',
                'error_code' => 'ACCOUNT_DEACTIVATED'
            ], 403);
        }

        // Check if user has the required role
        if (!$user->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient permissions.',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
                'required_role' => $role,
                'user_role' => $user->role
            ], 403);
        }

        // Check KYC status for certain roles (except superadmin)
        if ($user->role !== 'superadmin' && $user->kyc_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification required. Please complete your verification.',
                'error_code' => 'KYC_REQUIRED',
                'kyc_status' => $user->kyc_status
            ], 403);
        }

        return $next($request);
    }
} 