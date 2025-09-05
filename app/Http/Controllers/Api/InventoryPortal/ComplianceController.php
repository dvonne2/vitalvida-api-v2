<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\StockPhoto;
use App\Models\StrikeLog;
use App\Models\AgentActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComplianceController extends Controller
{
    /**
     * Weekly inventory photo compliance
     * GET /api/inventory-portal/da/compliance/weekly
     */
    public function getWeeklyCompliance(): JsonResponse
    {
        $thisWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        // Get compliance summary
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        $onTimeSubmissions = StockPhoto::whereBetween('uploaded_at', [$thisWeek, $endOfWeek])
            ->where('uploaded_at', '<=', $endOfWeek->copy()->subDays(1)->setTime(12, 0))
            ->count();
        
        $fullyCompliant = StockPhoto::whereBetween('uploaded_at', [$thisWeek, $endOfWeek])
            ->where('photo_quality', 'clear')
            ->count();
        
        $flagged = StockPhoto::whereBetween('uploaded_at', [$thisWeek, $endOfWeek])
            ->where('photo_quality', 'unclear')
            ->count();
        
        $nonCompliant = $totalDAs - $onTimeSubmissions;
        $feesBlocked = $nonCompliant * 2; // 2 fees per non-compliant DA
        $deductions = $nonCompliant * 2000; // ₦2000 deduction per violation
        $strikes = $nonCompliant; // 1 strike per non-compliant DA

        // Get agent compliance details
        $agents = DeliveryAgent::where('status', 'active')
            ->with(['stockPhotos' => function($query) use ($thisWeek, $endOfWeek) {
                $query->whereBetween('uploaded_at', [$thisWeek, $endOfWeek]);
            }])
            ->get()
            ->map(function($agent) use ($endOfWeek) {
                $latestPhoto = $agent->stockPhotos->sortByDesc('uploaded_at')->first();
                
                $status = 'non_compliant';
                $photoQuality = 'none';
                $systemAction = 'none';
                $submissionTime = null;

                if ($latestPhoto) {
                    $submissionTime = $latestPhoto->uploaded_at->format('j M - g:i A');
                    
                    if ($latestPhoto->uploaded_at <= $endOfWeek->copy()->subDays(1)->setTime(12, 0)) {
                        $status = 'on_time';
                        $photoQuality = $latestPhoto->photo_quality ?? 'clear';
                        $systemAction = $photoQuality === 'clear' ? 'none' : 'strike_deduction';
                    }
                }

                return [
                    'name' => $agent->user->name ?? $agent->da_code,
                    'id' => $agent->da_code,
                    'status' => $status,
                    'photo_quality' => $photoQuality,
                    'system_action' => $systemAction,
                    'submission_time' => $submissionTime
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'compliance_summary' => [
                    'on_time' => $onTimeSubmissions,
                    'fully_compliant' => $fullyCompliant,
                    'flagged' => $flagged,
                    'non_compliant' => $nonCompliant,
                    'fees_blocked' => $feesBlocked,
                    'deductions' => $deductions,
                    'strikes' => $strikes
                ],
                'auto_enforcement' => 'active',
                'agents' => $agents->take(10)->values() // Limit to 10 for display
            ]
        ]);
    }

    /**
     * Photo compliance details by DA
     * GET /api/inventory-portal/da/compliance/photos
     */
    public function getPhotoComplianceDetails(): JsonResponse
    {
        $thisWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $photoCompliance = DeliveryAgent::where('status', 'active')
            ->with(['stockPhotos' => function($query) use ($thisWeek, $endOfWeek) {
                $query->whereBetween('uploaded_at', [$thisWeek, $endOfWeek]);
            }])
            ->get()
            ->map(function($agent) use ($thisWeek, $endOfWeek) {
                $photos = $agent->stockPhotos;
                $totalPhotos = $photos->count();
                $clearPhotos = $photos->where('photo_quality', 'clear')->count();
                $unclearPhotos = $photos->where('photo_quality', 'unclear')->count();
                $missingPhotos = 7 - $totalPhotos; // Assuming 7 days per week

                $complianceRate = $totalPhotos > 0 ? round(($clearPhotos / $totalPhotos) * 100, 1) : 0;

                return [
                    'da_id' => $agent->da_code,
                    'da_name' => $agent->user->name ?? $agent->da_code,
                    'total_photos' => $totalPhotos,
                    'clear_photos' => $clearPhotos,
                    'unclear_photos' => $unclearPhotos,
                    'missing_photos' => $missingPhotos,
                    'compliance_rate' => $complianceRate,
                    'status' => $complianceRate >= 80 ? 'compliant' : ($complianceRate >= 60 ? 'warning' : 'critical'),
                    'last_upload' => $photos->sortByDesc('uploaded_at')->first()?->uploaded_at?->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'photo_compliance' => $photoCompliance,
                'summary' => [
                    'total_das' => $photoCompliance->count(),
                    'compliant_das' => $photoCompliance->where('status', 'compliant')->count(),
                    'warning_das' => $photoCompliance->where('status', 'warning')->count(),
                    'critical_das' => $photoCompliance->where('status', 'critical')->count()
                ]
            ]
        ]);
    }

    /**
     * Strike, deduction, verify actions
     * POST /api/inventory-portal/da/compliance/actions
     */
    public function performComplianceAction(Request $request): JsonResponse
    {
        $request->validate([
            'da_id' => 'required|string',
            'action_type' => 'required|in:strike,deduction,verify,escalate',
            'reason' => 'required|string',
            'amount' => 'nullable|numeric|min:0'
        ]);

        $da = DeliveryAgent::where('da_code', $request->da_id)->first();
        
        if (!$da) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery Agent not found'
            ], 404);
        }

        $action = $request->action_type;
        $reason = $request->reason;
        $amount = $request->amount ?? 0;

        switch ($action) {
            case 'strike':
                // Add strike to DA
                StrikeLog::create([
                    'delivery_agent_id' => $da->id,
                    'violation_type' => 'photo_compliance',
                    'description' => $reason,
                    'strikes_given' => 1,
                    'status' => 'pending_review',
                    'reported_by' => auth()->id()
                ]);

                // Update DA strikes count
                $da->increment('strikes_count');
                break;

            case 'deduction':
                // Create deduction record
                // Assuming you have a deductions table or similar
                $da->update([
                    'pending_deductions' => DB::raw("pending_deductions + $amount")
                ]);
                break;

            case 'verify':
                // Mark photo as verified
                StockPhoto::where('delivery_agent_id', $da->id)
                    ->where('uploaded_at', '>=', now()->startOfWeek())
                    ->update(['verified_at' => now()]);
                break;

            case 'escalate':
                // Escalate to management
                StrikeLog::create([
                    'delivery_agent_id' => $da->id,
                    'violation_type' => 'escalated',
                    'description' => $reason,
                    'status' => 'escalated',
                    'reported_by' => auth()->id()
                ]);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($action) . ' action performed successfully',
            'data' => [
                'da_id' => $da->da_code,
                'action_type' => $action,
                'amount' => $amount,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get compliance trends
     * GET /api/inventory-portal/da/compliance/trends
     */
    public function getComplianceTrends(): JsonResponse
    {
        $weeks = collect();
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $totalDAs = DeliveryAgent::where('status', 'active')->count();
            $compliantDAs = StockPhoto::whereBetween('uploaded_at', [$weekStart, $weekEnd])
                ->where('uploaded_at', '<=', $weekEnd->copy()->subDays(1)->setTime(12, 0))
                ->where('photo_quality', 'clear')
                ->distinct('delivery_agent_id')
                ->count();

            $complianceRate = $totalDAs > 0 ? round(($compliantDAs / $totalDAs) * 100, 1) : 0;

            $weeks->push([
                'week' => $weekStart->format('M j'),
                'compliance_rate' => $complianceRate,
                'total_das' => $totalDAs,
                'compliant_das' => $compliantDAs
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trends' => $weeks,
                'current_week_rate' => $weeks->last()['compliance_rate'],
                'previous_week_rate' => $weeks->get(2)['compliance_rate'],
                'trend_direction' => $weeks->last()['compliance_rate'] > $weeks->get(2)['compliance_rate'] ? 'improving' : 'declining'
            ]
        ]);
    }
}
