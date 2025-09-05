<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ProfitAllocation;
use App\Services\ProfitFirstService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitFirstController extends Controller
{
    protected $profitFirstService;

    public function __construct(ProfitFirstService $profitFirstService)
    {
        $this->profitFirstService = $profitFirstService;
    }

    /**
     * Get wallet balances
     */
    public function getWalletBalances(): JsonResponse
    {
        try {
            $walletBalances = $this->profitFirstService->getWalletBalances();
            $complianceReport = $this->profitFirstService->getComplianceReport();

            $data = [
                'wallets' => $walletBalances,
                'compliance' => $complianceReport,
                'total_allocated' => array_sum(array_column($walletBalances, 'total_allocated')),
                'compliance_percentage' => $complianceReport['compliance_percentage'] ?? 0,
                'total_potential_savings' => $complianceReport['total_allocated'] ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Wallet balances retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load wallet balances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Allocate revenue to Profit First wallets
     */
    public function allocateRevenue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'payment_reference' => 'required|string|max:255',
            ]);

            $amount = $request->input('amount');
            $paymentRef = $request->input('payment_reference');

            $result = $this->profitFirstService->allocateRevenue($amount, $paymentRef);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'message' => 'Revenue allocated successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to allocate revenue',
                    'error' => $result['error'],
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate revenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transfer funds between wallets
     */
    public function transferFunds(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_wallet' => 'required|string|in:marketing,opex,inventory,profit,bonus,tax',
                'to_wallet' => 'required|string|in:marketing,opex,inventory,profit,bonus,tax',
                'amount' => 'required|numeric|min:0',
            ]);

            $fromWallet = $request->input('from_wallet');
            $toWallet = $request->input('to_wallet');
            $amount = $request->input('amount');

            $success = $this->profitFirstService->transferBetweenWallets($fromWallet, $toWallet, $amount);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Funds transferred successfully',
                    'data' => [
                        'from_wallet' => $fromWallet,
                        'to_wallet' => $toWallet,
                        'amount' => $amount,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to transfer funds',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer funds',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent allocations
     */
    public function getRecentAllocations(): JsonResponse
    {
        try {
            $recentAllocations = ProfitAllocation::with('bankAccount')
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get();

            $data = $recentAllocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'payment_reference' => $allocation->payment_reference,
                    'amount_received' => $allocation->amount_received,
                    'formatted_amount_received' => $allocation->formatted_amount_received,
                    'allocated_to' => $allocation->allocated_to,
                    'amount_allocated' => $allocation->amount_allocated,
                    'formatted_amount_allocated' => $allocation->formatted_amount_allocated,
                    'allocation_percentage' => $allocation->allocation_percentage,
                    'allocation_status' => $allocation->allocation_status,
                    'status_color' => $allocation->status_color,
                    'status_icon' => $allocation->status_icon,
                    'wallet_type_icon' => $allocation->wallet_type_icon,
                    'wallet_type_color' => $allocation->wallet_type_color,
                    'created_at' => $allocation->created_at->format('Y-m-d H:i:s'),
                    'allocated_at' => $allocation->allocated_at ? $allocation->allocated_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Recent allocations retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get wallet details by type
     */
    public function getWalletDetails(string $walletType): JsonResponse
    {
        try {
            $wallet = BankAccount::where('wallet_type', $walletType)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wallet not found',
                ], 404);
            }

            $allocations = ProfitAllocation::where('allocated_to', $walletType)
                ->with('bankAccount')
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get();

            $data = [
                'wallet' => [
                    'id' => $wallet->id,
                    'wallet_type' => $wallet->wallet_type,
                    'account_name' => $wallet->account_name,
                    'account_number' => $wallet->account_number,
                    'current_balance' => $wallet->current_balance,
                    'formatted_balance' => $wallet->formatted_balance,
                    'allocation_percentage' => $wallet->allocation_percentage,
                    'formatted_allocation' => $wallet->formatted_allocation,
                    'status' => $wallet->status,
                    'status_color' => $wallet->status_color,
                    'wallet_type_icon' => $wallet->wallet_type_icon,
                    'wallet_type_color' => $wallet->wallet_type_color,
                ],
                'allocations' => $allocations->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'payment_reference' => $allocation->payment_reference,
                        'amount_received' => $allocation->amount_received,
                        'formatted_amount_received' => $allocation->formatted_amount_received,
                        'amount_allocated' => $allocation->amount_allocated,
                        'formatted_amount_allocated' => $allocation->formatted_amount_allocated,
                        'allocation_percentage' => $allocation->allocation_percentage,
                        'allocation_status' => $allocation->allocation_status,
                        'status_color' => $allocation->status_color,
                        'status_icon' => $allocation->status_icon,
                        'created_at' => $allocation->created_at->format('Y-m-d H:i:s'),
                        'allocated_at' => $allocation->allocated_at ? $allocation->allocated_at->format('Y-m-d H:i:s') : null,
                    ];
                }),
                'summary' => [
                    'total_allocations' => $allocations->count(),
                    'total_allocated' => $allocations->sum('amount_allocated'),
                    'formatted_total_allocated' => '₦' . number_format($allocations->sum('amount_allocated'), 2),
                    'completed_allocations' => $allocations->where('allocation_status', 'completed')->count(),
                    'pending_allocations' => $allocations->where('allocation_status', 'pending')->count(),
                    'failed_allocations' => $allocations->where('allocation_status', 'failed')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Wallet details retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load wallet details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process pending allocations
     */
    public function processPendingAllocations(): JsonResponse
    {
        try {
            $result = $this->profitFirstService->processPendingAllocations();

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Pending allocations processed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process pending allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get compliance report
     */
    public function getComplianceReport(): JsonResponse
    {
        try {
            $report = $this->profitFirstService->getComplianceReport();

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Compliance report retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load compliance report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
