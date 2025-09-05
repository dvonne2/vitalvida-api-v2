<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DAAchievementController extends Controller
{
    /**
     * Get DA achievements
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

            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            $achievements = [
                [
                    'name' => 'Speed Master',
                    'description' => '3+ deliveries under 10hrs',
                    'amount' => 1500,
                    'frequency' => 'weekly',
                    'status' => $this->checkSpeedMasterAchievement($agent->id, $weekStart, $weekEnd),
                    'progress' => $this->getSpeedMasterProgress($agent->id, $weekStart, $weekEnd)
                ],
                [
                    'name' => 'Customer Champion',
                    'description' => 'Zero complaints for 7 days',
                    'amount' => 2000,
                    'frequency' => 'weekly',
                    'status' => $this->checkCustomerChampionAchievement($agent->id),
                    'progress' => $this->getCustomerChampionProgress($agent->id)
                ],
                [
                    'name' => 'Friday Photo Compliance',
                    'description' => 'Friday Photo Compliance',
                    'amount' => 0,
                    'frequency' => 'weekly',
                    'status' => $this->checkFridayPhotoAchievement($agent->id),
                    'progress' => $this->getFridayPhotoProgress($agent->id)
                ],
                [
                    'name' => 'Delivery Fulfillment Rate',
                    'description' => '98%+ Delivery Rate',
                    'amount' => 1000,
                    'frequency' => 'weekly',
                    'status' => $this->checkDeliveryRateAchievement($agent->id, $weekStart, $weekEnd),
                    'progress' => $this->getDeliveryRateProgress($agent->id, $weekStart, $weekEnd)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $achievements
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch achievements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DA bonuses
     */
    public function bonuses(Request $request)
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

            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            $weeklyBonuses = [
                'speed_master' => $this->checkSpeedMasterAchievement($agent->id, $weekStart, $weekEnd) === 'earned' ? 1500 : 0,
                'customer_champion' => $this->checkCustomerChampionAchievement($agent->id) === 'earned' ? 2000 : 0,
                'delivery_rate' => $this->checkDeliveryRateAchievement($agent->id, $weekStart, $weekEnd) === 'earned' ? 1000 : 0
            ];

            $quarterlyTracker = [
                'target' => 5000,
                'status' => 'not_qualified',
                'infractions' => $this->getInfractionsCount($agent->id),
                'kpis' => [
                    'order_fulfillment' => $this->getOrderFulfillmentRate($agent->id),
                    'sla_compliance' => $this->getSLAComplianceRate($agent->id),
                    'stock_photo' => $this->getStockPhotoCompliance($agent->id),
                    'inventory_accuracy' => $this->getInventoryAccuracy($agent->id)
                ]
            ];

            // Check if qualified for quarterly bonus
            if ($quarterlyTracker['infractions'] === 0 && 
                $quarterlyTracker['kpis']['order_fulfillment'] >= 90 &&
                $quarterlyTracker['kpis']['sla_compliance'] >= 95 &&
                $quarterlyTracker['kpis']['stock_photo'] >= 3 &&
                $quarterlyTracker['kpis']['inventory_accuracy'] >= 100) {
                $quarterlyTracker['status'] = 'qualified';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'weekly_bonuses' => $weeklyBonuses,
                    'quarterly_tracker' => $quarterlyTracker
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bonuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Speed Master achievement
     */
    private function checkSpeedMasterAchievement($agentId, $startDate, $endDate)
    {
        $fastTrackDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereRaw('(julianday(updated_at) - julianday(created_at)) * 24 <= 10')
            ->count();

        return $fastTrackDeliveries >= 3 ? 'earned' : 'pending';
    }

    /**
     * Get Speed Master progress
     */
    private function getSpeedMasterProgress($agentId, $startDate, $endDate)
    {
        $fastTrackDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereRaw('(julianday(updated_at) - julianday(created_at)) * 24 <= 10')
            ->count();

        return min($fastTrackDeliveries, 3);
    }

    /**
     * Check Customer Champion achievement
     */
    private function checkCustomerChampionAchievement($agentId)
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        $complaints = Delivery::where('delivery_agent_id', $agentId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('has_complaint', true)
            ->count();

        return $complaints === 0 ? 'earned' : 'pending';
    }

    /**
     * Get Customer Champion progress
     */
    private function getCustomerChampionProgress($agentId)
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        $complaints = Delivery::where('delivery_agent_id', $agentId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('has_complaint', true)
            ->count();

        return $complaints === 0 ? 100 : 0;
    }

    /**
     * Check Friday Photo achievement
     */
    private function checkFridayPhotoAchievement($agentId)
    {
        $lastFriday = Carbon::now()->previous(Carbon::FRIDAY);
        
        $photoUploaded = DB::table('stock_photos')
            ->where('delivery_agent_id', $agentId)
            ->whereDate('uploaded_at', $lastFriday)
            ->exists();

        return $photoUploaded ? 'earned' : 'pending';
    }

    /**
     * Get Friday Photo progress
     */
    private function getFridayPhotoProgress($agentId)
    {
        $lastFriday = Carbon::now()->previous(Carbon::FRIDAY);
        
        $photoUploaded = DB::table('stock_photos')
            ->where('delivery_agent_id', $agentId)
            ->whereDate('uploaded_at', $lastFriday)
            ->exists();

        return $photoUploaded ? 100 : 0;
    }

    /**
     * Check Delivery Rate achievement
     */
    private function checkDeliveryRateAchievement($agentId, $startDate, $endDate)
    {
        $rate = $this->getDeliveryRateProgress($agentId, $startDate, $endDate);
        return $rate >= 98 ? 'earned' : 'pending';
    }

    /**
     * Get Delivery Rate progress
     */
    private function getDeliveryRateProgress($agentId, $startDate, $endDate)
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
     * Get infractions count
     */
    private function getInfractionsCount($agentId)
    {
        $monthStart = Carbon::now()->startOfMonth();
        
        return DB::table('violations')
            ->where('delivery_agent_id', $agentId)
            ->where('created_at', '>=', $monthStart)
            ->count();
    }

    /**
     * Get order fulfillment rate
     */
    private function getOrderFulfillmentRate($agentId)
    {
        $monthStart = Carbon::now()->startOfMonth();
        
        $totalOrders = Order::where('assigned_da_id', $agentId)
            ->where('created_at', '>=', $monthStart)
            ->count();

        $fulfilledOrders = Order::where('assigned_da_id', $agentId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', $monthStart)
            ->count();

        return $totalOrders > 0 ? round(($fulfilledOrders / $totalOrders) * 100) : 0;
    }

    /**
     * Get SLA compliance rate
     */
    private function getSLAComplianceRate($agentId)
    {
        $monthStart = Carbon::now()->startOfMonth();
        
        $totalDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('created_at', '>=', $monthStart)
            ->count();

        $onTimeDeliveries = Delivery::where('delivery_agent_id', $agentId)
            ->where('created_at', '>=', $startDate)
            ->where('delivered_at', '<=', DB::raw('expected_delivery_at'))
            ->count();

        return $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100) : 0;
    }

    /**
     * Get stock photo compliance
     */
    private function getStockPhotoCompliance($agentId)
    {
        $monthStart = Carbon::now()->startOfMonth();
        
        return DB::table('stock_photos')
            ->where('delivery_agent_id', $agentId)
            ->where('uploaded_at', '>=', $monthStart)
            ->count();
    }

    /**
     * Get inventory accuracy
     */
    private function getInventoryAccuracy($agentId)
    {
        // This would typically check inventory reconciliation accuracy
        // For now, return 100% as placeholder
        return 100;
    }
}
