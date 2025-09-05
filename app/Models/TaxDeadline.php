<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxDeadline extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_type',
        'filing_frequency',
        'due_day',
        'due_month',
        'description',
        'is_active',
    ];

    protected $casts = [
        'due_day' => 'integer',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeByType($query, $taxType)
    {
        return $query->where('tax_type', $taxType);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('filing_frequency', $frequency);
    }

    public function scopeMonthly($query)
    {
        return $query->where('filing_frequency', 'monthly');
    }

    public function scopeAnnual($query)
    {
        return $query->where('filing_frequency', 'annual');
    }

    // Accessors
    public function getFormattedDueDayAttribute()
    {
        $suffix = match($this->due_day) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
        
        return $this->due_day . $suffix;
    }

    public function getNextDueDateAttribute()
    {
        if ($this->filing_frequency === 'monthly') {
            $nextMonth = now()->addMonth();
            return Carbon::create($nextMonth->year, $nextMonth->month, $this->due_day);
        } else {
            // Annual taxes
            $currentYear = now()->year;
            if ($this->due_month === '3_months_after_year_end') {
                return Carbon::create($currentYear + 1, 3, 31);
            }
            return Carbon::create($currentYear + 1, 3, 31); // Default to March 31st
        }
    }

    public function getDaysUntilDueAttribute()
    {
        return now()->diffInDays($this->next_due_date, false);
    }

    public function getIsOverdueAttribute()
    {
        return $this->next_due_date < now()->toDateString();
    }

    public function getStatusColorAttribute()
    {
        if ($this->is_overdue) {
            return 'text-red-600';
        } elseif ($this->days_until_due <= 7) {
            return 'text-orange-600';
        } elseif ($this->days_until_due <= 30) {
            return 'text-yellow-600';
        } else {
            return 'text-green-600';
        }
    }

    public function getStatusIconAttribute()
    {
        if ($this->is_overdue) {
            return '⚠️';
        } elseif ($this->days_until_due <= 7) {
            return '🚨';
        } elseif ($this->days_until_due <= 30) {
            return '⏰';
        } else {
            return '✅';
        }
    }

    public function getTaxTypeIconAttribute()
    {
        return match($this->tax_type) {
            'VAT' => '🏛️',
            'PAYE' => '👥',
            'WHT' => '💼',
            'CIT' => '🏢',
            'EDT' => '🎓',
            default => '📄',
        };
    }

    public function getFrequencyTextAttribute()
    {
        return ucfirst($this->filing_frequency);
    }

    // Methods
    public function getDueDateForPeriod($period)
    {
        if ($this->filing_frequency === 'monthly') {
            $date = Carbon::parse($period . '-01');
            return $date->addMonth()->day($this->due_day);
        } else {
            // Annual taxes
            $year = Carbon::parse($period . '-01')->year;
            if ($this->due_month === '3_months_after_year_end') {
                return Carbon::create($year + 1, 3, 31);
            }
            return Carbon::create($year + 1, 3, 31);
        }
    }

    public function isDueSoon($days = 30)
    {
        return $this->days_until_due <= $days && $this->days_until_due >= 0;
    }

    public function getReminderMessage()
    {
        if ($this->is_overdue) {
            return "{$this->tax_type} filing is overdue by " . abs($this->days_until_due) . " days";
        } elseif ($this->days_until_due <= 7) {
            return "{$this->tax_type} filing is due in {$this->days_until_due} days";
        } elseif ($this->days_until_due <= 30) {
            return "{$this->tax_type} filing is due in {$this->days_until_due} days";
        } else {
            return "{$this->tax_type} filing is due in {$this->days_until_due} days";
        }
    }

    // Static methods
    public static function getUpcomingDeadlines($days = 30)
    {
        return static::active()
            ->get()
            ->filter(function ($deadline) use ($days) {
                return $deadline->isDueSoon($days);
            })
            ->sortBy('days_until_due');
    }

    public static function getOverdueDeadlines()
    {
        return static::active()
            ->get()
            ->filter(function ($deadline) {
                return $deadline->is_overdue;
            })
            ->sortBy('days_until_due');
    }

    public static function getDeadlinesByType($taxType)
    {
        return static::active()
            ->where('tax_type', $taxType)
            ->get();
    }

    public static function getMonthlyDeadlines()
    {
        return static::active()
            ->monthly()
            ->get();
    }

    public static function getAnnualDeadlines()
    {
        return static::active()
            ->annual()
            ->get();
    }

    public static function getDeadlineSummary()
    {
        $deadlines = static::active()->get();
        
        $summary = [];
        foreach ($deadlines as $deadline) {
            $summary[$deadline->tax_type] = [
                'next_due_date' => $deadline->next_due_date->format('Y-m-d'),
                'days_until_due' => $deadline->days_until_due,
                'is_overdue' => $deadline->is_overdue,
                'status_color' => $deadline->status_color,
                'status_icon' => $deadline->status_icon,
                'reminder_message' => $deadline->getReminderMessage(),
            ];
        }

        return $summary;
    }

    public static function getCriticalDeadlines()
    {
        return static::active()
            ->get()
            ->filter(function ($deadline) {
                return $deadline->days_until_due <= 7 || $deadline->is_overdue;
            })
            ->sortBy('days_until_due');
    }
}
