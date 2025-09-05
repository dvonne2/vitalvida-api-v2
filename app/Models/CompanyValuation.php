<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyValuation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'valuation_date',
        'total_company_value',
        'equity_value',
        'debt_value',
        'cash_value',
        'revenue_multiple',
        'ebitda_multiple',
        'discount_rate',
        'growth_rate',
        'equity_distribution',
        'valuation_methods',
        'assumptions',
        'notes',
        'status',
        'prepared_by',
        'reviewed_by',
        'approved_by',
        'published_at'
    ];

    protected $casts = [
        'valuation_date' => 'date',
        'total_company_value' => 'decimal:2',
        'equity_value' => 'decimal:2',
        'debt_value' => 'decimal:2',
        'cash_value' => 'decimal:2',
        'revenue_multiple' => 'decimal:2',
        'ebitda_multiple' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'growth_rate' => 'decimal:2',
        'equity_distribution' => 'array',
        'valuation_methods' => 'array',
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('valuation_date', 'desc');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('valuation_date', [$startDate, $endDate]);
    }

    // Business Logic Methods
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

    public function calculateEnterpriseValue()
    {
        return $this->total_company_value + $this->debt_value - $this->cash_value;
    }

    public function calculateNetDebt()
    {
        return $this->debt_value - $this->cash_value;
    }

    public function getEquityPercentage()
    {
        if ($this->total_company_value == 0) {
            return 0;
        }

        return round(($this->equity_value / $this->total_company_value) * 100, 2);
    }

    public function getDebtPercentage()
    {
        if ($this->total_company_value == 0) {
            return 0;
        }

        return round(($this->debt_value / $this->total_company_value) * 100, 2);
    }

    public function getCashPercentage()
    {
        if ($this->total_company_value == 0) {
            return 0;
        }

        return round(($this->cash_value / $this->total_company_value) * 100, 2);
    }

    public function getFormattedAmount($amount)
    {
        if ($amount >= 1000000000) {
            return '₦' . number_format($amount / 1000000000, 2) . 'B';
        } elseif ($amount >= 1000000) {
            return '₦' . number_format($amount / 1000000, 2) . 'M';
        } elseif ($amount >= 1000) {
            return '₦' . number_format($amount / 1000, 2) . 'K';
        } else {
            return '₦' . number_format($amount, 2);
        }
    }

    public function getEquityDistributionSummary()
    {
        if (empty($this->equity_distribution)) {
            return [];
        }

        $summary = [];
        foreach ($this->equity_distribution as $stakeholder => $percentage) {
            $summary[] = [
                'stakeholder' => $stakeholder,
                'percentage' => $percentage,
                'value' => $this->equity_value * ($percentage / 100)
            ];
        }

        return $summary;
    }

    public function getValuationMethodsSummary()
    {
        if (empty($this->valuation_methods)) {
            return [];
        }

        return $this->valuation_methods;
    }

    public function isPublished()
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function canBeViewedBy($investor)
    {
        // Check if investor has permission to view valuations
        if (!$investor->canAccessFinancials()) {
            return false;
        }

        // Only published valuations are viewable by investors
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

    public function getSummaryData()
    {
        return [
            'valuation_date' => $this->valuation_date->format('M Y'),
            'total_company_value' => $this->getFormattedAmount($this->total_company_value),
            'equity_value' => $this->getFormattedAmount($this->equity_value),
            'debt_value' => $this->getFormattedAmount($this->debt_value),
            'cash_value' => $this->getFormattedAmount($this->cash_value),
            'enterprise_value' => $this->getFormattedAmount($this->calculateEnterpriseValue()),
            'equity_percentage' => $this->getEquityPercentage() . '%',
            'debt_percentage' => $this->getDebtPercentage() . '%',
            'cash_percentage' => $this->getCashPercentage() . '%',
            'status' => $this->getStatusDisplayName(),
            'revenue_multiple' => $this->revenue_multiple ? $this->revenue_multiple . 'x' : 'N/A',
            'ebitda_multiple' => $this->ebitda_multiple ? $this->ebitda_multiple . 'x' : 'N/A',
            'discount_rate' => $this->discount_rate ? $this->discount_rate . '%' : 'N/A',
            'growth_rate' => $this->growth_rate ? $this->growth_rate . '%' : 'N/A'
        ];
    }

    public function getTrendData($previousValuation = null)
    {
        if (!$previousValuation) {
            return null;
        }

        $valueChange = $this->total_company_value - $previousValuation->total_company_value;
        $percentageChange = $previousValuation->total_company_value > 0 
            ? ($valueChange / $previousValuation->total_company_value) * 100 
            : 0;

        return [
            'value_change' => $this->getFormattedAmount($valueChange),
            'percentage_change' => round($percentageChange, 2),
            'trend' => $valueChange >= 0 ? 'up' : 'down'
        ];
    }
}
