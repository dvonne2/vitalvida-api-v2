<?php

namespace App\Services;

use App\Models\Revenue;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsService
{
    /**
     * Calculate net margin
     */
    public function calculateNetMargin(): array
    {
        $cacheKey = 'revenue_net_margin';
        
        return Cache::remember($cacheKey, 3600, function () {
            $thisMonth = Carbon::now()->startOfMonth();
            
            $revenueMTD = Revenue::getMonthlyRevenue($thisMonth->year, $thisMonth->month);
            
            // Calculate costs
            $cogs = $revenueMTD * 0.428; // 42.8% of revenue
            $adSpend = $revenueMTD * 0.20; // 20% of revenue
            $operationalCosts = $revenueMTD * 0.16; // 16% of revenue
            
            $netProfit = $revenueMTD - $cogs - $adSpend - $operationalCosts;
            $netMargin = $revenueMTD > 0 ? ($netProfit / $revenueMTD) * 100 : 0;
            
            return [
                'revenue_mtd' => $revenueMTD,
                'cogs' => $cogs,
                'ad_spend' => $adSpend,
                'operational_costs' => $operationalCosts,
                'net_profit' => $netProfit,
                'net_margin_percentage' => $netMargin,
                'margin_trend' => $netMargin >= 18 ? 'up' : 'down'
            ];
        });
    }

    /**
     * Get revenue projections
     */
    public function getRevenueProjections(): array
    {
        $cacheKey = 'revenue_projections';
        
        return Cache::remember($cacheKey, 3600, function () {
            $currentMonth = Carbon::now();
            $projections = [];
            
            for ($i = 0; $i < 6; $i++) {
                $month = $currentMonth->copy()->addMonths($i);
                $monthKey = $month->format('Y-m');
                
                // Calculate projection based on historical data and growth trends
                $baseRevenue = Revenue::getMonthlyRevenue($month->year, $month->month);
                $growthRate = 1 + (0.15 + ($i * 0.02)); // 15% base + 2% per month
                $projectedRevenue = $baseRevenue * $growthRate;
                
                $projections[$monthKey] = [
                    'month' => $month->format('M Y'),
                    'projected_revenue' => $projectedRevenue,
                    'growth_rate' => ($growthRate - 1) * 100,
                    'confidence_level' => max(60, 100 - ($i * 10)) // Decreasing confidence for distant months
                ];
            }
            
            return $projections;
        });
    }

    /**
     * Analyze profit trends
     */
    public function analyzeProfitTrends(): array
    {
        $cacheKey = 'profit_trends';
        
        return Cache::remember($cacheKey, 3600, function () {
            $months = [];
            $profits = [];
            
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $revenue = Revenue::getMonthlyRevenue($month->year, $month->month);
                
                // Calculate profit for each month
                $cogs = $revenue * 0.428;
                $adSpend = $revenue * 0.20;
                $operationalCosts = $revenue * 0.16;
                $profit = $revenue - $cogs - $adSpend - $operationalCosts;
                
                $months[] = $month->format('M');
                $profits[] = $profit;
            }
            
            // Calculate trend
            $trend = 'stable';
            if (count($profits) >= 2) {
                $recentAvg = array_sum(array_slice($profits, -3)) / 3;
                $olderAvg = array_sum(array_slice($profits, 0, 3)) / 3;
                
                if ($recentAvg > $olderAvg * 1.1) {
                    $trend = 'increasing';
                } elseif ($recentAvg < $olderAvg * 0.9) {
                    $trend = 'decreasing';
                }
            }
            
            return [
                'months' => $months,
                'profits' => $profits,
                'trend' => $trend,
                'average_profit' => array_sum($profits) / count($profits),
                'profit_margin_trend' => $trend
            ];
        });
    }

    /**
     * Compute ROAS (Return on Ad Spend)
     */
    public function computeROAS(): array
    {
        $cacheKey = 'roas_calculations';
        
        return Cache::remember($cacheKey, 1800, function () {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            
            // Get revenue and ad spend data
            $revenueToday = Revenue::whereDate('date', $today)->sum('total_revenue');
            $revenueMTD = Revenue::getMonthlyRevenue($thisMonth->year, $thisMonth->month);
            
            // Simulate ad spend data
            $adSpendToday = 132000; // Daily ad spend
            $adSpendMTD = $revenueMTD * 0.20; // 20% of revenue
            
            // Calculate ROAS
            $roasToday = $adSpendToday > 0 ? $revenueToday / $adSpendToday : 0;
            $roasMTD = $adSpendMTD > 0 ? $revenueMTD / $adSpendMTD : 0;
            
            return [
                'today' => [
                    'roas' => $roasToday,
                    'revenue' => $revenueToday,
                    'ad_spend' => $adSpendToday,
                    'status' => $roasToday >= 3.5 ? 'good' : ($roasToday >= 2.5 ? 'monitor' : 'fix')
                ],
                'mtd' => [
                    'roas' => $roasMTD,
                    'revenue' => $revenueMTD,
                    'ad_spend' => $adSpendMTD,
                    'status' => $roasMTD >= 3.5 ? 'good' : ($roasMTD >= 2.5 ? 'monitor' : 'fix')
                ],
                'target_roas' => 3.5,
                'platform_breakdown' => [
                    'facebook' => ['roas' => 3.2, 'spend' => 45000],
                    'tiktok' => ['roas' => 2.8, 'spend' => 35000],
                    'google' => ['roas' => 4.1, 'spend' => 52000]
                ]
            ];
        });
    }

    /**
     * Get revenue by department
     */
    public function getRevenueByDepartment(): array
    {
        $cacheKey = 'revenue_by_department';
        
        return Cache::remember($cacheKey, 3600, function () {
            $thisMonth = Carbon::now()->startOfMonth();
            
            $departments = DB::table('departments')->get();
            $revenueData = [];
            
            foreach ($departments as $department) {
                $departmentRevenue = Revenue::where('department_id', $department->id)
                    ->whereMonth('date', $thisMonth->month)
                    ->whereYear('date', $thisMonth->year)
                    ->sum('total_revenue');
                
                $revenueData[] = [
                    'department' => $department->name,
                    'revenue' => $departmentRevenue,
                    'percentage' => 0, // Will be calculated after total is known
                    'growth' => rand(-15, 25), // Simulated growth
                    'status' => $departmentRevenue > 0 ? 'active' : 'inactive'
                ];
            }
            
            // Calculate percentages
            $totalRevenue = array_sum(array_column($revenueData, 'revenue'));
            foreach ($revenueData as &$data) {
                $data['percentage'] = $totalRevenue > 0 ? ($data['revenue'] / $totalRevenue) * 100 : 0;
            }
            
            return $revenueData;
        });
    }

    /**
     * Get comprehensive revenue analytics
     */
    public function getRevenueAnalytics(): array
    {
        return [
            'net_margin' => $this->calculateNetMargin(),
            'projections' => $this->getRevenueProjections(),
            'profit_trends' => $this->analyzeProfitTrends(),
            'roas' => $this->computeROAS(),
            'by_department' => $this->getRevenueByDepartment(),
            'last_updated' => now()->toISOString()
        ];
    }
} 