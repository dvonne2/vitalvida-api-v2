<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\SystemRecommendation;
use App\Models\Bin;
use App\Models\ImDailyLog;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    // GET /api/inventory/recommendations/generate
    public function generateRecommendations(Request $request)
    {
        $user = $request->user();
        
        // Clear expired recommendations
        SystemRecommendation::where('assigned_to', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->update(['status' => 'expired']);
        
        $recommendations = collect();
        
        // 1. CRITICAL: Stockout Detection
        $stockoutRisks = $this->detectStockoutRisks();
        foreach ($stockoutRisks as $risk) {
            $recommendations->push($this->createRecommendation(
                $risk['da_id'],
                'restock',
                'critical',
                "🚨 URGENT: {$risk['da_code']} will run out of stock in {$risk['days_remaining']} days. Current: {$risk['current_stock']} units. Restock {$risk['recommended_quantity']} units immediately.",
                ['current_stock' => $risk['current_stock'], 'recommended_quantity' => $risk['recommended_quantity']],
                $user->id
            ));
        }
        
        // 2. HIGH: Low Stock Warnings
        $lowStockAgents = $this->detectLowStock();
        foreach ($lowStockAgents as $agent) {
            $recommendations->push($this->createRecommendation(
                $agent['da_id'],
                'restock',
                'high',
                "⚠️ LOW STOCK: {$agent['da_code']} has {$agent['current_stock']} units remaining. Restock {$agent['recommended_quantity']} units within 48 hours.",
                ['current_stock' => $agent['current_stock'], 'recommended_quantity' => $agent['recommended_quantity']],
                $user->id
            ));
        }
        
        // 3. MEDIUM: Optimization Opportunities
        $optimizations = $this->detectOptimizationOpportunities();
        foreach ($optimizations as $opt) {
            $recommendations->push($this->createRecommendation(
                $opt['da_id'],
                $opt['type'],
                'medium',
                $opt['message'],
                $opt['data'],
                $user->id
            ));
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $recommendations->toArray(),
            'summary' => [
                'total_recommendations' => $recommendations->count(),
                'critical' => $recommendations->where('priority', 'critical')->count(),
                'high' => $recommendations->where('priority', 'high')->count(),
                'medium' => $recommendations->where('priority', 'medium')->count(),
                'generated_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }
    
    // POST /api/inventory/recommendations/execute/{recommendation_id}
    public function executeRecommendation(Request $request, $recommendationId)
    {
        $request->validate([
            'action_taken' => 'required|string',
            'notes' => 'string|nullable',
            'quantity_processed' => 'integer|nullable'
        ]);
        
        $recommendation = SystemRecommendation::findOrFail($recommendationId);
        
        $recommendation->update([
            'status' => 'executed',
            'executed_at' => now(),
            'action_data' => array_merge($recommendation->action_data, [
                'execution_notes' => $request->notes,
                'action_taken' => $request->action_taken,
                'quantity_processed' => $request->quantity_processed
            ])
        ]);
        
        // Update IM daily log
        $user = $request->user();
        $dailyLog = ImDailyLog::firstOrCreate(['user_id' => $user->id, 'log_date' => today()]);
        $dailyLog->increment('recommendations_executed');
        
        // Award bonus for critical recommendations
        if ($recommendation->priority === 'critical') {
            $dailyLog->increment('bonus_amount', 500);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Recommendation executed successfully',
            'bonus_awarded' => $recommendation->priority === 'critical' ? 500 : 0
        ]);
    }
    
    // GET /api/inventory/recommendations/pending
    public function getPendingRecommendations(Request $request)
    {
        $user = $request->user();
        
        $recommendations = SystemRecommendation::with('deliveryAgent')
            ->where('assigned_to', $user->id)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($rec) {
                return [
                    'id' => $rec->id,
                    'da_code' => $rec->deliveryAgent->da_code,
                    'type' => $rec->type,
                    'priority' => $rec->priority,
                    'message' => $rec->message,
                    'action_data' => $rec->action_data,
                    'created_at' => $rec->created_at->diffForHumans(),
                    'urgency_score' => $this->calculateUrgencyScore($rec)
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $recommendations,
            'penalty_risk' => $recommendations->count() * 1000 // ₦1000 per pending recommendation
        ]);
    }
    
    private function detectStockoutRisks()
    {
        $risks = [];
        $agents = DeliveryAgent::with('user')->where('status', 'active')->get();
        
        foreach ($agents as $agent) {
            $bin = Bin::where('delivery_agent_id', $agent->id)->first();
            if (!$bin) continue;
            
            $currentStock = $bin->current_stock_count ?? 0;
            $dailyConsumption = 3; // Assume 3 units per day average
            
            if ($currentStock > 0) {
                $daysRemaining = $currentStock / $dailyConsumption;
                
                if ($daysRemaining <= 2) {
                    $risks[] = [
                        'da_id' => $agent->id,
                        'da_code' => $agent->da_code,
                        'current_stock' => $currentStock,
                        'days_remaining' => round($daysRemaining, 1),
                        'recommended_quantity' => max(20, $dailyConsumption * 7) // Week supply
                    ];
                }
            }
        }
        
        return $risks;
    }
    
    private function detectLowStock()
    {
        $lowStock = [];
        $agents = DeliveryAgent::where('status', 'active')->get();
        
        foreach ($agents as $agent) {
            $bin = Bin::where('delivery_agent_id', $agent->id)->first();
            if (!$bin) continue;
            
            $currentStock = $bin->current_stock_count ?? 0;
            
            if ($currentStock > 0 && $currentStock <= 15) {
                $lowStock[] = [
                    'da_id' => $agent->id,
                    'da_code' => $agent->da_code,
                    'current_stock' => $currentStock,
                    'recommended_quantity' => 25
                ];
            }
        }
        
        return $lowStock;
    }
    
    private function detectOptimizationOpportunities()
    {
        return []; // Placeholder for advanced optimization logic
    }
    
    private function createRecommendation($daId, $type, $priority, $message, $actionData, $assignedTo)
    {
        return SystemRecommendation::create([
            'delivery_agent_id' => $daId,
            'type' => $type,
            'priority' => $priority,
            'message' => $message,
            'action_data' => $actionData,
            'status' => 'pending',
            'assigned_to' => $assignedTo
        ]);
    }
    
    private function calculateUrgencyScore($recommendation)
    {
        $priorityScores = ['critical' => 10, 'high' => 7, 'medium' => 4, 'low' => 1];
        $ageHours = $recommendation->created_at->diffInHours(now());
        
        return $priorityScores[$recommendation->priority] + min($ageHours, 24);
    }
}
