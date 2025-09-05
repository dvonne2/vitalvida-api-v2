<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\KanbanMovement;
use App\Services\KanbanMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KanbanController extends Controller
{
    protected $kanbanService;
    
    public function __construct(KanbanMovementService $kanbanService)
    {
        $this->kanbanService = $kanbanService;
    }
    
    public function processAllOrders(): JsonResponse
    {
        // Process all orders that can move
        $orders = Order::where('can_auto_progress', true)
            ->whereNotIn('status', ['completed', 'abandoned'])
            ->get();
        
        $processed = 0;
        $blocked = 0;
        
        foreach ($orders as $order) {
            $result = $this->kanbanService->processOrder($order);
            if ($result) {
                $processed++;
            } else {
                $blocked++;
            }
        }
        
        return response()->json([
            'processed' => $processed,
            'blocked' => $blocked,
            'total' => $orders->count()
        ]);
    }
    
    public function getMovementHistory(Order $order): JsonResponse
    {
        $movements = $order->kanbanMovements()
            ->orderBy('moved_at', 'desc')
            ->get();
        
        return response()->json($movements);
    }
    
    public function getBlockedOrders(): JsonResponse
    {
        $blocked = Order::where('can_auto_progress', false)
            ->orWhere('status', 'blocked_for_review')
            ->with(['customer', 'accountManager'])
            ->get()
            ->map(function($order) {
                return array_merge($order->toArray(), [
                    'ai_status' => $order->canMoveToNextStage(),
                    'restriction_type' => $order->ai_restrictions['type'] ?? 'unknown',
                    'days_blocked' => $order->updated_at->diffInDays(now())
                ]);
            });
        
        return response()->json($blocked);
    }
    
    public function getMovementStats(): JsonResponse
    {
        $stats = [
            'total_movements_today' => KanbanMovement::whereDate('moved_at', today())->count(),
            'ai_auto_movements' => KanbanMovement::where('movement_type', 'ai_auto')
                ->whereDate('moved_at', today())->count(),
            'manual_overrides' => KanbanMovement::where('movement_type', 'manual_override')
                ->whereDate('moved_at', today())->count(),
            'blocked_orders' => Order::where('can_auto_progress', false)->count(),
            'avg_processing_time' => $this->getAverageProcessingTime(),
            'bottleneck_status' => $this->getBottleneckStatus()
        ];
        
        return response()->json($stats);
    }
    
    private function getAverageProcessingTime()
    {
        return Order::where('status', 'completed')
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours')
            ->value('avg_hours');
    }
    
    private function getBottleneckStatus()
    {
        $statusCounts = Order::whereNotIn('status', ['completed', 'abandoned'])
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'status');
        
        return $statusCounts->first() > 10 ? $statusCounts->keys()->first() : null;
    }
}
