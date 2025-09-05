<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingReferral;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingReferralController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'referrer_customer_id' => 'required|uuid|exists:customers,id',
            'referred_customer_id' => 'required|uuid|exists:customers,id|different:referrer_customer_id',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'referral_code' => 'nullable|string|unique:marketing_referrals,referral_code',
            'commission_type' => 'required|in:percentage,fixed',
            'commission_value' => 'required|numeric|min:0',
            'status' => 'nullable|in:pending,completed,cancelled'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Verify both customers belong to the same company
        $referrer = Customer::where('id', $request->referrer_customer_id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        $referred = Customer::where('id', $request->referred_customer_id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$referrer || !$referred) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid customer IDs for this company'
            ], 400);
        }
        
        try {
            $referral = MarketingReferral::create([
                'referrer_customer_id' => $request->referrer_customer_id,
                'referred_customer_id' => $request->referred_customer_id,
                'brand_id' => $request->brand_id,
                'referral_code' => $request->referral_code ?? Str::upper(Str::random(8)),
                'commission_type' => $request->commission_type,
                'commission_value' => $request->commission_value,
                'status' => $request->status ?? 'pending',
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Referral created successfully',
                'data' => $referral->load(['referrer', 'referred', 'brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create referral: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getPerformance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30));
        $endDate = $request->get('end_date', Carbon::now());
        $brandId = $request->get('brand_id');
        
        $query = MarketingReferral::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate]);
            
        if ($brandId) {
            $query->where('brand_id', $brandId);
        }
        
        // Overall referral metrics
        $totalReferrals = $query->count();
        $completedReferrals = $query->where('status', 'completed')->count();
        $pendingReferrals = $query->where('status', 'pending')->count();
        $totalCommissionPaid = $query->where('status', 'completed')->sum('commission_paid');
        
        // Conversion rate
        $conversionRate = $totalReferrals > 0 ? ($completedReferrals / $totalReferrals) * 100 : 0;
        
        // Top referrers
        $topReferrers = MarketingReferral::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'referrer_customer_id',
                DB::raw('COUNT(*) as total_referrals'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_referrals'),
                DB::raw('SUM(commission_paid) as total_commission')
            )
            ->with(['referrer:id,name,email'])
            ->groupBy('referrer_customer_id')
            ->orderByDesc('completed_referrals')
            ->limit(10)
            ->get();
            
        // Daily referral trend
        $dailyTrend = MarketingReferral::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_referrals'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_referrals')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_referrals' => $totalReferrals,
                    'completed_referrals' => $completedReferrals,
                    'pending_referrals' => $pendingReferrals,
                    'conversion_rate' => $conversionRate,
                    'total_commission_paid' => $totalCommissionPaid
                ],
                'top_referrers' => $topReferrers,
                'daily_trend' => $dailyTrend,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]
        ]);
    }
    
    public function getLeaderboard(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $period = $request->get('period', 'month'); // week, month, quarter, year
        $brandId = $request->get('brand_id');
        
        // Calculate date range based on period
        $startDate = match($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth()
        };
        
        $query = MarketingReferral::where('company_id', $companyId)
            ->where('created_at', '>=', $startDate);
            
        if ($brandId) {
            $query->where('brand_id', $brandId);
        }
        
        $leaderboard = $query->select(
                'referrer_customer_id',
                DB::raw('COUNT(*) as total_referrals'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as successful_referrals'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN commission_paid ELSE 0 END) as total_earnings'),
                DB::raw('ROUND(AVG(CASE WHEN status = "completed" THEN commission_paid END), 2) as avg_commission')
            )
            ->with(['referrer:id,name,email,phone'])
            ->groupBy('referrer_customer_id')
            ->orderByDesc('successful_referrals')
            ->orderByDesc('total_earnings')
            ->limit(50)
            ->get()
            ->map(function ($item, $index) {
                $conversionRate = $item->total_referrals > 0 ? 
                    ($item->successful_referrals / $item->total_referrals) * 100 : 0;
                    
                return [
                    'rank' => $index + 1,
                    'customer' => $item->referrer,
                    'total_referrals' => $item->total_referrals,
                    'successful_referrals' => $item->successful_referrals,
                    'conversion_rate' => round($conversionRate, 2),
                    'total_earnings' => $item->total_earnings,
                    'avg_commission' => $item->avg_commission
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate,
                'leaderboard' => $leaderboard,
                'total_participants' => $leaderboard->count()
            ]
        ]);
    }
}
