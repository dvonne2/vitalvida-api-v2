<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelesalesAgent;
use App\Models\Order;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getSystemOverview()
    {
        $overview = [
            'total_telesales_agents' => TelesalesAgent::count(),
            'active_telesales_agents' => TelesalesAgent::where('status', 'active')->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('call_status', 'pending')->count(),
            'confirmed_orders' => Order::where('call_status', 'confirmed')->count(),
            'delivered_orders' => Order::where('delivery_status', 'delivered')->count(),
            'total_delivery_agents' => DeliveryAgent::count(),
            'active_delivery_agents' => DeliveryAgent::where('status', 'active')->count(),
            'system_health' => 'healthy',
            'last_updated' => now()->toISOString()
        ];
        
        return response()->json($overview);
    }
} 