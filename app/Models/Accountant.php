<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Accountant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'full_name', 'email', 'phone_number', 'department',
        'role', 'status', 'hire_date', 'current_strikes', 'total_penalties', 'user_id'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'current_strikes' => 'integer',
        'total_penalties' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentRecords()
    {
        return $this->hasMany(PaymentRecord::class, 'processed_by');
    }

    public function strikeRecords()
    {
        return $this->hasMany(StrikeRecord::class, 'accountant_id');
    }

    public function bonusTracking()
    {
        return $this->hasMany(BonusTracking::class, 'accountant_id');
    }

    public function expenseRequests()
    {
        return $this->hasMany(ExpenseRequest::class, 'requested_by');
    }

    public function escalationRequests()
    {
        return $this->hasMany(EscalationRequest::class, 'submitted_by');
    }

    public function complianceRecords()
    {
        return $this->hasMany(SystemCompliance::class, 'accountant_id');
    }

    public function dailyProgress()
    {
        return $this->hasMany(DailyProgressTracking::class, 'accountant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithStrikes($query)
    {
        return $query->where('current_strikes', '>', 0);
    }

    public function scopeEligibleForBonus($query)
    {
        return $query->where('current_strikes', '<', 3);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Methods
    public function generateEmployeeId()
    {
        $lastAccountant = self::orderBy('id', 'desc')->first();
        $number = $lastAccountant ? intval(substr($lastAccountant->employee_id, 3)) + 1 : 1;
        return 'ACC' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function addStrike($violationType, $description, $penaltyAmount, $orderId = null, $evidence = [])
    {
        $strikeNumber = $this->current_strikes + 1;
        
        $strike = StrikeRecord::create([
            'accountant_id' => $this->id,
            'strike_number' => $strikeNumber,
            'violation_type' => $violationType,
            'violation_description' => $description,
            'penalty_amount' => $penaltyAmount,
            'order_id' => $orderId,
            'evidence' => $evidence,
            'issued_date' => now()->toDateString()
        ]);

        $this->increment('current_strikes');
        $this->increment('total_penalties', $penaltyAmount);

        // Check for termination threshold
        if ($this->current_strikes >= 5) {
            $this->update(['status' => 'suspended']);
        }

        return $strike;
    }

    public function getCurrentWeekBonus()
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        
        return $this->bonusTracking()
                   ->where('week_start_date', $weekStart->toDateString())
                   ->where('week_end_date', $weekEnd->toDateString())
                   ->first();
    }

    public function calculateWeeklyBonus()
    {
        $bonus = $this->getCurrentWeekBonus();
        if (!$bonus) {
            $bonus = BonusTracking::create([
                'accountant_id' => $this->id,
                'week_start_date' => now()->startOfWeek()->toDateString(),
                'week_end_date' => now()->endOfWeek()->toDateString(),
                'goal_amount' => 10000.00
            ]);
        }

        // Calculate criteria scores
        $paymentAccuracy = $this->calculatePaymentMatchingAccuracy();
        $escalationDiscipline = $this->calculateEscalationDiscipline();
        $documentationIntegrity = $this->calculateDocumentationIntegrity();
        $bonusLogAccuracy = $this->calculateBonusLogAccuracy();

        $criteriaMet = 0;
        if ($paymentAccuracy >= 98) $criteriaMet++;
        if ($escalationDiscipline >= 100) $criteriaMet++;
        if ($documentationIntegrity >= 100) $criteriaMet++;
        if ($bonusLogAccuracy >= 100) $criteriaMet++;

        $bonus->update([
            'criteria_met' => $criteriaMet,
            'payment_matching_accuracy' => $paymentAccuracy,
            'escalation_discipline_score' => $escalationDiscipline,
            'documentation_integrity_score' => $documentationIntegrity,
            'bonus_log_accuracy' => $bonusLogAccuracy,
            'bonus_status' => $criteriaMet >= 4 && $this->current_strikes === 0 ? 'eligible' : 'not_eligible'
        ]);

        return $bonus;
    }

    private function calculatePaymentMatchingAccuracy()
    {
        $totalPayments = $this->paymentRecords()
                             ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                             ->count();
        
        if ($totalPayments === 0) return 100;
        
        $correctPayments = $this->paymentRecords()
                               ->where('verification_status', '3_way_match')
                               ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                               ->count();
        
        return round(($correctPayments / $totalPayments) * 100, 2);
    }

    private function calculateEscalationDiscipline()
    {
        // Check if any unauthorized payments were made over thresholds
        $violations = $this->strikeRecords()
                          ->where('violation_type', 'payment_mismatch')
                          ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                          ->count();
        
        return $violations === 0 ? 100 : 0;
    }

    private function calculateDocumentationIntegrity()
    {
        $requiredReceipts = $this->paymentRecords()
                                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                                ->count();
        
        if ($requiredReceipts === 0) return 100;
        
        $uploadedReceipts = $this->paymentRecords()
                                ->where('receipt_uploaded', true)
                                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                                ->count();
        
        return round(($uploadedReceipts / $requiredReceipts) * 100, 2);
    }

    private function calculateBonusLogAccuracy()
    {
        // Check if all bonuses were pre-approved by FC
        $bonusPayments = BonusTracking::where('accountant_id', $this->id)
                                     ->where('bonus_status', 'paid')
                                     ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                                     ->count();
        
        if ($bonusPayments === 0) return 100;
        
        $preApprovedBonuses = BonusTracking::where('accountant_id', $this->id)
                                         ->where('bonus_status', 'paid')
                                         ->where('fc_approved', true)
                                         ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                                         ->count();
        
        return round(($preApprovedBonuses / $bonusPayments) * 100, 2);
    }

    public function isEligibleForWeeklyBonus()
    {
        return $this->current_strikes === 0 && $this->status === 'active';
    }

    public function getComplianceScore()
    {
        $latestCompliance = $this->complianceRecords()
                                ->latest('compliance_date')
                                ->first();
        
        return $latestCompliance ? $latestCompliance->overall_compliance_score : 0;
    }

    public function getTodayTasks()
    {
        return $this->dailyProgress()
                   ->where('task_date', now()->toDateString())
                   ->get();
    }

    public function getPendingExpenses()
    {
        return $this->expenseRequests()
                   ->where('approval_status', 'pending')
                   ->get();
    }

    public function getPendingEscalations()
    {
        return $this->escalationRequests()
                   ->where('escalation_status', 'like', 'pending%')
                   ->get();
    }

    public function canApproveExpenses()
    {
        return in_array($this->role, ['financial_controller', 'ceo']);
    }

    public function canApproveEscalations()
    {
        return in_array($this->role, ['financial_controller', 'ceo']);
    }

    public function getRoleDisplayName()
    {
        return match($this->role) {
            'accountant' => 'Accountant',
            'financial_controller' => 'Financial Controller',
            'ceo' => 'CEO',
            default => 'Unknown'
        };
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'active' => 'green',
            'suspended' => 'red',
            'terminated' => 'gray',
            default => 'yellow'
        };
    }
} 