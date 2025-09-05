<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeliveryAgentMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
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

        // Check if user is a delivery agent or has delivery agent access
        if (!$user->hasAnyRole(['delivery_agent', 'admin', 'superadmin']) && !$user->delivery_agent_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Delivery agent access required'
            ], 403);
        }

        // If user has a specific delivery agent ID, ensure they can only access their data
        if ($user->delivery_agent_id && $request->has('delivery_agent_id')) {
            $requestAgentId = $request->get('delivery_agent_id');
            if ($user->delivery_agent_id != $requestAgentId && !$user->hasAnyRole(['admin', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'error' => 'You can only access your own delivery agent data'
                ], 403);
            }
        }

        return $next($request);
    }
} 