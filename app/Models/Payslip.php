<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_name',
        'employee_role',
        'employee_department',
        'payroll_id',
        'pay_period_month',
        'payslip_number',
        'base_salary',
        'prorated_salary',
        'total_bonuses',
        'bonus_details',
        'total_deductions',
        'deduction_details',
        'gross_pay',
        'net_pay',
        'working_days',
        'employee_working_days',
        'generated_by',
        'generated_at'
    ];

    protected $casts = [
        'pay_period_month' => 'date',
        'base_salary' => 'decimal:2',
        'prorated_salary' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
        'bonus_details' => 'array',
        'total_deductions' => 'decimal:2',
        'deduction_details' => 'array',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'generated_at' => 'datetime'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
} 