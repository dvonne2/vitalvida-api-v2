<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingContentLibrary;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingWhatsAppLog;
use App\Models\Sale;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MarketingDashboardController extends Controller
{
    public function getStats()
    {
        $companyId = auth()->user()->company_id;
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        // Content Library Stats
        $totalAssets = MarketingContentLibrary::where('company_id', $companyId)->count();
        $aiGenerated = MarketingContentLibrary::where('company_id', $companyId)
            ->where('content_type', 'ai_generated')->count();
        
        // WhatsApp Stats
        $whatsappSent = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->where('channel', 'whatsapp')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
            
        $whatsappDelivered = MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->where('channel', 'whatsapp')
            ->where('interaction_type', 'delivered')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        
        // Financial Stats
        $totalSpend = MarketingCampaign::where('company_id', $companyId)
            ->where('status', 'active')
            ->sum('actual_spend');
            
        $totalRevenue = $this->calculateTotalRevenue($companyId);
        $avgRoas = $this->calculateROAS($companyId);
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_assets' => $totalAssets,
                'ai_generated' => $aiGenerated,
                'whatsapp_sent' => $whatsappSent,
                'whatsapp_delivered' => $whatsappDelivered,
                'total_spend' => $totalSpend,
                'total_revenue' => $totalRevenue,
                'avg_roas' => $avgRoas,
                'delivery_rate' => $whatsappSent > 0 ? ($whatsappDelivered / $whatsappSent) * 100 : 0
            ]
        ]);
    }
    
    private function calculateTotalRevenue($companyId)
    {
        return Sale::where('company_id', $companyId)
            ->whereHas('marketingTouchpoints')
            ->sum('total_amount');
    }
    
    private function calculateROAS($companyId)
    {
        $totalSpend = MarketingCampaign::where('company_id', $companyId)
            ->where('status', 'active')
            ->sum('actual_spend');
            
        $totalRevenue = $this->calculateTotalRevenue($companyId);
        
        if ($totalSpend > 0) {
            return $totalRevenue / $totalSpend;
        }
        
        return 0;
    }
}
