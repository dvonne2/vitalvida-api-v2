<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TransactionLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'locked_till',
        'locked_by',
        'lock_reason',
        'locked_at',
    ];

    protected $casts = [
        'locked_till' => 'date',
        'locked_at' => 'datetime',
    ];

    // Relationships
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // Scopes
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeActive($query)
    {
        return $query->where('locked_till', '>=', now()->toDateString());
    }

    public function scopeExpired($query)
    {
        return $query->where('locked_till', '<', now()->toDateString());
    }

    // Accessors
    public function getIsActiveAttribute()
    {
        return $this->locked_till >= now()->toDateString();
    }

    public function getDaysRemainingAttribute()
    {
        return now()->diffInDays($this->locked_till, false);
    }

    public function getStatusColorAttribute()
    {
        return $this->is_active ? 'text-red-600' : 'text-green-600';
    }

    public function getStatusTextAttribute()
    {
        return $this->is_active ? 'LOCKED' : 'EXPIRED';
    }

    // Methods
    public function isLockedForDate($date = null)
    {
        $checkDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        return $this->locked_till >= $checkDate;
    }

    public function extendLock($newDate)
    {
        $this->locked_till = $newDate;
        $this->save();
    }

    public function releaseLock()
    {
        $this->locked_till = now()->subDay();
        $this->save();
    }

    // Static methods
    public static function isModuleLocked($module, $date = null)
    {
        $checkDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        
        return static::where('module', $module)
            ->where('locked_till', '>=', $checkDate)
            ->exists();
    }

    public static function getActiveLocks()
    {
        return static::with('lockedBy')
            ->where('locked_till', '>=', now()->toDateString())
            ->orderBy('locked_till')
            ->get();
    }

    public static function getLockForModule($module)
    {
        return static::with('lockedBy')
            ->where('module', $module)
            ->where('locked_till', '>=', now()->toDateString())
            ->first();
    }

    public static function lockModule($module, $tillDate, $reason = null, $lockedBy = null)
    {
        // Check if module is already locked
        if (static::isModuleLocked($module)) {
            return false;
        }

        return static::create([
            'module' => $module,
            'locked_till' => $tillDate,
            'locked_by' => $lockedBy ?? auth()->id(),
            'lock_reason' => $reason,
            'locked_at' => now(),
        ]);
    }

    public static function unlockModule($module)
    {
        return static::where('module', $module)
            ->where('locked_till', '>=', now()->toDateString())
            ->update(['locked_till' => now()->subDay()]);
    }

    public static function getLockedModules()
    {
        return static::where('locked_till', '>=', now()->toDateString())
            ->pluck('module')
            ->unique()
            ->toArray();
    }

    public static function getLockSummary()
    {
        return static::with('lockedBy')
            ->where('locked_till', '>=', now()->toDateString())
            ->get()
            ->groupBy('module')
            ->map(function ($locks) {
                return [
                    'count' => $locks->count(),
                    'latest_lock' => $locks->sortByDesc('locked_till')->first(),
                    'total_days' => $locks->sum('days_remaining'),
                ];
            });
    }
}
