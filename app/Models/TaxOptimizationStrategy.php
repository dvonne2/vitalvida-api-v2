<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxOptimizationStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_name',
        'description',
        'potential_savings',
        'implementation_status',
        'difficulty_level',
        'deadline',
    ];

    protected $casts = [
        'potential_savings' => 'decimal:2',
        'deadline' => 'date',
    ];

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('implementation_status', $status);
    }

    public function scopeAvailable($query)
    {
        return $query->where('implementation_status', 'available');
    }

    public function scopeImplemented($query)
    {
        return $query->where('implementation_status', 'implemented');
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    public function scopeHighImpact($query)
    {
        return $query->where('potential_savings', '>=', 1000000);
    }

    public function scopeLowDifficulty($query)
    {
        return $query->where('difficulty_level', 'low');
    }

    // Accessors
    public function getFormattedPotentialSavingsAttribute()
    {
        return '₦' . number_format($this->potential_savings, 2);
    }

    public function getStatusColorAttribute()
    {
        return match($this->implementation_status) {
            'available' => 'text-green-600',
            'implemented' => 'text-blue-600',
            'not_applicable' => 'text-gray-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->implementation_status) {
            'available' => '✅',
            'implemented' => '🎯',
            'not_applicable' => '❌',
            default => '📄',
        };
    }

    public function getDifficultyColorAttribute()
    {
        return match($this->difficulty_level) {
            'low' => 'text-green-600',
            'medium' => 'text-yellow-600',
            'high' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getDifficultyIconAttribute()
    {
        return match($this->difficulty_level) {
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🔴',
            default => '⚪',
        };
    }

    public function getIsOverdueAttribute()
    {
        return $this->deadline && $this->deadline < now()->toDateString() && 
               $this->implementation_status === 'available';
    }

    public function getDaysUntilDeadlineAttribute()
    {
        if (!$this->deadline) return null;
        return now()->diffInDays($this->deadline, false);
    }

    public function getPriorityScoreAttribute()
    {
        $score = 0;
        
        // Higher savings = higher priority
        if ($this->potential_savings >= 2000000) $score += 5;
        elseif ($this->potential_savings >= 1000000) $score += 3;
        elseif ($this->potential_savings >= 500000) $score += 2;
        else $score += 1;

        // Lower difficulty = higher priority
        if ($this->difficulty_level === 'low') $score += 3;
        elseif ($this->difficulty_level === 'medium') $score += 2;
        else $score += 1;

        // Urgency based on deadline
        if ($this->deadline && $this->days_until_deadline < 30) $score += 2;
        elseif ($this->deadline && $this->days_until_deadline < 90) $score += 1;

        return $score;
    }

    // Methods
    public function implement()
    {
        $this->implementation_status = 'implemented';
        $this->save();
    }

    public function markAsNotApplicable()
    {
        $this->implementation_status = 'not_applicable';
        $this->save();
    }

    public function resetToAvailable()
    {
        $this->implementation_status = 'available';
        $this->save();
    }

    // Static methods
    public static function getAvailableStrategies()
    {
        return static::available()
            ->orderBy('potential_savings', 'desc')
            ->get();
    }

    public static function getHighImpactStrategies()
    {
        return static::highImpact()
            ->where('implementation_status', 'available')
            ->orderBy('potential_savings', 'desc')
            ->get();
    }

    public static function getLowDifficultyStrategies()
    {
        return static::lowDifficulty()
            ->where('implementation_status', 'available')
            ->orderBy('potential_savings', 'desc')
            ->get();
    }

    public static function getTotalPotentialSavings()
    {
        return static::where('implementation_status', 'available')
            ->sum('potential_savings');
    }

    public static function getImplementedSavings()
    {
        return static::where('implementation_status', 'implemented')
            ->sum('potential_savings');
    }

    public static function getOptimizationSummary()
    {
        $totalAvailable = static::available()->sum('potential_savings');
        $totalImplemented = static::implemented()->sum('potential_savings');
        $totalNotApplicable = static::where('implementation_status', 'not_applicable')->sum('potential_savings');

        return [
            'total_available' => $totalAvailable,
            'total_implemented' => $totalImplemented,
            'total_not_applicable' => $totalNotApplicable,
            'implementation_rate' => $totalAvailable > 0 ? ($totalImplemented / $totalAvailable) * 100 : 0,
            'formatted_available' => '₦' . number_format($totalAvailable, 2),
            'formatted_implemented' => '₦' . number_format($totalImplemented, 2),
            'formatted_not_applicable' => '₦' . number_format($totalNotApplicable, 2),
        ];
    }

    public static function getPriorityStrategies($limit = 10)
    {
        return static::available()
            ->get()
            ->sortByDesc('priority_score')
            ->take($limit);
    }

    public static function getOverdueStrategies()
    {
        return static::where('deadline', '<', now()->toDateString())
            ->where('implementation_status', 'available')
            ->get();
    }

    public static function getDueSoonStrategies($days = 30)
    {
        return static::where('deadline', '<=', now()->addDays($days)->toDateString())
            ->where('deadline', '>=', now()->toDateString())
            ->where('implementation_status', 'available')
            ->get();
    }
}
