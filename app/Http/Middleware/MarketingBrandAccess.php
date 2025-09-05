<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Marketing\MarketingBrand;
use Symfony\Component\HttpFoundation\Response;

class MarketingBrandAccess
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

        // Get brand ID from request
        $brandId = $request->route('brand_id') ?? $request->input('brand_id');
        
        if (!$brandId) {
            return response()->json([
                'error' => 'Brand ID required',
                'message' => 'Brand ID must be provided'
            ], 400);
        }

        // Check if user has access to this brand
        $hasBrandAccess = $this->checkBrandAccess($user, $brandId);
        
        if (!$hasBrandAccess) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Access to this brand is not allowed'
            ], 403);
        }

        // Add brand info to request
        $request->merge([
            'brand_id' => $brandId,
            'brand_access' => true
        ]);

        return $next($request);
    }

    protected function checkBrandAccess($user, $brandId)
    {
        // Super admin has access to all brands
        if ($user->role === 'admin' || $user->role === 'ceo') {
            return true;
        }

        // Get the brand
        $brand = MarketingBrand::find($brandId);
        
        if (!$brand) {
            return false;
        }

        // Check if brand belongs to user's company
        if ($brand->company_id !== $user->company_id) {
            return false;
        }

        // Check user's brand-specific permissions
        if ($user->brand_permissions) {
            $brandPermissions = json_decode($user->brand_permissions, true);
            
            if (isset($brandPermissions[$brandId]) && $brandPermissions[$brandId]) {
                return true;
            }
        }

        // Check if user is assigned to this brand
        if ($user->assigned_brands) {
            $assignedBrands = json_decode($user->assigned_brands, true);
            
            if (in_array($brandId, $assignedBrands)) {
                return true;
            }
        }

        // Marketing managers have access to all brands in their company
        if ($user->role === 'marketing_manager' && $brand->company_id === $user->company_id) {
            return true;
        }

        return false;
    }
}
