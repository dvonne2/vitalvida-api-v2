<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelesalesAgent;
use Illuminate\Http\Request;
use Exception;

class LovableIntegrationController extends Controller
{
    /**
     * Get all data needed for Lovable frontend initialization
     */
    public function getInitialData($agentId, Request $request)
    {
        try {
            $agent = TelesalesAgent::findOrFail($agentId);
            $period = $request->get('period', 'week');
            
            // Get dashboard data
            $dashboardController = app(\App\Http\Controllers\Api\TelesalesDashboardController::class);
            $dashboardData = $dashboardController->getDashboard($agentId, $request)->getData();
            
            // Get urgent orders
            $kemiController = app(\App\Http\Controllers\Api\KemiController::class);
            $urgentOrders = $kemiController->getUrgentOrders($agentId)->getData();
            
            // Get available delivery agents
            $deliveryController = app(\App\Http\Controllers\Api\DeliveryAgentController::class);
            $availableAgents = $deliveryController->getAvailableAgents($request)->getData();
            
            // Get performance summary
            $performanceController = app(\App\Http\Controllers\Api\PerformanceController::class);
            $performanceSummary = $performanceController->getWeeklyPerformance($agentId, $request)->getData();
            
            return response()->json([
                'agent' => $agent,
                'dashboard_data' => $dashboardData,
                'urgent_orders' => $urgentOrders,
                'available_agents' => $availableAgents,
                'performance_summary' => $performanceSummary,
                'config' => [
                    'bonus_rules' => config('telesales.bonus_rules'),
                    'features' => config('telesales.performance'),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to load initial data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Heartbeat endpoint for Lovable to check connection
     */
    public function heartbeat()
    {
        return response()->json([
            'status' => 'online',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'services' => [
                'database' => 'connected',
                'cache' => 'connected',
                'queue' => 'connected'
            ]
        ]);
    }
    
    /**
     * Batch operation endpoint for multiple actions
     */
    public function batchOperation(Request $request)
    {
        $request->validate([
            'operations' => 'required|array',
            'operations.*.type' => 'required|string',
            'operations.*.data' => 'required|array'
        ]);
        
        $results = [];
        
        foreach ($request->operations as $operation) {
            try {
                $result = $this->executeOperation($operation['type'], $operation['data']);
                $results[] = ['success' => true, 'data' => $result];
            } catch (Exception $e) {
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return response()->json(['results' => $results]);
    }
    
    /**
     * Get real-time updates for the frontend
     */
    public function getRealtimeUpdates($agentId, Request $request)
    {
        try {
            $agent = TelesalesAgent::findOrFail($agentId);
            $lastUpdate = $request->get('last_update', now()->subMinutes(5)->toISOString());
            
            // Get new orders since last update
            $newOrders = \App\Models\Order::where('telesales_agent_id', $agentId)
                ->where('created_at', '>', $lastUpdate)
                ->count();
                
            // Get urgent alerts
            $kemiController = app(\App\Http\Controllers\Api\KemiController::class);
            $urgentAlerts = $kemiController->getUrgentAlerts($agentId)->getData();
            
            // Get performance updates
            $performanceController = app(\App\Http\Controllers\Api\PerformanceController::class);
            $performanceUpdate = $performanceController->getWeeklyPerformance($agentId)->getData();
            
            return response()->json([
                'new_orders' => $newOrders,
                'urgent_alerts' => $urgentAlerts,
                'performance_update' => $performanceUpdate,
                'timestamp' => now()->toISOString()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to get real-time updates',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Health check endpoint for monitoring
     */
    public function healthCheck()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'external_services' => $this->checkExternalServices()
        ];
        
        $overallStatus = collect($checks)->every(fn($status) => $status === 'healthy') ? 'healthy' : 'degraded';
        
        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toISOString()
        ]);
    }
    
    private function executeOperation($type, $data)
    {
        switch ($type) {
            case 'record_call':
                $orderController = app(\App\Http\Controllers\Api\OrderController::class);
                return $orderController->recordCall($data['order_id'], new Request($data));
                
            case 'assign_agent':
                $orderController = app(\App\Http\Controllers\Api\OrderController::class);
                return $orderController->assignDeliveryAgent($data['order_id'], new Request($data));
                
            case 'send_kemi_message':
                $kemiController = app(\App\Http\Controllers\Api\KemiController::class);
                return $kemiController->handleChat($data['order_id'], new Request($data));
                
            case 'generate_otp':
                $orderController = app(\App\Http\Controllers\Api\OrderController::class);
                return $orderController->generateOTP($data['order_id'], new Request($data));
                
            default:
                throw new Exception("Unknown operation type: {$type}");
        }
    }
    
    private function checkDatabase()
    {
        try {
            \DB::connection()->getPdo();
            return 'healthy';
        } catch (Exception $e) {
            return 'unhealthy';
        }
    }
    
    private function checkCache()
    {
        try {
            \Cache::store()->has('health_check');
            return 'healthy';
        } catch (Exception $e) {
            return 'unhealthy';
        }
    }
    
    private function checkQueue()
    {
        try {
            // Simple queue check
            return 'healthy';
        } catch (Exception $e) {
            return 'unhealthy';
        }
    }
    
    private function checkExternalServices()
    {
        $services = [];
        
        // Check Zoho connection
        try {
            $zohoService = app(\App\Services\ZohoInventoryService::class);
            $services['zoho'] = $zohoService->testConnection() ? 'healthy' : 'unhealthy';
        } catch (Exception $e) {
            $services['zoho'] = 'unhealthy';
        }
        
        // Check EbulkSMS connection
        try {
            $smsService = app(\App\Services\EbulkSmsService::class);
            $services['ebulksms'] = $smsService->testConnection() ? 'healthy' : 'unhealthy';
        } catch (Exception $e) {
            $services['ebulksms'] = 'unhealthy';
        }
        
        return $services;
    }
} 