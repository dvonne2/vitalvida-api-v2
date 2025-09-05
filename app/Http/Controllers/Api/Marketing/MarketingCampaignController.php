<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingBrand;
use App\Jobs\Marketing\ProcessOmnipresenceMarketingCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketingCampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketingCampaign::with(['brand', 'creator'])
            ->where('company_id', auth()->user()->company_id);
            
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:meta,tiktok,google,email,whatsapp',
            'whatsapp_providers' => 'nullable|array',
            'whatsapp_providers.*' => 'string|in:wamation,ebulksms,whatsapp_business',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'budget_total' => 'nullable|numeric|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $campaign = MarketingCampaign::create([
                'brand_id' => $request->brand_id,
                'name' => $request->name,
                'status' => 'draft',
                'channels' => $request->channels,
                'whatsapp_providers' => $request->whatsapp_providers,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'budget_total' => $request->budget_total,
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => $campaign->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $campaign = MarketingCampaign::with(['brand', 'creator', 'customerTouchpoints'])
            ->where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $campaign = MarketingCampaign::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'channels' => 'sometimes|array|min:1',
            'channels.*' => 'string|in:meta,tiktok,google,email,whatsapp',
            'whatsapp_providers' => 'nullable|array',
            'whatsapp_providers.*' => 'string|in:wamation,ebulksms,whatsapp_business',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'budget_total' => 'nullable|numeric|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $campaign->update($request->only([
                'name', 'channels', 'whatsapp_providers', 
                'start_date', 'end_date', 'budget_total'
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => $campaign->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        $campaign = MarketingCampaign::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }
        
        try {
            $campaign->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function launch($id)
    {
        $campaign = MarketingCampaign::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }
        
        if ($campaign->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft campaigns can be launched'
            ], 400);
        }
        
        try {
            // Update campaign status
            $campaign->update(['status' => 'active']);
            
            // Dispatch job to process omnipresence campaign
            ProcessOmnipresenceMarketingCampaign::dispatch($campaign);
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign launched successfully',
                'data' => $campaign->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to launch campaign: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getPerformance($id)
    {
        $campaign = MarketingCampaign::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }
        
        // Get campaign performance metrics
        $touchpoints = $campaign->customerTouchpoints()
            ->with(['customer', 'content'])
            ->get();
            
        $channelStats = $touchpoints->groupBy('channel')
            ->map(function ($channelTouchpoints) {
                return [
                    'total' => $channelTouchpoints->count(),
                    'delivered' => $channelTouchpoints->where('interaction_type', 'delivered')->count(),
                    'converted' => $channelTouchpoints->where('interaction_type', 'converted')->count()
                ];
            });
            
        $performance = [
            'campaign' => $campaign,
            'total_touchpoints' => $touchpoints->count(),
            'channel_stats' => $channelStats,
            'roi' => $campaign->roi,
            'roi_percentage' => $campaign->roi_percentage,
            'budget_utilization' => $campaign->budget_utilization,
            'touchpoints' => $touchpoints->take(10) // Recent touchpoints
        ];
        
        return response()->json([
            'success' => true,
            'data' => $performance
        ]);
    }
}
