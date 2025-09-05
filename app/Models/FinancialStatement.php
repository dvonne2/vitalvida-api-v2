<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialStatement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'period_start',
        'period_end',
        'period_name',
        'revenue',
        'cost_of_goods_sold',
        'gross_profit',
        'operating_expenses',
        'operating_income',
        'net_income',
        'total_assets',
        'total_liabilities',
        'total_equity',
        'operating_cash_flow',
        'investing_cash_flow',
        'financing_cash_flow',
        'net_cash_flow',
        'cash_balance',
        'additional_metrics',
        'breakdown',
        'notes',
        'status',
        'prepared_by',
        'reviewed_by',
        'approved_by',
        'published_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'revenue' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'operating_expenses' => 'decimal:2',
        'operating_income' => 'decimal:2',
        'net_income' => 'decimal:2',
        'total_assets' => 'decimal:2',
        'total_liabilities' => 'decimal:2',
        'total_equity' => 'decimal:2',
        'operating_cash_flow' => 'decimal:2',
        'investing_cash_flow' => 'decimal:2',
        'financing_cash_flow' => 'decimal:2',
        'net_cash_flow' => 'decimal:2',
        'cash_balance' => 'decimal:2',
        'additional_metrics' => 'array',
        'breakdown' => 'array',
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Type constants
    const TYPE_PROFIT_LOSS = 'profit_loss';
    const TYPE_BALANCE_SHEET = 'balance_sheet';
    const TYPE_CASH_FLOW = 'cash_flow';

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_APPROVED = 'approved';
    const STATUS_PUBLISHED = 'published';

    // Relationships
    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('period_start', [$startDate, $endDate]);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('period_end', 'desc');
    }

    // Business Logic Methods
    public function getTypeDisplayName()
    {
        $typeNames = [
            self::TYPE_PROFIT_LOSS => 'Profit & Loss',
            self::TYPE_BALANCE_SHEET => 'Balance Sheet',
            self::TYPE_CASH_FLOW => 'Cash Flow'
        ];

        return $typeNames[$this->type] ?? $this->type;
    }

    public function getStatusDisplayName()
    {
        $statusNames = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_REVIEWED => 'Reviewed',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PUBLISHED => 'Published'
        ];

        return $statusNames[$this->status] ?? $this->status;
    }

    public function calculateGrossProfit()
    {
        return $this->revenue - $this->cost_of_goods_sold;
    }

    public function calculateOperatingIncome()
    {
        return $this->gross_profit - $this->operating_expenses;
    }

    public function calculateNetIncome()
    {
        return $this->operating_income;
    }

    public function calculateNetCashFlow()
    {
        return $this->operating_cash_flow + $this->investing_cash_flow + $this->financing_cash_flow;
    }

    public function getGrossProfitMargin()
    {
        if ($this->revenue == 0) {
            return 0;
        }

        return round(($this->gross_profit / $this->revenue) * 100, 2);
    }

    public function getOperatingMargin()
    {
        if ($this->revenue == 0) {
            return 0;
        }

        return round(($this->operating_income / $this->revenue) * 100, 2);
    }

    public function getNetProfitMargin()
    {
        if ($this->revenue == 0) {
            return 0;
        }

        return round(($this->net_income / $this->revenue) * 100, 2);
    }

    public function getCurrentRatio()
    {
        // This would need current assets and current liabilities from breakdown
        $currentAssets = $this->breakdown['current_assets'] ?? 0;
        $currentLiabilities = $this->breakdown['current_liabilities'] ?? 0;

        if ($currentLiabilities == 0) {
            return 0;
        }

        return round($currentAssets / $currentLiabilities, 2);
    }

    public function getDebtToEquityRatio()
    {
        if ($this->total_equity == 0) {
            return 0;
        }

        return round($this->total_liabilities / $this->total_equity, 2);
    }

    public function isPublished()
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function canBeViewedBy($investor)
    {
        // Check if investor has permission to view financial statements
        if (!$investor->canAccessFinancials()) {
            return false;
        }

        // Only published statements are viewable by investors
        return $this->isPublished();
    }

    public function publish()
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now()
        ]);
    }

    public function approve($approvedBy)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedBy->id
        ]);
    }

    public function review($reviewedBy)
    {
        $this->update([
            'status' => self::STATUS_REVIEWED,
            'reviewed_by' => $reviewedBy->id
        ]);
    }

    public function getFormattedAmount($amount)
    {
        return '₦' . number_format($amount, 2);
    }

    public function getSummaryData()
    {
        return [
            'period' => $this->period_name,
            'type' => $this->getTypeDisplayName(),
            'status' => $this->getStatusDisplayName(),
            'revenue' => $this->getFormattedAmount($this->revenue),
            'net_income' => $this->getFormattedAmount($this->net_income),
            'gross_profit_margin' => $this->getGrossProfitMargin() . '%',
            'operating_margin' => $this->getOperatingMargin() . '%',
            'net_profit_margin' => $this->getNetProfitMargin() . '%',
            'cash_balance' => $this->getFormattedAmount($this->cash_balance),
            'total_assets' => $this->getFormattedAmount($this->total_assets),
            'total_equity' => $this->getFormattedAmount($this->total_equity)
        ];
    }
}
