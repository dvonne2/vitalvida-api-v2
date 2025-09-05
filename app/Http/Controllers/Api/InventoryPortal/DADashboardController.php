<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DADashboardController extends Controller
{
    /**
     * Get DA dashboard overview
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            // Calculate performance score
            $totalDeliveries = Delivery::where('delivery_agent_id', $agent->id)->count();
            $successfulDeliveries = Delivery::where('delivery_agent_id', $agent->id)
                ->where('status', 'completed')
                ->count();
            $performanceScore = $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100) : 0;

            // Get agent rank
            $rank = $this->getAgentRank($agent->id);

            // Get this week's metrics
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            $weeklyMetrics = [
                'orders_delivered' => Delivery::where('delivery_agent_id', $agent->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->count(),
                'delivery_rate' => $this->calculateDeliveryRate($agent->id, $weekStart, $weekEnd),
                'fasttrack_rate' => $this->calculateFastTrackRate($agent->id, $weekStart, $weekEnd),
                'rating' => Delivery::where('delivery_agent_id', $agent->id)
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->avg('rating') ?? 4.8
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'performance_score' => $performanceScore,
                    'rank' => $rank,
                    'total_agents' => DeliveryAgent::count(),
                    'metrics' => $weeklyMetrics,
                    'agent_info' => [
                        'name' => $agent->name,
                        'location' => $agent->location ?? 'Lagos Central Zone',
                        'partnership_level' => $this->getPartnershipLevel($performanceScore)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DA support information
     */
    public function support(Request $request)
    {
        try {
            $contacts = [
                'logistics_coordinator' => '+234 801 234 5678',
                'support_hotline' => '+234 802 345 6789',
                'whatsapp_support' => '+234 803 456 7890',
                'emergency_hotline' => '+234 804 567 8901'
            ];

            $performanceTips = [
                'Always call customers before arrival to ensure smooth delivery.',
                'Take clear photos of delivered items for proof.',
                'Keep your vehicle well-maintained for efficient deliveries.',
                'Plan your route to minimize delivery time.',
                'Be polite and professional with every customer interaction.'
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contacts' => $contacts,
                    'performance_tips' => $performanceTips,
                    'current_tip' => $performanceTips[array_rand($performanceTips)]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch support information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent rank among all agents
     */
    private function getAgentRank($agentId)
    {
        $rankings = DeliveryAgent::select([
            'delivery_agents.id',
            DB::raw('COUNT(deliveries.id) as total_deliveries'),
            DB::raw('COUNT(CASE WHEN deliveries.status = "completed" THEN 1 END) as successful_deliveries')
        ])
        ->leftJoin('deliveries', 'delivery_agents.id', '=', 'deliveries.delivery_agent_id')
        ->groupBy('delivery_agents.id')
        ->orderBy('successful_deliveries', 'desc')
        ->orderBy('total_deliveries', 'desc')
        ->get();

        $rank = $rankings->search(function ($agent) use ($agentId) {
            return $agent->id == $agentId;
        });

        return $rank !== false ? $rank + 1 : DeliveryAgent::count();
    }

    /**
     * Calculate delivery rate for the week
     */
    private function calculateDeliveryRate($agentId, $startDate, $endDate)
    {
        $totalOrders = Order::where('assigned_da_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $deliveredOrders = Order::where('assigned_da_id', $agentId)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100) : 0;
    }

    /**
     * Calculate FastTrack rate (deliveries under 10 hours)
     */
    private function calculateFastTrackRate($agentId, $startDate, $endDate)
    {
        $totalDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $fastTrackDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereRaw('(julianday(updated_at) - julianday(created_at)) * 24 <= 10')
            ->count();

        return $totalDeliveries > 0 ? round(($fastTrackDeliveries / $totalDeliveries) * 100) : 0;
    }

    /**
     * Get partnership level based on performance score
     */
    private function getPartnershipLevel($score)
    {
        if ($score >= 90) return 'Lead Partner';
        if ($score >= 80) return 'Senior Partner';
        if ($score >= 70) return 'Partner';
        if ($score >= 60) return 'Junior Partner';
        return 'Trainee';
    }
}
