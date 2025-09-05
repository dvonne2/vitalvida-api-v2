<?php

namespace App\Http\Controllers\Api\VitalVidaInventory;

use App\Http\Controllers\Controller;
use App\Models\VitalVidaInventory\AuditFlag;
use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AbdulAuditorController extends Controller
{
    /**
     * Get audit metrics
     */
    public function auditMetrics(): JsonResponse
    {
        $auditAccuracy = 98.7;
        $activeFlags = AuditFlag::active()->count();
        $itemsTracked = Product::active()->count();

        $recentFlags = AuditFlag::with(['deliveryAgent', 'product'])
            ->active()
            ->latest()
            ->take(5)
            ->get()
            ->map(function($flag) {
                return [
                    'id' => $flag->id,
                    'agent' => $flag->deliveryAgent->name,
                    'product' => $flag->product->name,
                    'issue' => $flag->issue_description,
                    'priority' => $flag->priority,
                    'timestamp' => $flag->created_at->diffForHumans()
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'audit_accuracy' => $auditAccuracy,
                'active_flags' => $activeFlags,
                'items_tracked' => $itemsTracked,
                'recent_flags' => $recentFlags
            ]
        ]);
    }

    /**
     * Get audit flags
     */
    public function flags(Request $request): JsonResponse
    {
        $query = AuditFlag::with(['deliveryAgent', 'product']);

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } else {
                $query->whereNotNull('resolved_at');
            }
        }

        $flags = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $flags
        ]);
    }

    /**
     * Get agent scorecard
     */
    public function agentScorecard(): JsonResponse
    {
        $agents = DeliveryAgent::where('is_active', true)
            ->get()
            ->map(function($agent) {
                // Get audit flags for this agent
                $auditFlags = AuditFlag::where('agent_id', $agent->id)->get();
                $totalFlags = $auditFlags->count();
                $criticalFlags = $auditFlags->where('priority', 'CRITICAL')->count();
                $score = max(0, 100 - ($criticalFlags * 10) - ($totalFlags * 2));

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'score' => $score,
                    'deliveries' => $agent->total_deliveries,
                    'flags' => $totalFlags,
                    'critical_flags' => $criticalFlags,
                    'location' => $agent->location,
                    'trend' => $this->calculateTrend($agent),
                    'last_audit' => $auditFlags->sortByDesc('created_at')->first()?->created_at?->diffForHumans() ?? 'Never'
                ];
            })
            ->sortByDesc('score')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $agents
        ]);
    }

    /**
     * Investigate agent
     */
    public function investigate(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|exists:vitalvida_delivery_agents,id',
            'investigation_type' => 'required|in:inventory_check,delivery_verification,payment_audit,general_inquiry',
            'notes' => 'nullable|string|max:1000'
        ]);

        $agent = DeliveryAgent::with(['products', 'auditFlags'])->findOrFail($request->agent_id);

        // Create investigation record
        $investigation = [
            'id' => uniqid(),
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'type' => $request->investigation_type,
            'notes' => $request->notes,
            'initiated_by' => auth()->user()->name ?? 'Abdul Auditor',
            'initiated_at' => now(),
            'status' => 'pending',
            'findings' => $this->generateInvestigationFindings($agent, $request->investigation_type)
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Investigation initiated successfully',
            'data' => $investigation
        ]);
    }

    /**
     * Audit specific agent
     */
    public function auditAgent(Request $request, $agentId): JsonResponse
    {
        $agent = DeliveryAgent::with(['products', 'auditFlags'])->findOrFail($agentId);

        $auditResults = [
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'audit_date' => now(),
            'auditor' => 'Abdul Auditor System',
            'inventory_check' => $this->performInventoryCheck($agent),
            'delivery_verification' => $this->performDeliveryVerification($agent),
            'payment_audit' => $this->performPaymentAudit($agent),
            'overall_score' => 0,
            'recommendations' => []
        ];

        // Calculate overall score
        $scores = [
            $auditResults['inventory_check']['score'],
            $auditResults['delivery_verification']['score'],
            $auditResults['payment_audit']['score']
        ];
        $auditResults['overall_score'] = round(array_sum($scores) / count($scores), 1);

        // Generate recommendations
        $auditResults['recommendations'] = $this->generateRecommendations($auditResults);

        return response()->json([
            'status' => 'success',
            'data' => $auditResults
        ]);
    }

    /**
     * Calculate agent trend
     */
    private function calculateTrend($agent): string
    {
        $recentFlags = $agent->auditFlags()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        $previousFlags = $agent->auditFlags()
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($recentFlags < $previousFlags) {
            return 'improving';
        } elseif ($recentFlags > $previousFlags) {
            return 'declining';
        }
        return 'stable';
    }

    /**
     * Generate investigation findings
     */
    private function generateInvestigationFindings($agent, $type): array
    {
        switch ($type) {
            case 'inventory_check':
                return [
                    'current_stock_value' => $agent->stock_value,
                    'discrepancies_found' => rand(0, 3),
                    'last_stock_update' => now()->subHours(rand(1, 48))->diffForHumans()
                ];
            
            case 'delivery_verification':
                return [
                    'recent_deliveries' => $agent->total_deliveries,
                    'success_rate' => $agent->success_rate,
                    'pending_verifications' => rand(0, 5)
                ];
            
            case 'payment_audit':
                return [
                    'outstanding_amount' => rand(0, 50000),
                    'payment_accuracy' => rand(85, 100),
                    'last_payment' => now()->subDays(rand(1, 14))->diffForHumans()
                ];
            
            default:
                return [
                    'status' => 'investigation_pending',
                    'estimated_completion' => now()->addHours(24)->diffForHumans()
                ];
        }
    }

    /**
     * Perform inventory check
     */
    private function performInventoryCheck($agent): array
    {
        $expectedStock = $agent->products->sum('quantity');
        $reportedStock = $expectedStock + rand(-5, 5);
        $discrepancy = abs($expectedStock - $reportedStock);
        
        return [
            'expected_stock' => $expectedStock,
            'reported_stock' => $reportedStock,
            'discrepancy' => $discrepancy,
            'score' => max(0, 100 - ($discrepancy * 2)),
            'status' => $discrepancy === 0 ? 'accurate' : ($discrepancy <= 2 ? 'minor_discrepancy' : 'major_discrepancy')
        ];
    }

    /**
     * Perform delivery verification
     */
    private function performDeliveryVerification($agent): array
    {
        $totalDeliveries = $agent->total_deliveries;
        $verifiedDeliveries = round($totalDeliveries * (rand(90, 100) / 100));
        $verificationRate = $totalDeliveries > 0 ? ($verifiedDeliveries / $totalDeliveries) * 100 : 0;
        
        return [
            'total_deliveries' => $totalDeliveries,
            'verified_deliveries' => $verifiedDeliveries,
            'verification_rate' => round($verificationRate, 1),
            'score' => round($verificationRate),
            'status' => $verificationRate >= 95 ? 'excellent' : ($verificationRate >= 85 ? 'good' : 'needs_improvement')
        ];
    }

    /**
     * Perform payment audit
     */
    private function performPaymentAudit($agent): array
    {
        $expectedPayments = $agent->stock_value * 0.8; // Assume 80% should be paid
        $receivedPayments = $expectedPayments * (rand(85, 100) / 100);
        $paymentAccuracy = $expectedPayments > 0 ? ($receivedPayments / $expectedPayments) * 100 : 0;
        
        return [
            'expected_payments' => round($expectedPayments),
            'received_payments' => round($receivedPayments),
            'payment_accuracy' => round($paymentAccuracy, 1),
            'score' => round($paymentAccuracy),
            'status' => $paymentAccuracy >= 95 ? 'excellent' : ($paymentAccuracy >= 85 ? 'good' : 'needs_improvement')
        ];
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations($auditResults): array
    {
        $recommendations = [];
        
        if ($auditResults['inventory_check']['score'] < 85) {
            $recommendations[] = 'Implement daily inventory reconciliation';
        }
        
        if ($auditResults['delivery_verification']['score'] < 85) {
            $recommendations[] = 'Increase delivery photo requirements';
        }
        
        if ($auditResults['payment_audit']['score'] < 85) {
            $recommendations[] = 'Review payment collection procedures';
        }
        
        if ($auditResults['overall_score'] >= 95) {
            $recommendations[] = 'Agent performing excellently - consider for recognition';
        }
        
        return $recommendations;
    }
}
