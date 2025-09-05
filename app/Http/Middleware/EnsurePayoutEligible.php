<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PayoutEligibilityService;
use App\Helpers\SystemLogger;

class EnsurePayoutEligible
{
    protected $eligibilityService;

    public function __construct(PayoutEligibilityService $eligibilityService)
    {
        $this->eligibilityService = $eligibilityService;
    }

    public function handle(Request $request, Closure $next)
    {
        $orderId = $request->route('orderId') ?? $request->order_id ?? $request->id;
        
        if (!$orderId) {
            return response()->json(['error' => 'Order ID required for eligibility check'], 400);
        }

        $eligibility = $this->eligibilityService->checkEligibility($orderId);

        if (!$eligibility['eligible']) {
            // Log the blocked attempt
            SystemLogger::logAction('payout_blocked_by_middleware', auth()->id(), request()->ip(), [
                'order_id' => $orderId,
                'route' => $request->route()->getName(),
                'reasons' => $eligibility['reasons'],
                'da_id' => auth()->user()->delivery_agent_id ?? null
            ]);

            return response()->json([
                'error' => 'Payout not eligible: ' . implode(' and ', $eligibility['reasons']),
                'eligible' => false,
                'reasons' => $eligibility['reasons'],
                'order_id' => $orderId
            ], 403);
        }

        // Log successful eligibility check
        SystemLogger::logAction('payout_eligible_middleware_passed', auth()->id(), request()->ip(), [
            'order_id' => $orderId,
            'route' => $request->route()->getName(),
            'da_id' => auth()->user()->delivery_agent_id ?? null
        ]);

        return $next($request);
    }
}
