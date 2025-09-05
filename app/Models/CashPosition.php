<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'opening_balance',
        'closing_balance',
        'total_inflow',
        'total_outflow',
        'net_cash_flow',
        'cash_on_hand',
        'bank_balance',
        'pending_receivables',
        'pending_payables',
        'cash_reserves',
        'operating_cash',
        'investment_cash',
        'financing_cash',
        'currency',
        'exchange_rate',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_inflow' => 'decimal:2',
        'total_outflow' => 'decimal:2',
        'net_cash_flow' => 'decimal:2',
        'cash_on_hand' => 'decimal:2',
        'bank_balance' => 'decimal:2',
        'pending_receivables' => 'decimal:2',
        'pending_payables' => 'decimal:2',
        'cash_reserves' => 'decimal:2',
        'operating_cash' => 'decimal:2',
        'investment_cash' => 'decimal:2',
        'financing_cash' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('date', 'desc');
    }

    // Helper methods
    public function getFormattedOpeningBalanceAttribute()
    {
        return '₦' . number_format($this->opening_balance, 2);
    }

    public function getFormattedClosingBalanceAttribute()
    {
        return '₦' . number_format($this->closing_balance, 2);
    }

    public function getFormattedTotalInflowAttribute()
    {
        return '₦' . number_format($this->total_inflow, 2);
    }

    public function getFormattedTotalOutflowAttribute()
    {
        return '₦' . number_format($this->total_outflow, 2);
    }

    public function getFormattedNetCashFlowAttribute()
    {
        return '₦' . number_format($this->net_cash_flow, 2);
    }

    public function getFormattedCashOnHandAttribute()
    {
        return '₦' . number_format($this->cash_on_hand, 2);
    }

    public function getFormattedBankBalanceAttribute()
    {
        return '₦' . number_format($this->bank_balance, 2);
    }

    public function getFormattedPendingReceivablesAttribute()
    {
        return '₦' . number_format($this->pending_receivables, 2);
    }

    public function getFormattedPendingPayablesAttribute()
    {
        return '₦' . number_format($this->pending_payables, 2);
    }

    public function getFormattedCashReservesAttribute()
    {
        return '₦' . number_format($this->cash_reserves, 2);
    }

    public function getCashFlowDirectionAttribute()
    {
        return $this->net_cash_flow >= 0 ? 'positive' : 'negative';
    }

    public function getCashFlowColorAttribute()
    {
        return $this->net_cash_flow >= 0 ? 'green' : 'red';
    }

    public function getLiquidityRatioAttribute()
    {
        if ($this->total_outflow == 0) {
            return 0;
        }

        return ($this->cash_on_hand + $this->bank_balance) / $this->total_outflow;
    }

    public function getWorkingCapitalAttribute()
    {
        return $this->cash_on_hand + $this->bank_balance - $this->pending_payables;
    }

    public function getFormattedWorkingCapitalAttribute()
    {
        return '₦' . number_format($this->working_capital, 2);
    }

    public function getCashBreakdownAttribute()
    {
        return [
            'cash_on_hand' => $this->cash_on_hand,
            'bank_balance' => $this->bank_balance,
            'cash_reserves' => $this->cash_reserves,
            'operating_cash' => $this->operating_cash,
            'investment_cash' => $this->investment_cash,
            'financing_cash' => $this->financing_cash,
        ];
    }

    public function getCashFlowBreakdownAttribute()
    {
        return [
            'operating_cash' => $this->operating_cash,
            'investment_cash' => $this->investment_cash,
            'financing_cash' => $this->financing_cash,
        ];
    }

    // Static methods
    public static function getLatestPosition()
    {
        return static::latest()->first();
    }

    public static function getPositionByDate($date)
    {
        return static::where('date', $date)->first();
    }

    public static function getDailyCashFlow($date)
    {
        return static::where('date', $date)->sum('net_cash_flow');
    }

    public static function getMonthlyCashFlow($year, $month)
    {
        return static::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('net_cash_flow');
    }

    public static function getCashFlowTrend($days = 30)
    {
        return static::where('date', '>=', now()->subDays($days))
            ->groupBy('date')
            ->selectRaw('date, SUM(net_cash_flow) as daily_cash_flow')
            ->orderBy('date')
            ->get();
    }

    public static function getCashPositionSummary()
    {
        $latest = static::getLatestPosition();
        
        if (!$latest) {
            return null;
        }

        return [
            'current_balance' => $latest->closing_balance,
            'cash_on_hand' => $latest->cash_on_hand,
            'bank_balance' => $latest->bank_balance,
            'pending_receivables' => $latest->pending_receivables,
            'pending_payables' => $latest->pending_payables,
            'cash_reserves' => $latest->cash_reserves,
            'working_capital' => $latest->working_capital,
            'liquidity_ratio' => $latest->liquidity_ratio,
            'cash_flow_direction' => $latest->cash_flow_direction,
            'cash_flow_color' => $latest->cash_flow_color,
        ];
    }

    public static function createCashPosition($data)
    {
        return static::create([
            'date' => $data['date'] ?? now()->toDateString(),
            'opening_balance' => $data['opening_balance'] ?? 0,
            'closing_balance' => $data['closing_balance'] ?? 0,
            'total_inflow' => $data['total_inflow'] ?? 0,
            'total_outflow' => $data['total_outflow'] ?? 0,
            'net_cash_flow' => $data['net_cash_flow'] ?? 0,
            'cash_on_hand' => $data['cash_on_hand'] ?? 0,
            'bank_balance' => $data['bank_balance'] ?? 0,
            'pending_receivables' => $data['pending_receivables'] ?? 0,
            'pending_payables' => $data['pending_payables'] ?? 0,
            'cash_reserves' => $data['cash_reserves'] ?? 0,
            'operating_cash' => $data['operating_cash'] ?? 0,
            'investment_cash' => $data['investment_cash'] ?? 0,
            'financing_cash' => $data['financing_cash'] ?? 0,
            'currency' => $data['currency'] ?? 'NGN',
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }
} 