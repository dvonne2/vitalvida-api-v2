<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\AICreative;
use App\Models\Order;
use App\Models\AIInteraction;
use App\Services\AIContentGenerator;
use App\Services\OmnichannelRetargeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AICommandRoomController extends Controller
{
    public function dashboard()
    {
        $metrics = $this->getRealTimeMetrics();
        $topCreatives = $this->getTopPerformingCreatives();
        $aiActions = $this->getRecentAIActions();
        $predictions = $this->getAIPredictions();

        return view('ai-command-room.dashboard', compact(
            'metrics', 'topCreatives', 'aiActions', 'predictions'
        ));
    }

    public function getRealTimeMetrics(): array
    {
        return Cache::remember('ai_command_metrics', 30, function () {
            return [
                'orders_today' => Order::whereDate('created_at', today())->count(),
                'orders_target' => 5000,
                'revenue_today' => Order::whereDate('created_at', today())->sum('total_amount'),
                'average_cpo' => $this->calculateAverageCPO(),
                'customer_ltv' => Customer::avg('lifetime_value_prediction') ?? 0,
                'repeat_rate' => $this->calculateRepeatRate(),
                'ai_creatives_live' => AICreative::where('status', 'active')->count(),
                'churn_risk_customers' => Customer::where('churn_probability', '>', 0.7)->count(),
                'winning_creatives' => AICreative::winning()->count(),
                'losing_creatives' => AICreative::losing()->count(),
                'total_customers' => Customer::count(),
                'active_campaigns' => \App\Models\Campaign::active()->count()
            ];
        });
    }

    public function getTopPerformingCreatives(): array
    {
        return AICreative::where('status', 'active')
            ->where('orders_generated', '>', 0)
            ->orderBy('cpo', 'asc')
            ->orderBy('orders_generated', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($creative) {
                return [
                    'id' => $creative->id,
                    'thumbnail' => $creative->thumbnail_url ?? '/images/default-creative.jpg',
                    'platform' => $creative->platform,
                    'cpo' => $creative->cpo,
                    'orders' => $creative->orders_generated,
                    'revenue' => $creative->revenue,
                    'ctr' => $creative->ctr,
                    'performance_score' => $creative->performance_score,
                    'grade' => $creative->getPerformanceGrade()
                ];
            })
            ->toArray();
    }

    public function getRecentAIActions(): array
    {
        return AIInteraction::with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($interaction) {
                return [
                    'timestamp' => $interaction->created_at,
                    'action' => $this->formatAIAction($interaction),
                    'result' => $this->formatAIResult($interaction),
                    'type' => $interaction->interaction_type,
                    'customer_name' => $interaction->customer->name ?? 'Unknown',
                    'confidence' => $interaction->confidence_score
                ];
            })
            ->toArray();
    }

    public function getAIPredictions(): array
    {
        return [
            'next_week_orders' => $this->predictNextWeekOrders(),
            'churn_risk_trend' => $this->getChurnRiskTrend(),
            'revenue_forecast' => $this->getRevenueForecast(),
            'optimal_budget_allocation' => $this->getOptimalBudgetAllocation()
        ];
    }

    public function triggerAIAction(Request $request)
    {
        $action = $request->input('action');
        $parameters = $request->input('parameters', []);

        try {
            switch ($action) {
                case 'generate_creatives':
                    return $this->generateNewCreatives($parameters);
                case 'scale_winners':
                    return $this->scaleWinningCampaigns($parameters);
                case 'kill_losers':
                    return $this->killUnderperformingCampaigns($parameters);
                case 'trigger_reorder_blast':
                    return $this->triggerReorderBlast($parameters);
                case 'optimize_budgets':
                    return $this->optimizeBudgets($parameters);
                case 'launch_churn_prevention':
                    return $this->launchChurnPrevention($parameters);
                default:
                    return response()->json(['error' => 'Unknown action'], 400);
            }
        } catch (\Exception $e) {
            Log::error('AI Action failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Action failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateNewCreatives(array $parameters): array
    {
        $aiGenerator = app(AIContentGenerator::class);
        
        $creativeParams = [
            'audience' => $parameters['audience'] ?? 'Nigerian women 25-45',
            'platform' => $parameters['platform'] ?? 'meta',
            'pain_point' => $parameters['pain_point'] ?? 'thin edges',
            'goal' => 'conversion',
            'tone' => 'empathetic'
        ];

        $generatedContent = $aiGenerator->generateAdCopy($creativeParams);
        
        $creativesCreated = 0;
        foreach ($generatedContent['variations'] as $variation) {
            AICreative::create([
                'type' => 'text_ad',
                'platform' => $creativeParams['platform'],
                'prompt_used' => json_encode($creativeParams),
                'copy_text' => $variation,
                'status' => 'pending_review',
                'ai_confidence_score' => rand(70, 95) / 100,
                'target_audience' => ['audience' => $creativeParams['audience']]
            ]);
            $creativesCreated++;
        }

        // Log AI interaction
        AIInteraction::create([
            'customer_id' => null,
            'interaction_type' => 'creative_generation',
            'platform' => $creativeParams['platform'],
            'content_generated' => $generatedContent,
            'ai_model_used' => 'claude-3-sonnet',
            'confidence_score' => 0.85,
            'performance_metrics' => ['creatives_created' => $creativesCreated]
        ]);

        return [
            'success' => true,
            'message' => "Generated {$creativesCreated} new creatives",
            'creatives' => $generatedContent['variations']
        ];
    }

    private function scaleWinningCampaigns(array $parameters): array
    {
        $winningCreatives = AICreative::winning()->get();
        $scaledCount = 0;

        foreach ($winningCreatives as $creative) {
            // Scale budget by 5x
            $creative->update([
                'spend' => $creative->spend * 5,
                'status' => 'active'
            ]);
            $scaledCount++;
        }

        return [
            'success' => true,
            'message' => "Scaled {$scaledCount} winning campaigns by 5x",
            'scaled_count' => $scaledCount
        ];
    }

    private function killUnderperformingCampaigns(array $parameters): array
    {
        $losingCreatives = AICreative::losing()->get();
        $killedCount = 0;

        foreach ($losingCreatives as $creative) {
            $creative->update(['status' => 'paused']);
            $killedCount++;
        }

        return [
            'success' => true,
            'message' => "Killed {$killedCount} underperforming campaigns",
            'killed_count' => $killedCount
        ];
    }

    private function triggerReorderBlast(array $parameters): array
    {
        $customers = Customer::readyForReorder()->limit(10000)->get();
        $retargetingService = app(OmnichannelRetargeting::class);
        $triggeredCount = 0;

        foreach ($customers as $customer) {
            $retargetingService->triggerReorderSequence($customer);
            $triggeredCount++;
        }

        return [
            'success' => true,
            'message' => "Triggered reorder sequence for {$triggeredCount} customers",
            'triggered_count' => $triggeredCount
        ];
    }

    private function optimizeBudgets(array $parameters): array
    {
        $campaigns = \App\Models\Campaign::active()->get();
        $optimizedCount = 0;

        foreach ($campaigns as $campaign) {
            if ($campaign->shouldScale()) {
                $campaign->update(['budget' => $campaign->budget * 1.5]);
                $optimizedCount++;
            } elseif ($campaign->shouldPause()) {
                $campaign->update(['status' => 'paused']);
                $optimizedCount++;
            }
        }

        return [
            'success' => true,
            'message' => "Optimized {$optimizedCount} campaign budgets",
            'optimized_count' => $optimizedCount
        ];
    }

    private function launchChurnPrevention(array $parameters): array
    {
        $highRiskCustomers = Customer::highChurnRisk()->get();
        $retargetingService = app(OmnichannelRetargeting::class);
        $preventedCount = 0;

        foreach ($highRiskCustomers as $customer) {
            $retargetingService->triggerChurnPreventionSequence($customer);
            $preventedCount++;
        }

        return [
            'success' => true,
            'message' => "Launched churn prevention for {$preventedCount} customers",
            'prevented_count' => $preventedCount
        ];
    }

    private function calculateAverageCPO(): float
    {
        $activeCreatives = AICreative::where('status', 'active')
            ->where('cpo', '>', 0)
            ->get();

        if ($activeCreatives->isEmpty()) {
            return 1500.0; // Default CPO
        }

        return $activeCreatives->avg('cpo');
    }

    private function calculateRepeatRate(): float
    {
        $totalCustomers = Customer::count();
        $repeatCustomers = Customer::where('orders_count', '>', 1)->count();

        if ($totalCustomers === 0) {
            return 0.0;
        }

        return ($repeatCustomers / $totalCustomers) * 100;
    }

    private function formatAIAction($interaction): string
    {
        return match($interaction->interaction_type) {
            'creative_generation' => "Generated new creatives for {$interaction->platform}",
            'retargeting_message' => "Sent retargeting message via {$interaction->platform}",
            'churn_prevention' => "Launched churn prevention campaign",
            'reorder_reminder' => "Triggered reorder reminder sequence",
            'personalized_offer' => "Generated personalized offer",
            'abandoned_cart' => "Sent abandoned cart recovery",
            'viral_amplification' => "Launched viral amplification campaign",
            default => "AI action: {$interaction->interaction_type}"
        };
    }

    private function formatAIResult($interaction): string
    {
        if ($interaction->conversion_achieved) {
            return "✅ Conversion achieved - ₦" . number_format($interaction->revenue_generated);
        }

        if ($interaction->response_received) {
            return "📱 Response received";
        }

        return "⏳ Processing...";
    }

    private function predictNextWeekOrders(): int
    {
        // Simple prediction based on current trend
        $currentOrders = Order::whereDate('created_at', today())->count();
        $growthRate = 1.15; // 15% daily growth
        return (int) ($currentOrders * $growthRate * 7);
    }

    private function getChurnRiskTrend(): array
    {
        $highRiskCount = Customer::highChurnRisk()->count();
        $totalCustomers = Customer::count();
        
        return [
            'high_risk_percentage' => $totalCustomers > 0 ? ($highRiskCount / $totalCustomers) * 100 : 0,
            'trend' => 'increasing', // This would be calculated based on historical data
            'recommendation' => 'Launch churn prevention campaigns'
        ];
    }

    private function getRevenueForecast(): array
    {
        $todayRevenue = Order::whereDate('created_at', today())->sum('total_amount');
        
        return [
            'today' => $todayRevenue,
            'week_forecast' => $todayRevenue * 7 * 1.1, // 10% growth
            'month_forecast' => $todayRevenue * 30 * 1.2, // 20% growth
            'growth_rate' => 15.5
        ];
    }

    private function getOptimalBudgetAllocation(): array
    {
        return [
            'meta' => 40, // 40% of budget
            'tiktok' => 25,
            'google' => 20,
            'whatsapp' => 10,
            'sms' => 3,
            'email' => 2
        ];
    }
} 