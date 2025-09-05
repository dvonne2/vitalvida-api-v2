<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {}

    public function mintAndRedirect(string $token)
    {
        $tokenData = $this->referralService->decryptReferralToken($token);
        
        if (!$tokenData) {
            return redirect('/order/form')->with('error', 'Invalid referral link');
        }

        // Set httpOnly SameSite=Lax cookie for 30 days
        cookie()->queue(cookie('ref_token', $token, 60 * 24 * 30, '/', null, false, true, false, 'lax'));
        
        // Also store in session for immediate access
        session(['ref_token' => $token]);

        return redirect('/order/form');
    }

    public function formPreview(Request $request): JsonResponse
    {
        $customerPhone = $request->get('phone');
        $customerAddress = $request->get('address');

        if (!$customerPhone) {
            return response()->json([
                'friendEligible' => false,
                'friendWillApply' => false,
                'referrerWillApply' => false,
                'reasons' => ['missing_phone']
            ]);
        }

        $preview = $this->referralService->getFormPreview($customerPhone, $customerAddress);
        
        return response()->json($preview);
    }

    public function summary(Request $request): JsonResponse
    {
        $customerId = $request->get('customerId');
        
        if (!$customerId) {
            return response()->json(['error' => 'Customer ID required'], 400);
        }

        $summary = $this->referralService->getReferralSummary($customerId);
        
        return response()->json($summary);
    }

    public function relations(Request $request): JsonResponse
    {
        $customerId = $request->get('customerId');
        
        if (!$customerId) {
            return response()->json(['error' => 'Customer ID required'], 400);
        }

        $relations = $this->referralService->getReferralRelations($customerId);
        
        return response()->json($relations);
    }
}
