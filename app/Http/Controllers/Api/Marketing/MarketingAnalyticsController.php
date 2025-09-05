<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Marketing\MarketingWhatsAppLog;
use App\Models\Sale;
use App\Models\Customer;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsController extends Controller
{
    public function getPerformance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30));
        $endDate = $request->get('end_date', Carbon::now());
        
        // Overall performance metrics
        $totalCampaigns = MarketingCampaign::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $activeCampaigns = MarketingCampaign::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
            
        $totalSpend = MarketingCampaign::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('actual_spend');
            
        $totalRevenue = Sale::where('company_id', $companyId)
            ->whereHas('marketingTouchpoints')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');
            
        $totalTouchpoints = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $conversions = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->where('interaction_type', 'converted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Calculate metrics
        $roi = $totalSpend > 0 ? ($totalRevenue / $totalSpend) : 0;
        $conversionRate = $totalTouchpoints > 0 ? ($conversions / $totalTouchpoints) * 100 : 0;
        
        // Daily performance trend
        $dailyPerformance = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as touchpoints'),
                DB::raw('COUNT(CASE WHEN interaction_type = "converted" THEN 1 END) as conversions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_campaigns' => $totalCampaigns,
                    'active_campaigns' => $activeCampaigns,
                    'total_spend' => $totalSpend,
                    'total_revenue' => $totalRevenue,
                    'roi' => $roi,
                    'total_touchpoints' => $totalTouchpoints,
                    'conversions' => $conversions,
                    'conversion_rate' => $conversionRate
                ],
                'daily_performance' => $dailyPerformance
            ]
        ]);
    }
    
    public function getCustomerJourney(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $customerId = $request->get('customer_id');
        
        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Customer ID is required'
            ], 400);
        }
        
        // Get customer touchpoints in chronological order
        $touchpoints = MarketingCustomerTouchpoint::with(['campaign', 'content'])
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->orderBy('created_at')
            ->get();
            
        // Get customer sales attributed to marketing
        $sales = Sale::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereHas('marketingTouchpoints')
            ->with('marketingTouchpoints')
            ->get();
            
        // Journey analytics
        $firstTouch = $touchpoints->first();
        $lastTouch = $touchpoints->last();
        $totalTouchpoints = $touchpoints->count();
        $channelsUsed = $touchpoints->pluck('channel')->unique()->values();
        $timeToConversion = null;
        
        if ($firstTouch && $sales->isNotEmpty()) {
            $firstSale = $sales->first();
            $timeToConversion = $firstTouch->created_at->diffInDays($firstSale->created_at);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'customer_id' => $customerId,
                'journey_summary' => [
                    'total_touchpoints' => $totalTouchpoints,
                    'channels_used' => $channelsUsed,
                    'time_to_conversion_days' => $timeToConversion,
                    'total_sales' => $sales->sum('total_amount'),
                    'first_touch_date' => $firstTouch?->created_at,
                    'last_touch_date' => $lastTouch?->created_at
                ],
                'touchpoints' => $touchpoints,
                'sales' => $sales
            ]
        ]);
    }
    
    public function getROI(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30));
        $endDate = $request->get('end_date', Carbon::now());
        $groupBy = $request->get('group_by', 'campaign'); // campaign, channel, brand
        
        $query = MarketingCampaign::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate]);
            
        if ($groupBy === 'campaign') {
            $roiData = $query->select('id', 'name', 'actual_spend')
                ->with(['customerTouchpoints.sales'])
                ->get()
                ->map(function ($campaign) {
                    $revenue = $campaign->customerTouchpoints
                        ->flatMap(function ($touchpoint) {
                            return $touchpoint->sales;
                        })
                        ->sum('total_amount');
                        
                    $roi = $campaign->actual_spend > 0 ? ($revenue / $campaign->actual_spend) : 0;
                    
                    return [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'spend' => $campaign->actual_spend,
                        'revenue' => $revenue,
                        'roi' => $roi,
                        'roi_percentage' => $roi * 100
                    ];
                });
        } elseif ($groupBy === 'channel') {
            $roiData = MarketingCustomerTouchpoint::where('company_id', $companyId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('channel')
                ->with(['campaign', 'sales'])
                ->get()
                ->groupBy('channel')
                ->map(function ($touchpoints, $channel) {
                    $spend = $touchpoints->sum(function ($touchpoint) {
                        return $touchpoint->campaign?->actual_spend ?? 0;
                    });
                    
                    $revenue = $touchpoints->flatMap(function ($touchpoint) {
                        return $touchpoint->sales;
                    })->sum('total_amount');
                    
                    $roi = $spend > 0 ? ($revenue / $spend) : 0;
                    
                    return [
                        'channel' => $channel,
                        'spend' => $spend,
                        'revenue' => $revenue,
                        'roi' => $roi,
                        'roi_percentage' => $roi * 100
                    ];
                })
                ->values();
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'group_by' => $groupBy,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'roi_data' => $roiData
            ]
        ]);
    }
    
    public function getChannelPerformance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30));
        $endDate = $request->get('end_date', Carbon::now());
        
        $channelPerformance = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'channel',
                DB::raw('COUNT(*) as total_touchpoints'),
                DB::raw('COUNT(CASE WHEN interaction_type = "delivered" THEN 1 END) as delivered'),
                DB::raw('COUNT(CASE WHEN interaction_type = "opened" THEN 1 END) as opened'),
                DB::raw('COUNT(CASE WHEN interaction_type = "clicked" THEN 1 END) as clicked'),
                DB::raw('COUNT(CASE WHEN interaction_type = "converted" THEN 1 END) as converted')
            )
            ->groupBy('channel')
            ->get()
            ->map(function ($channel) {
                $deliveryRate = $channel->total_touchpoints > 0 ? 
                    ($channel->delivered / $channel->total_touchpoints) * 100 : 0;
                $openRate = $channel->delivered > 0 ? 
                    ($channel->opened / $channel->delivered) * 100 : 0;
                $clickRate = $channel->opened > 0 ? 
                    ($channel->clicked / $channel->opened) * 100 : 0;
                $conversionRate = $channel->total_touchpoints > 0 ? 
                    ($channel->converted / $channel->total_touchpoints) * 100 : 0;
                    
                return [
                    'channel' => $channel->channel,
                    'total_touchpoints' => $channel->total_touchpoints,
                    'delivered' => $channel->delivered,
                    'opened' => $channel->opened,
                    'clicked' => $channel->clicked,
                    'converted' => $channel->converted,
                    'delivery_rate' => $deliveryRate,
                    'open_rate' => $openRate,
                    'click_rate' => $clickRate,
                    'conversion_rate' => $conversionRate
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => $channelPerformance
        ]);
    }
    
    public function getWhatsAppProviderPerformance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30));
        $endDate = $request->get('end_date', Carbon::now());
        
        $providerPerformance = MarketingWhatsAppLog::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'provider',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('COUNT(CASE WHEN status = "delivered" THEN 1 END) as delivered'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed'),
                DB::raw('AVG(response_time_ms) as avg_response_time')
            )
            ->groupBy('provider')
            ->get()
            ->map(function ($provider) {
                $deliveryRate = $provider->total_attempts > 0 ? 
                    ($provider->delivered / $provider->total_attempts) * 100 : 0;
                $failureRate = $provider->total_attempts > 0 ? 
                    ($provider->failed / $provider->total_attempts) * 100 : 0;
                    
                return [
                    'provider' => $provider->provider,
                    'total_attempts' => $provider->total_attempts,
                    'delivered' => $provider->delivered,
                    'failed' => $provider->failed,
                    'delivery_rate' => $deliveryRate,
                    'failure_rate' => $failureRate,
                    'avg_response_time_ms' => round($provider->avg_response_time, 2)
                ];
            });
            
        // Provider reliability ranking
        $rankedProviders = $providerPerformance->sortByDesc('delivery_rate')->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'provider_performance' => $providerPerformance,
                'provider_ranking' => $rankedProviders,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]
        ]);
    }
}
