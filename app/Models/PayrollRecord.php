<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_period',
        'base_salary',
        'performance_bonus',
        'penalties',
        'net_pay',
        'status',
        'processed_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'payroll_period' => 'date',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
