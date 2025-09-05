<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Get all journal entries
     */
    public function index(): JsonResponse
    {
        try {
            $entries = JournalEntry::with(['creator', 'approver', 'lines.account'])
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'entry_date' => $entry->entry_date->format('Y-m-d'),
                    'description' => $entry->description,
                    'total_amount' => $entry->total_amount,
                    'formatted_total_amount' => $entry->formatted_total_amount,
                    'status' => $entry->status,
                    'status_color' => $entry->status_color,
                    'status_icon' => $entry->status_icon,
                    'is_balanced' => $entry->is_balanced,
                    'debit_total' => $entry->debit_total,
                    'credit_total' => $entry->credit_total,
                    'creator' => $entry->creator ? $entry->creator->name : 'Unknown',
                    'approver' => $entry->approver ? $entry->approver->name : null,
                    'posted_at' => $entry->posted_at ? $entry->posted_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $entry->updated_at->format('Y-m-d H:i:s'),
                    'lines_count' => $entry->lines->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Journal entries retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load journal entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new journal entry
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'entry_date' => 'required|date',
                'description' => 'required|string|max:500',
                'lines' => 'required|array|min:2',
                'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
                'lines.*.debit_amount' => 'required_without:lines.*.credit_amount|numeric|min:0',
                'lines.*.credit_amount' => 'required_without:lines.*.debit_amount|numeric|min:0',
                'lines.*.line_description' => 'nullable|string|max:255',
            ]);

            // Validate double-entry balance
            $totalDebits = 0;
            $totalCredits = 0;

            foreach ($request->input('lines') as $line) {
                $debitAmount = $line['debit_amount'] ?? 0;
                $creditAmount = $line['credit_amount'] ?? 0;
                
                $totalDebits += $debitAmount;
                $totalCredits += $creditAmount;
            }

            if (abs($totalDebits - $totalCredits) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal entry is not balanced. Total debits must equal total credits.',
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                ], 400);
            }

            $entry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(),
                'entry_date' => $request->input('entry_date'),
                'description' => $request->input('description'),
                'total_amount' => $totalDebits,
                'created_by' => auth()->id(),
            ]);

            // Create journal entry lines
            foreach ($request->input('lines') as $lineData) {
                $entry->addLine([
                    'account_id' => $lineData['account_id'],
                    'debit_amount' => $lineData['debit_amount'] ?? 0,
                    'credit_amount' => $lineData['credit_amount'] ?? 0,
                    'line_description' => $lineData['line_description'] ?? null,
                ]);
            }

            $entry->load(['creator', 'lines.account']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'entry_date' => $entry->entry_date->format('Y-m-d'),
                    'description' => $entry->description,
                    'total_amount' => $entry->total_amount,
                    'formatted_total_amount' => $entry->formatted_total_amount,
                    'status' => $entry->status,
                    'status_color' => $entry->status_color,
                    'status_icon' => $entry->status_icon,
                    'is_balanced' => $entry->is_balanced,
                    'lines' => $entry->lines->map(function ($line) {
                        return [
                            'id' => $line->id,
                            'account_id' => $line->account_id,
                            'account_name' => $line->account_name,
                            'account_code' => $line->account_code,
                            'debit_amount' => $line->debit_amount,
                            'formatted_debit_amount' => $line->formatted_debit_amount,
                            'credit_amount' => $line->credit_amount,
                            'formatted_credit_amount' => $line->formatted_credit_amount,
                            'net_amount' => $line->net_amount,
                            'formatted_net_amount' => $line->formatted_net_amount,
                            'line_description' => $line->line_description,
                        ];
                    }),
                ],
                'message' => 'Journal entry created successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific journal entry
     */
    public function show(JournalEntry $entry): JsonResponse
    {
        try {
            $entry->load(['creator', 'approver', 'lines.account']);

            $data = [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'total_amount' => $entry->total_amount,
                'formatted_total_amount' => $entry->formatted_total_amount,
                'status' => $entry->status,
                'status_color' => $entry->status_color,
                'status_icon' => $entry->status_icon,
                'is_balanced' => $entry->is_balanced,
                'debit_total' => $entry->debit_total,
                'credit_total' => $entry->credit_total,
                'creator' => $entry->creator ? $entry->creator->name : 'Unknown',
                'approver' => $entry->approver ? $entry->approver->name : null,
                'posted_at' => $entry->posted_at ? $entry->posted_at->format('Y-m-d H:i:s') : null,
                'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $entry->updated_at->format('Y-m-d H:i:s'),
                'lines' => $entry->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'account_id' => $line->account_id,
                        'account_name' => $line->account_name,
                        'account_code' => $line->account_code,
                        'account_type' => $line->account_type,
                        'debit_amount' => $line->debit_amount,
                        'formatted_debit_amount' => $line->formatted_debit_amount,
                        'credit_amount' => $line->credit_amount,
                        'formatted_credit_amount' => $line->formatted_credit_amount,
                        'net_amount' => $line->net_amount,
                        'formatted_net_amount' => $line->formatted_net_amount,
                        'line_description' => $line->line_description,
                        'is_debit' => $line->is_debit,
                        'is_credit' => $line->is_credit,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Journal entry retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a journal entry
     */
    public function post(JournalEntry $entry): JsonResponse
    {
        try {
            if ($entry->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft entries can be posted',
                ], 400);
            }

            if (!$entry->is_balanced) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal entry must be balanced before posting',
                ], 400);
            }

            $entry->post();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'status' => $entry->status,
                    'status_color' => $entry->status_color,
                    'status_icon' => $entry->status_icon,
                    'posted_at' => $entry->posted_at->format('Y-m-d H:i:s'),
                ],
                'message' => 'Journal entry posted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to post journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse a journal entry
     */
    public function reverse(JournalEntry $entry): JsonResponse
    {
        try {
            if ($entry->status !== 'posted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only posted entries can be reversed',
                ], 400);
            }

            $reversedEntry = $entry->reverse();

            return response()->json([
                'success' => true,
                'data' => [
                    'original_entry_id' => $entry->id,
                    'reversed_entry_id' => $reversedEntry->id,
                    'reversed_reference_number' => $reversedEntry->reference_number,
                    'status' => $reversedEntry->status,
                    'status_color' => $reversedEntry->status_color,
                    'status_icon' => $reversedEntry->status_icon,
                ],
                'message' => 'Journal entry reversed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reverse journal entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trial balance
     */
    public function getTrialBalance(): JsonResponse
    {
        try {
            $accounts = ChartOfAccount::with('childAccounts')->get();
            $trialBalance = [];

            foreach ($accounts as $account) {
                $debitTotal = $account->journalEntryLines()->debits()->sum('debit_amount');
                $creditTotal = $account->journalEntryLines()->credits()->sum('credit_amount');
                $balance = $debitTotal - $creditTotal;

                $trialBalance[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance' => $balance,
                    'formatted_balance' => '₦' . number_format($balance, 2),
                    'is_debit_balance' => $balance > 0,
                    'is_credit_balance' => $balance < 0,
                ];
            }

            $totalDebits = collect($trialBalance)->sum('debit_total');
            $totalCredits = collect($trialBalance)->sum('credit_total');
            $totalBalance = collect($trialBalance)->sum('balance');

            return response()->json([
                'success' => true,
                'data' => [
                    'accounts' => $trialBalance,
                    'summary' => [
                        'total_debits' => $totalDebits,
                        'total_credits' => $totalCredits,
                        'total_balance' => $totalBalance,
                        'formatted_total_debits' => '₦' . number_format($totalDebits, 2),
                        'formatted_total_credits' => '₦' . number_format($totalCredits, 2),
                        'formatted_total_balance' => '₦' . number_format($totalBalance, 2),
                        'is_balanced' => abs($totalBalance) < 0.01,
                    ],
                ],
                'message' => 'Trial balance retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trial balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unbalanced entries
     */
    public function getUnbalancedEntries(): JsonResponse
    {
        try {
            $unbalancedEntries = JournalEntry::getUnbalancedEntries()
                ->with(['creator', 'lines.account'])
                ->get();

            $data = $unbalancedEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'entry_date' => $entry->entry_date->format('Y-m-d'),
                    'description' => $entry->description,
                    'total_amount' => $entry->total_amount,
                    'formatted_total_amount' => $entry->formatted_total_amount,
                    'debit_total' => $entry->debit_total,
                    'credit_total' => $entry->credit_total,
                    'balance_difference' => $entry->debit_total - $entry->credit_total,
                    'formatted_balance_difference' => '₦' . number_format($entry->debit_total - $entry->credit_total, 2),
                    'status' => $entry->status,
                    'status_color' => $entry->status_color,
                    'status_icon' => $entry->status_icon,
                    'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Unbalanced entries retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load unbalanced entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
