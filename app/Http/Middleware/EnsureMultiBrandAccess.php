<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Marketing\MarketingBrand;
use Symfony\Component\HttpFoundation\Response;

class EnsureMultiBrandAccess
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

        // Check if user has multi-brand access
        $hasMultiBrandAccess = $this->checkMultiBrandAccess($user);
        
        if (!$hasMultiBrandAccess) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Multi-brand access required'
            ], 403);
        }

        // Get user's accessible brands
        $accessibleBrands = $this->getAccessibleBrands($user);
        
        if ($accessibleBrands->isEmpty()) {
            return response()->json([
                'error' => 'No brands accessible',
                'message' => 'User has no accessible brands'
            ], 403);
        }

        // Add brand information to request
        $request->merge([
            'accessible_brands' => $accessibleBrands,
            'multi_brand_access' => true,
            'primary_brand_id' => $accessibleBrands->first()->id
        ]);

        return $next($request);
    }

    protected function checkMultiBrandAccess($user)
    {
        // Super admins and CEOs have multi-brand access
        if (in_array($user->role, ['admin', 'ceo'])) {
            return true;
        }

        // Marketing managers have multi-brand access
        if ($user->role === 'marketing_manager') {
            return true;
        }

        // Check if user has multi-brand permission
        if ($user->permissions && in_array('multi_brand_access', $user->permissions)) {
            return true;
        }

        // Check if user is assigned to multiple brands
        if ($user->assigned_brands) {
            $assignedBrands = json_decode($user->assigned_brands, true);
            return count($assignedBrands) > 1;
        }

        // Check if user's company has multiple brands
        $brandCount = MarketingBrand::where('company_id', $user->company_id)->count();
        return $brandCount > 1;
    }

    protected function getAccessibleBrands($user)
    {
        $query = MarketingBrand::where('company_id', $user->company_id);

        // Super admins and CEOs can access all brands
        if (in_array($user->role, ['admin', 'ceo'])) {
            return $query->get();
        }

        // Marketing managers can access all brands in their company
        if ($user->role === 'marketing_manager') {
            return $query->get();
        }

        // Check user's assigned brands
        if ($user->assigned_brands) {
            $assignedBrandIds = json_decode($user->assigned_brands, true);
            return $query->whereIn('id', $assignedBrandIds)->get();
        }

        // Check user's brand permissions
        if ($user->brand_permissions) {
            $brandPermissions = json_decode($user->brand_permissions, true);
            $accessibleBrandIds = array_keys(array_filter($brandPermissions));
            return $query->whereIn('id', $accessibleBrandIds)->get();
        }

        // Default: return empty collection
        return collect();
    }
}
