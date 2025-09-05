<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ThresholdValidationService;

class EnforceThresholds
{
    public function __construct(
        private ThresholdValidationService $thresholdService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Only apply to payment/cost creation requests
        if (!$this->shouldEnforceThresholds($request)) {
            return $next($request);
        }

        // Extract cost data from request
        $costData = $this->extractCostData($request);

        if ($costData) {
            // Validate against thresholds
            $validation = $this->thresholdService->validateCost($costData);

            // Block if validation fails
            if (!$validation['valid']) {
                return response()->json([
                    'error' => 'Payment blocked - exceeds business thresholds',
                    'violation_details' => $validation,
                    'requires_escalation' => $validation['requires_escalation'] ?? false,
                    'message' => 'This payment has been blocked and escalated for FC+GM approval.',
                    'next_steps' => [
                        'An escalation request has been created',
                        'FC and GM will be notified for approval',
                        'Payment will be authorized only after dual approval',
                        'Escalation expires in 48 hours if not approved'
                    ]
                ], 422);
            }
        }

        return $next($request);
    }

    private function shouldEnforceThresholds(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'logistics.store',
            'expenses.store', 
            'bonuses.store',
            'payments.create'
        ]) && $request->isMethod('POST');
    }

    private function extractCostData(Request $request): ?array
    {
        $routeName = $request->route()?->getName();
        
        return match($routeName) {
            'logistics.store' => [
                'type' => 'logistics',
                'amount' => $request->input('total_cost', 0),
                'quantity' => $request->input('quantity', 1),
                'storekeeper_fee' => $request->input('storekeeper_fee', 0),
                'transport_fare' => $request->input('transport_fare', 0),
                'user_id' => $request->user()?->id,
                'reference_type' => 'App\\Models\\LogisticsCost',
                'reference_id' => null // Will be set after creation
            ],
            'expenses.store' => [
                'type' => 'expense',
                'category' => $request->input('category'),
                'amount' => $request->input('amount', 0),
                'user_id' => $request->user()?->id,
                'reference_type' => 'App\\Models\\Expense',
                'reference_id' => null
            ],
            'bonuses.store' => [
                'type' => 'bonus',
                'amount' => $request->input('amount', 0),
                'user_id' => $request->user()?->id,
                'reference_type' => 'App\\Models\\Bonus',
                'reference_id' => null
            ],
            default => null
        };
    }
}
