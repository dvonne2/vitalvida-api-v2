<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_type',
        'period',
        'taxable_amount',
        'tax_rate',
        'tax_amount',
        'status',
        'due_date',
        'filed_date',
        'paid_date',
        'penalty_amount',
    ];

    protected $casts = [
        'taxable_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'due_date' => 'date',
        'filed_date' => 'date',
        'paid_date' => 'date',
    ];

    // Scopes
    public function scopeByType($query, $taxType)
    {
        return $query->where('tax_type', $taxType);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereIn('status', ['calculated', 'filed']);
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('due_date', '<=', now()->addDays($days)->toDateString())
            ->whereIn('status', ['calculated', 'filed']);
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    // Accessors
    public function getFormattedTaxableAmountAttribute()
    {
        return '₦' . number_format($this->taxable_amount, 2);
    }

    public function getFormattedTaxAmountAttribute()
    {
        return '₦' . number_format($this->tax_amount, 2);
    }

    public function getFormattedPenaltyAmountAttribute()
    {
        return '₦' . number_format($this->penalty_amount, 2);
    }

    public function getFormattedTaxRateAttribute()
    {
        return $this->tax_rate . '%';
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'calculated' => 'text-blue-600',
            'filed' => 'text-yellow-600',
            'paid' => 'text-green-600',
            'overdue' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'calculated' => '📊',
            'filed' => '📤',
            'paid' => '✅',
            'overdue' => '⚠️',
            default => '📄',
        };
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date < now()->toDateString() && 
               in_array($this->status, ['calculated', 'filed']);
    }

    public function getDaysUntilDueAttribute()
    {
        return now()->diffInDays($this->due_date, false);
    }

    public function getDaysOverdueAttribute()
    {
        if ($this->is_overdue) {
            return now()->diffInDays($this->due_date);
        }
        return 0;
    }

    public function getTotalAmountDueAttribute()
    {
        return $this->tax_amount + $this->penalty_amount;
    }

    public function getFormattedTotalAmountDueAttribute()
    {
        return '₦' . number_format($this->total_amount_due, 2);
    }

    // Methods
    public function calculateTax()
    {
        $this->tax_amount = ($this->taxable_amount * $this->tax_rate) / 100;
        $this->save();
    }

    public function markAsFiled()
    {
        $this->status = 'filed';
        $this->filed_date = now()->toDateString();
        $this->save();
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->paid_date = now()->toDateString();
        $this->save();
    }

    public function calculatePenalty()
    {
        if ($this->is_overdue) {
            $daysOverdue = $this->days_overdue;
            $penaltyRate = 0.05; // 5% penalty per month
            
            $monthsOverdue = ceil($daysOverdue / 30);
            $this->penalty_amount = $this->tax_amount * $penaltyRate * $monthsOverdue;
            $this->save();
        }
    }

    public function updateStatus()
    {
        if ($this->is_overdue && $this->status !== 'paid') {
            $this->status = 'overdue';
            $this->calculatePenalty();
        }
        $this->save();
    }

    // Static methods
    public static function calculateVAT($revenue, $period)
    {
        $taxRate = 7.5; // Nigerian VAT rate
        $taxableAmount = $revenue;
        $taxAmount = ($taxableAmount * $taxRate) / 100;
        $dueDate = self::getVATDueDate($period);

        return static::create([
            'tax_type' => 'VAT',
            'period' => $period,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'due_date' => $dueDate,
        ]);
    }

    public static function calculatePAYE($totalSalaries, $period)
    {
        $taxRate = 30; // Simplified PAYE rate
        $taxableAmount = $totalSalaries;
        $taxAmount = ($taxableAmount * $taxRate) / 100;
        $dueDate = self::getPAYEDueDate($period);

        return static::create([
            'tax_type' => 'PAYE',
            'period' => $period,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'due_date' => $dueDate,
        ]);
    }

    public static function calculateCIT($taxableProfit, $period)
    {
        $taxRate = 30; // Nigerian CIT rate
        $taxableAmount = $taxableProfit;
        $taxAmount = ($taxableAmount * $taxRate) / 100;
        $dueDate = self::getCITDueDate($period);

        return static::create([
            'tax_type' => 'CIT',
            'period' => $period,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'due_date' => $dueDate,
        ]);
    }

    public static function calculateEducationTax($taxableProfit, $period)
    {
        $taxRate = 2; // Nigerian Education Tax rate
        $taxableAmount = $taxableProfit;
        $taxAmount = ($taxableAmount * $taxRate) / 100;
        $dueDate = self::getEDTDueDate($period);

        return static::create([
            'tax_type' => 'EDT',
            'period' => $period,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'due_date' => $dueDate,
        ]);
    }

    public static function calculateWHT($amount, $type, $period)
    {
        $taxRates = [
            'contract' => 5,
            'rent' => 10,
            'professional' => 10,
            'dividend' => 10,
        ];

        $taxRate = $taxRates[$type] ?? 5;
        $taxAmount = ($amount * $taxRate) / 100;
        $dueDate = self::getWHTDueDate($period);

        return static::create([
            'tax_type' => 'WHT',
            'period' => $period,
            'taxable_amount' => $amount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'due_date' => $dueDate,
        ]);
    }

    // Due date calculation methods
    private static function getVATDueDate($period)
    {
        $date = Carbon::parse($period . '-01');
        return $date->addMonth()->day(21);
    }

    private static function getPAYEDueDate($period)
    {
        $date = Carbon::parse($period . '-01');
        return $date->addMonth()->day(10);
    }

    private static function getCITDueDate($period)
    {
        $year = Carbon::parse($period . '-01')->year;
        return Carbon::create($year + 1, 3, 31);
    }

    private static function getEDTDueDate($period)
    {
        $year = Carbon::parse($period . '-01')->year;
        return Carbon::create($year + 1, 3, 31);
    }

    private static function getWHTDueDate($period)
    {
        $date = Carbon::parse($period . '-01');
        return $date->addMonth()->day(21);
    }

    public static function getOverdueTaxes()
    {
        return static::overdue()->get();
    }

    public static function getDueSoonTaxes($days = 7)
    {
        return static::dueSoon($days)->get();
    }

    public static function getTaxSummary()
    {
        return static::selectRaw('
            tax_type,
            COUNT(*) as total_calculations,
            SUM(tax_amount) as total_tax_amount,
            SUM(penalty_amount) as total_penalty_amount,
            COUNT(CASE WHEN status = "paid" THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = "overdue" THEN 1 END) as overdue_count
        ')
        ->groupBy('tax_type')
        ->get();
    }
}
