<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ReferralReward;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ReferralService
{
    public function generateReferralToken(int $referrerId): string
    {
        $payload = [
            'referrer_id' => $referrerId,
            'iat' => time()
        ];
        
        return Crypt::encryptString(json_encode($payload));
    }

    public function decryptReferralToken(string $token): ?array
    {
        try {
            $decrypted = Crypt::decryptString($token);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFormPreview(string $customerPhone, ?string $customerAddress = null): array
    {
        $refToken = session('ref_token') ?? request()->cookie('ref_token');
        
        if (!$refToken) {
            return [
                'friendEligible' => false,
                'friendWillApply' => false,
                'referrerWillApply' => false,
                'reasons' => ['no_token']
            ];
        }

        $tokenData = $this->decryptReferralToken($refToken);
        if (!$tokenData) {
            return [
                'friendEligible' => false,
                'friendWillApply' => false,
                'referrerWillApply' => false,
                'reasons' => ['invalid_token']
            ];
        }

        $referrerId = $tokenData['referrer_id'];
        $referrer = Customer::find($referrerId);
        
        if (!$referrer) {
            return [
                'friendEligible' => false,
                'friendWillApply' => false,
                'referrerWillApply' => false,
                'reasons' => ['referrer_not_found']
            ];
        }

        // Check if customer is eligible (first order + fraud gates)
        $friendEligible = $this->checkFriendEligibility($customerPhone, $customerAddress, $referrer);
        
        // Check if referrer has available rewards to apply
        $referrerWillApply = $this->checkReferrerRewardAvailable($referrerId);

        return [
            'friendEligible' => $friendEligible['eligible'],
            'friendWillApply' => $friendEligible['eligible'],
            'referrerWillApply' => $referrerWillApply,
            'reasons' => $friendEligible['reasons'] ?? []
        ];
    }

    private function checkFriendEligibility(string $customerPhone, ?string $customerAddress, Customer $referrer): array
    {
        // Check if customer has any prior paid orders (first order only)
        $existingOrders = Order::where('customer_phone', $customerPhone)
            ->whereNotNull('paid_at')
            ->count();

        if ($existingOrders > 0) {
            return ['eligible' => false, 'reasons' => ['not_first_order']];
        }

        // Check self-referral
        if ($referrer->phone === $customerPhone) {
            return ['eligible' => false, 'reasons' => ['self_referral']];
        }

        // Check address similarity (basic fraud gate)
        if ($customerAddress && $referrer->address) {
            $addressHash1 = md5(strtolower(trim($customerAddress)));
            $addressHash2 = md5(strtolower(trim($referrer->address)));
            
            if ($addressHash1 === $addressHash2) {
                return ['eligible' => false, 'reasons' => ['same_address']];
            }
        }

        return ['eligible' => true];
    }

    private function checkReferrerRewardAvailable(int $referrerId): bool
    {
        return ReferralReward::where('referrer_id', $referrerId)
            ->where('status', 'credited')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function applyFriendDiscount(Order $order, string $refToken): void
    {
        $order->update([
            'discount_naira' => ($order->discount_naira ?? 0) + 1500,
            'delivery_fee_naira' => 0,
            'referral_ref_token' => $refToken,
            'referral_friend_discount_applied' => true,
            'referral_free_delivery_applied' => true
        ]);
    }

    public function applyReferrerReward(Order $order, int $referrerId): void
    {
        // Find and redeem available reward
        $reward = ReferralReward::where('referrer_id', $referrerId)
            ->where('status', 'credited')
            ->where('expires_at', '>', now())
            ->first();

        if ($reward) {
            $order->update([
                'discount_naira' => ($order->discount_naira ?? 0) + $reward->cash_naira,
                'delivery_fee_naira' => $reward->free_delivery ? 0 : $order->delivery_fee_naira,
                'referrer_reward_applied' => true
            ]);

            $reward->update(['status' => 'redeemed']);
        }
    }

    public function creditReferrerReward(Order $friendOrder): void
    {
        if (!$friendOrder->referral_ref_token) {
            return;
        }

        $tokenData = $this->decryptReferralToken($friendOrder->referral_ref_token);
        if (!$tokenData) {
            return;
        }

        $referrerId = $tokenData['referrer_id'];

        ReferralReward::create([
            'referrer_id' => $referrerId,
            'referee_order_id' => $friendOrder->id,
            'cash_naira' => 1500,
            'free_delivery' => true,
            'status' => 'credited',
            'expires_at' => Carbon::now()->addDays(60)
        ]);
    }

    public function getReferralSummary(int $customerId): array
    {
        $customer = Customer::find($customerId);
        $refToken = $this->generateReferralToken($customerId);
        
        // Get available rewards
        $rewards = ReferralReward::where('referrer_id', $customerId)
            ->where('status', 'credited')
            ->where('expires_at', '>', now())
            ->get();

        $cashAvailable = $rewards->sum('cash_naira');
        $freeDeliveryVoucher = $rewards->where('free_delivery', true)->count() > 0 ? 'active' : 'none';
        $expiresAt = $rewards->min('expires_at');

        // Get activity (simplified - last 5 referee orders)
        $activity = Order::whereNotNull('referral_ref_token')
            ->get()
            ->filter(function ($order) use ($customerId) {
                $tokenData = $this->decryptReferralToken($order->referral_ref_token);
                return $tokenData && $tokenData['referrer_id'] === $customerId;
            })
            ->take(5)
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => $order->paid_at ? ($order->delivered_at ? 'delivered' : 'paid') : 'first_order',
                    'at' => $order->created_at->toISOString()
                ];
            });

        return [
            'refCode' => $refToken,
            'shortUrl' => url("/r/{$refToken}"),
            'shareTextSample' => "Get ₦1,500 off + FREE delivery on your first VitalVida order! Use my link: " . url("/r/{$refToken}"),
            'auto' => [
                'lastSentAt' => null,
                'lastChannel' => null,
                'lastStatus' => null,
                'nextWindowHint' => null
            ],
            'rewards' => [
                'cashAvailable' => $cashAvailable,
                'freeDeliveryVoucher' => $freeDeliveryVoucher,
                'expiresAt' => $expiresAt?->toISOString()
            ],
            'activity' => $activity->toArray(),
            'autoApply' => [
                'friendAutoApply' => true,
                'referrerAutoApply' => $cashAvailable > 0
            ]
        ];
    }

    public function getReferralRelations(int $customerId): array
    {
        // Who referred them
        $theirFirstPaid = Order::where('customer_id', $customerId)
            ->whereNotNull('paid_at')
            ->orderBy('paid_at')
            ->first();

        $referrer = null;
        if ($theirFirstPaid?->referral_ref_token) {
            $tokenData = $this->decryptReferralToken($theirFirstPaid->referral_ref_token);
            if ($tokenData) {
                $referrerCustomer = Customer::find($tokenData['referrer_id']);
                if ($referrerCustomer) {
                    $referrer = [
                        'customerId' => $referrerCustomer->id,
                        'name' => $referrerCustomer->name,
                        'phoneE164' => $referrerCustomer->phone,
                        'firstOrderAt' => $theirFirstPaid->created_at->toISOString(),
                        'progress' => $theirFirstPaid->delivered_at ? 'delivered' : ($theirFirstPaid->paid_at ? 'paid' : 'first_order'),
                        'reward' => 'credited',
                        'gmv' => $theirFirstPaid->total_naira
                    ];
                }
            }
        }

        // Who they referred
        $refereeOrders = Order::whereNotNull('referral_ref_token')
            ->get()
            ->filter(function ($order) use ($customerId) {
                $tokenData = $this->decryptReferralToken($order->referral_ref_token);
                return $tokenData && $tokenData['referrer_id'] === $customerId;
            })
            ->groupBy('customer_id')
            ->map->sortBy('created_at')->map->first();

        $referees = [];
        $successful = 0;
        $pending = 0;
        $blocked = 0;
        $totalGmv = 0;
        $rewardsPaid = 0;

        foreach ($refereeOrders as $order) {
            $customer = Customer::find($order->customer_id);
            if (!$customer) continue;

            $progress = $order->delivered_at ? 'delivered' : ($order->paid_at ? 'paid' : 'first_order');
            $reward = ReferralReward::where('referrer_id', $customerId)
                ->where('referee_order_id', $order->id)
                ->first();

            $referees[] = [
                'customerId' => $customer->id,
                'name' => $customer->name,
                'phoneE164' => $customer->phone,
                'firstOrderAt' => $order->created_at->toISOString(),
                'progress' => $progress,
                'reward' => $reward ? $reward->status : 'none',
                'gmv' => $order->paid_at ? $order->total_naira : null
            ];

            if ($progress === 'delivered') $successful++;
            elseif ($progress === 'paid') $pending++;
            
            if ($order->paid_at) {
                $totalGmv += $order->total_naira;
            }

            if ($reward && $reward->status === 'redeemed') {
                $rewardsPaid += $reward->cash_naira;
            }
        }

        return [
            'referrer' => $referrer,
            'referees' => $referees,
            'aggregates' => [
                'successful' => $successful,
                'pending' => $pending,
                'blocked' => $blocked,
                'gmv' => $totalGmv,
                'rewardsPaid' => $rewardsPaid
            ]
        ];
    }
}
