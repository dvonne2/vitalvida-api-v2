<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\ImDailyLog;
use App\Models\SystemRecommendation;
use App\Models\Bin;
use Illuminate\Http\Request;

class DAReviewController extends Controller
{
    public function getDAList(Request $request)
    {
        $deliveryAgents = DeliveryAgent::with(['user'])->where('status', 'active')->get()->map(function($da) {
            $bin = Bin::where('delivery_agent_id', $da->id)->first();
            return [
                'id' => $da->id,
                'da_code' => $da->da_code,
                'name' => $da->user->name ?? 'Unknown',
                'stock_status' => [
                    'current_stock' => $bin->current_stock_count ?? 0,
                    'is_critically_low' => ($bin->current_stock_count ?? 0) < 10,
                    'needs_restock' => ($bin->current_stock_count ?? 0) < 15
                ],
                'performance' => [
                    'success_rate' => $da->total_deliveries > 0 ? round(($da->successful_deliveries / $da->total_deliveries) * 100, 2) : 0,
                    'strikes' => $da->strikes_count ?? 0
                ]
            ];
        });
        
        return response()->json(['status' => 'success', 'data' => $deliveryAgents]);
    }
    
    public function completeDaReview(Request $request, $daId)
    {
        $user = $request->user();
        $dailyLog = ImDailyLog::firstOrCreate(['user_id' => $user->id, 'log_date' => today()]);
        $dailyLog->increment('das_reviewed_count');
        
        return response()->json(['status' => 'success', 'message' => 'DA review completed']);
    }
}
