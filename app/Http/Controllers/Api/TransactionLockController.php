<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionLock;
use App\Services\TransactionLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TransactionLockController extends Controller
{
    protected $lockService;

    public function __construct(TransactionLockService $lockService)
    {
        $this->lockService = $lockService;
    }

    /**
     * Get all transaction locks
     */
    public function index(): JsonResponse
    {
        try {
            $locks = TransactionLock::with('lockedBy')
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $locks->map(function ($lock) {
                return [
                    'id' => $lock->id,
                    'module' => $lock->module,
                    'locked_till' => $lock->locked_till->format('Y-m-d'),
                    'lock_reason' => $lock->lock_reason,
                    'locked_by' => $lock->lockedBy ? $lock->lockedBy->name : 'Unknown',
                    'locked_at' => $lock->locked_at->format('Y-m-d H:i:s'),
                    'is_active' => $lock->is_active,
                    'days_remaining' => $lock->days_remaining,
                    'status_color' => $lock->status_color,
                    'status_text' => $lock->status_text,
                    'created_at' => $lock->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $lock->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Transaction locks retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transaction locks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new transaction lock
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'module' => 'required|string|in:Sales,Purchases,Banking,Payroll,Inventory',
                'locked_till' => 'required|date|after:today',
                'lock_reason' => 'required|string|max:500',
            ]);

            $module = $request->input('module');
            $lockedTill = Carbon::parse($request->input('locked_till'));
            $reason = $request->input('lock_reason');

            $success = $this->lockService->lockModule($module, $lockedTill, $reason);

            if ($success) {
                $lock = TransactionLock::where('module', $module)
                    ->where('locked_till', $lockedTill)
                    ->with('lockedBy')
                    ->first();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $lock->id,
                        'module' => $lock->module,
                        'locked_till' => $lock->locked_till->format('Y-m-d'),
                        'lock_reason' => $lock->lock_reason,
                        'locked_by' => $lock->lockedBy ? $lock->lockedBy->name : 'Unknown',
                        'locked_at' => $lock->locked_at->format('Y-m-d H:i:s'),
                        'is_active' => $lock->is_active,
                        'days_remaining' => $lock->days_remaining,
                        'status_color' => $lock->status_color,
                        'status_text' => $lock->status_text,
                    ],
                    'message' => 'Module locked successfully',
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to lock module',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to lock module',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unlock a module
     */
    public function unlock(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'module' => 'required|string|in:Sales,Purchases,Banking,Payroll,Inventory',
            ]);

            $module = $request->input('module');
            $success = $this->lockService->unlockModule($module);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'module' => $module,
                        'unlocked_at' => now()->format('Y-m-d H:i:s'),
                    ],
                    'message' => 'Module unlocked successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unlock module',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unlock module',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extend a lock
     */
    public function extend(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'module' => 'required|string|in:Sales,Purchases,Banking,Payroll,Inventory',
                'new_date' => 'required|date|after:today',
            ]);

            $module = $request->input('module');
            $newDate = Carbon::parse($request->input('new_date'));

            $success = $this->lockService->extendLock($module, $newDate);

            if ($success) {
                $lock = TransactionLock::where('module', $module)
                    ->where('locked_till', $newDate)
                    ->with('lockedBy')
                    ->first();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $lock->id,
                        'module' => $lock->module,
                        'locked_till' => $lock->locked_till->format('Y-m-d'),
                        'lock_reason' => $lock->lock_reason,
                        'locked_by' => $lock->lockedBy ? $lock->lockedBy->name : 'Unknown',
                        'locked_at' => $lock->locked_at->format('Y-m-d H:i:s'),
                        'is_active' => $lock->is_active,
                        'days_remaining' => $lock->days_remaining,
                        'status_color' => $lock->status_color,
                        'status_text' => $lock->status_text,
                    ],
                    'message' => 'Lock extended successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to extend lock',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend lock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get lock summary
     */
    public function getLockSummary(): JsonResponse
    {
        try {
            $summary = $this->lockService->getLockSummary();
            $activeLocks = $this->lockService->getActiveLocks();
            $lockedModules = $this->lockService->getLockedModules();
            $criticalStatus = $this->lockService->getCriticalLockStatus();

            $data = [
                'summary' => $summary,
                'active_locks' => $activeLocks->map(function ($lock) {
                    return [
                        'id' => $lock->id,
                        'module' => $lock->module,
                        'locked_till' => $lock->locked_till->format('Y-m-d'),
                        'lock_reason' => $lock->lock_reason,
                        'locked_by' => $lock->lockedBy ? $lock->lockedBy->name : 'Unknown',
                        'locked_at' => $lock->locked_at->format('Y-m-d H:i:s'),
                        'is_active' => $lock->is_active,
                        'days_remaining' => $lock->days_remaining,
                        'status_color' => $lock->status_color,
                        'status_text' => $lock->status_text,
                    ];
                }),
                'locked_modules' => $lockedModules,
                'critical_status' => $criticalStatus,
                'can_proceed' => $this->lockService->canProceedWithTransaction('Sales', now()),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Lock summary retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load lock summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if module is locked
     */
    public function checkLock(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'module' => 'required|string|in:Sales,Purchases,Banking,Payroll,Inventory',
                'date' => 'nullable|date',
            ]);

            $module = $request->input('module');
            $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();

            $isLocked = $this->lockService->isModuleLocked($module, $date);
            $canProceed = $this->lockService->canProceedWithTransaction($module, $date);

            return response()->json([
                'success' => true,
                'data' => [
                    'module' => $module,
                    'date' => $date->format('Y-m-d'),
                    'is_locked' => $isLocked,
                    'can_proceed' => $canProceed['can_proceed'],
                    'message' => $canProceed['message'],
                    'lock_details' => $canProceed['lock_details'] ?? null,
                ],
                'message' => 'Lock status checked successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check lock status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get lock statistics
     */
    public function getLockStatistics(): JsonResponse
    {
        try {
            $statistics = $this->lockService->getLockStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Lock statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load lock statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Release a lock
     */
    public function release(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'module' => 'required|string|in:Sales,Purchases,Banking,Payroll,Inventory',
            ]);

            $module = $request->input('module');
            $success = $this->lockService->releaseLock($module);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'module' => $module,
                        'released_at' => now()->format('Y-m-d H:i:s'),
                    ],
                    'message' => 'Lock released successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to release lock',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release lock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
