<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MarketingBrandController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketingBrand::with(['creator', 'campaigns', 'contentLibrary'])
            ->where('company_id', auth()->user()->company_id);
            
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }
        
        $brands = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'website_url' => 'nullable|url',
            'social_media_handles' => 'nullable|array',
            'target_audience' => 'nullable|string',
            'brand_voice' => 'nullable|string',
            'key_messages' => 'nullable|array',
            'status' => 'nullable|in:active,inactive'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $brand = MarketingBrand::create([
                'name' => $request->name,
                'description' => $request->description,
                'logo_url' => $request->logo_url,
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
                'website_url' => $request->website_url,
                'social_media_handles' => $request->social_media_handles,
                'target_audience' => $request->target_audience,
                'brand_voice' => $request->brand_voice,
                'key_messages' => $request->key_messages,
                'status' => $request->status ?? 'active',
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Brand created successfully',
                'data' => $brand->load(['creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create brand: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $brand = MarketingBrand::with(['creator', 'campaigns', 'contentLibrary', 'customerTouchpoints', 'referrals'])
            ->where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $brand
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $brand = MarketingBrand::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'website_url' => 'nullable|url',
            'social_media_handles' => 'nullable|array',
            'target_audience' => 'nullable|string',
            'brand_voice' => 'nullable|string',
            'key_messages' => 'nullable|array',
            'status' => 'nullable|in:active,inactive'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $brand->update($request->only([
                'name', 'description', 'logo_url', 'primary_color', 'secondary_color',
                'website_url', 'social_media_handles', 'target_audience', 'brand_voice',
                'key_messages', 'status'
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'Brand updated successfully',
                'data' => $brand->load(['creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update brand: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        $brand = MarketingBrand::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }
        
        // Check if brand has active campaigns
        if ($brand->campaigns()->where('status', 'active')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete brand with active campaigns'
            ], 400);
        }
        
        try {
            $brand->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Brand deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete brand: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function duplicate($id)
    {
        $originalBrand = MarketingBrand::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$originalBrand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }
        
        try {
            $duplicatedBrand = $originalBrand->replicate();
            $duplicatedBrand->name = $originalBrand->name . ' (Copy)';
            $duplicatedBrand->created_by = auth()->id();
            $duplicatedBrand->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Brand duplicated successfully',
                'data' => $duplicatedBrand->load(['creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate brand: ' . $e->getMessage()
            ], 500);
        }
    }
}
