<?php

namespace App\Services;

use App\Models\TransactionLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionLockService
{
    /**
     * Lock a module for a specific period
     */
    public function lockModule(string $module, Carbon $tillDate, string $reason = null): bool
    {
        try {
            // Check if module is already locked
            if ($this->isModuleLocked($module)) {
                Log::warning('Attempted to lock already locked module', [
                    'module' => $module,
                    'reason' => $reason,
                ]);
                return false;
            }

            $lock = TransactionLock::lockModule($module, $tillDate, $reason);

            if ($lock) {
                Log::info('Module locked successfully', [
                    'module' => $module,
                    'locked_till' => $tillDate->format('Y-m-d'),
                    'reason' => $reason,
                    'locked_by' => auth()->id(),
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to lock module', [
                'module' => $module,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a module is locked for a specific date
     */
    public function isModuleLocked(string $module, Carbon $date = null): bool
    {
        $checkDate = $date ? $date->toDateString() : now()->toDateString();
        
        return TransactionLock::isModuleLocked($module, $checkDate);
    }

    /**
     * Unlock a module
     */
    public function unlockModule(string $module): bool
    {
        try {
            $result = TransactionLock::unlockModule($module);

            if ($result) {
                Log::info('Module unlocked successfully', [
                    'module' => $module,
                    'unlocked_by' => auth()->id(),
                ]);
            }

            return $result > 0;

        } catch (\Exception $e) {
            Log::error('Failed to unlock module', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all active locks
     */
    public function getActiveLocks(): array
    {
        $locks = TransactionLock::getActiveLocks();
        
        return $locks->map(function ($lock) {
            return [
                'id' => $lock->id,
                'module' => $lock->module,
                'locked_till' => $lock->locked_till->format('Y-m-d'),
                'days_remaining' => $lock->days_remaining,
                'lock_reason' => $lock->lock_reason,
                'locked_by' => $lock->lockedBy->name ?? 'Unknown',
                'locked_at' => $lock->locked_at->format('Y-m-d H:i:s'),
                'is_active' => $lock->is_active,
                'status_color' => $lock->status_color,
                'status_text' => $lock->status_text,
            ];
        })->toArray();
    }

    /**
     * Get lock summary by module
     */
    public function getLockSummary(): array
    {
        return TransactionLock::getLockSummary()->toArray();
    }

    /**
     * Get locked modules list
     */
    public function getLockedModules(): array
    {
        return TransactionLock::getLockedModules();
    }

    /**
     * Check if any critical modules are locked
     */
    public function getCriticalLockStatus(): array
    {
        $criticalModules = ['Sales', 'Banking', 'Payroll'];
        $lockedModules = $this->getLockedModules();
        
        $criticalLocks = [];
        foreach ($criticalModules as $module) {
            if (in_array($module, $lockedModules)) {
                $lock = TransactionLock::getLockForModule($module);
                $criticalLocks[$module] = [
                    'is_locked' => true,
                    'locked_till' => $lock->locked_till->format('Y-m-d'),
                    'days_remaining' => $lock->days_remaining,
                    'lock_reason' => $lock->lock_reason,
                    'locked_by' => $lock->lockedBy->name ?? 'Unknown',
                ];
            } else {
                $criticalLocks[$module] = [
                    'is_locked' => false,
                ];
            }
        }

        return $criticalLocks;
    }

    /**
     * Extend an existing lock
     */
    public function extendLock(string $module, Carbon $newDate): bool
    {
        try {
            $lock = TransactionLock::getLockForModule($module);
            
            if (!$lock) {
                Log::warning('Attempted to extend non-existent lock', [
                    'module' => $module,
                ]);
                return false;
            }

            $oldDate = $lock->locked_till;
            $lock->extendLock($newDate);

            Log::info('Lock extended successfully', [
                'module' => $module,
                'old_date' => $oldDate->format('Y-m-d'),
                'new_date' => $newDate->format('Y-m-d'),
                'extended_by' => auth()->id(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to extend lock', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release a lock early
     */
    public function releaseLock(string $module): bool
    {
        try {
            $lock = TransactionLock::getLockForModule($module);
            
            if (!$lock) {
                Log::warning('Attempted to release non-existent lock', [
                    'module' => $module,
                ]);
                return false;
            }

            $lock->releaseLock();

            Log::info('Lock released early', [
                'module' => $module,
                'released_by' => auth()->id(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to release lock', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get lock statistics
     */
    public function getLockStatistics(): array
    {
        $activeLocks = TransactionLock::active()->count();
        $expiredLocks = TransactionLock::expired()->count();
        $totalLocks = TransactionLock::count();

        $moduleBreakdown = TransactionLock::active()
            ->get()
            ->groupBy('module')
            ->map(function ($locks) {
                return [
                    'count' => $locks->count(),
                    'total_days' => $locks->sum('days_remaining'),
                    'avg_days_remaining' => $locks->avg('days_remaining'),
                ];
            });

        return [
            'active_locks' => $activeLocks,
            'expired_locks' => $expiredLocks,
            'total_locks' => $totalLocks,
            'module_breakdown' => $moduleBreakdown,
        ];
    }

    /**
     * Validate if a transaction can proceed
     */
    public function canProceedWithTransaction(string $module, Carbon $date = null): array
    {
        $isLocked = $this->isModuleLocked($module, $date);
        
        if ($isLocked) {
            $lock = TransactionLock::getLockForModule($module);
            return [
                'can_proceed' => false,
                'reason' => 'Module is locked',
                'locked_till' => $lock->locked_till->format('Y-m-d'),
                'days_remaining' => $lock->days_remaining,
                'lock_reason' => $lock->lock_reason,
                'locked_by' => $lock->lockedBy->name ?? 'Unknown',
            ];
        }

        return [
            'can_proceed' => true,
            'reason' => 'Module is not locked',
        ];
    }
} 