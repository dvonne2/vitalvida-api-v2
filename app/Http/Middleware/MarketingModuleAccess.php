<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MarketingModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized access',
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if user has marketing module access
        $hasMarketingAccess = $this->checkMarketingAccess($user);
        
        if (!$hasMarketingAccess) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Marketing module access required'
            ], 403);
        }

        // Add marketing access info to request
        $request->merge([
            'marketing_access' => true,
            'user_company_id' => $user->company_id
        ]);

        return $next($request);
    }

    protected function checkMarketingAccess($user)
    {
        // Check user role for marketing access
        $marketingRoles = ['admin', 'marketing_manager', 'marketing_agent', 'ceo', 'gm'];
        
        if (in_array($user->role, $marketingRoles)) {
            return true;
        }

        // Check user permissions
        if ($user->permissions && in_array('marketing_access', $user->permissions)) {
            return true;
        }

        // Check if user is assigned to marketing department
        if ($user->department === 'marketing') {
            return true;
        }

        // Check company-level marketing access
        if ($user->company && $user->company->marketing_enabled) {
            return true;
        }

        return false;
    }
}
