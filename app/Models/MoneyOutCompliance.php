<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MoneyOutCompliance extends Model
{
    use HasFactory;

    protected $table = 'money_out_compliance';
    
    protected $fillable = [
        'order_id',
        'delivery_agent_id',
        'amount',
        'payment_verified',
        'otp_submitted',
        'friday_photo_approved',
        'three_way_match',
        'compliance_status',
        'proof_of_payment_path',
        'paid_at',
        'paid_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_verified' => 'boolean',
        'otp_submitted' => 'boolean',
        'friday_photo_approved' => 'boolean',
        'three_way_match' => 'boolean',
        'paid_at' => 'datetime'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function fileUploads(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'uploadable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // Business Logic Methods
    
    /**
     * Check if compliance is ready for payment (all criteria met)
     */
    public function isReadyForPayment(): bool
    {
        return $this->payment_verified && 
               $this->otp_submitted && 
               $this->friday_photo_approved &&
               $this->three_way_match;
    }

    /**
     * Calculate compliance score (0-100%)
     */
    public function calculateComplianceScore(): float
    {
        $score = 0;
        if ($this->payment_verified) $score += 25;
        if ($this->otp_submitted) $score += 25;
        if ($this->friday_photo_approved) $score += 25;
        if ($this->three_way_match) $score += 25;
        return $score;
    }

    /**
     * Auto-lock compliance if all criteria are met
     */
    public function lockCompliance(): bool
    {
        if ($this->isReadyForPayment() && $this->compliance_status === 'ready') {
            $this->update(['compliance_status' => 'locked']);
            
            // Log the auto-lock event
            $this->auditLogs()->create([
                'event_type' => 'auto_lock',
                'user_id' => auth()->id(),
                'new_values' => ['compliance_status' => 'locked'],
                'metadata' => ['reason' => 'All compliance criteria met']
            ]);
            
            return true;
        }
        return false;
    }

    /**
     * Verify payment (first step of three-way verification)
     */
    public function verifyPayment(): bool
    {
        if (!$this->payment_verified) {
            $this->update(['payment_verified' => true]);
            $this->checkAndUpdateThreeWayMatch();
            return true;
        }
        return false;
    }

    /**
     * Submit OTP (second step of three-way verification)
     */
    public function submitOtp(): bool
    {
        if (!$this->otp_submitted) {
            $this->update(['otp_submitted' => true]);
            $this->checkAndUpdateThreeWayMatch();
            return true;
        }
        return false;
    }

    /**
     * Approve Friday photo (third step of three-way verification)
     */
    public function approveFridayPhoto(): bool
    {
        if (!$this->friday_photo_approved) {
            $this->update(['friday_photo_approved' => true]);
            $this->checkAndUpdateThreeWayMatch();
            return true;
        }
        return false;
    }

    /**
     * Check and update three-way match status
     */
    private function checkAndUpdateThreeWayMatch(): void
    {
        $threeWayMatch = $this->payment_verified && 
                        $this->otp_submitted && 
                        $this->friday_photo_approved;
        
        if ($threeWayMatch && !$this->three_way_match) {
            $this->update(['three_way_match' => true]);
            
            // Auto-lock if all criteria are now met
            $this->lockCompliance();
        }
    }

    /**
     * Mark as paid (final step)
     */
    public function markAsPaid(int $userId): bool
    {
        if ($this->compliance_status === 'locked' && $this->proof_of_payment_path) {
            $this->update([
                'compliance_status' => 'paid',
                'paid_at' => now(),
                'paid_by' => $userId
            ]);
            
            // Log payment completion
            $this->auditLogs()->create([
                'event_type' => 'payment_completed',
                'user_id' => $userId,
                'new_values' => [
                    'compliance_status' => 'paid',
                    'paid_at' => now(),
                    'paid_by' => $userId
                ],
                'metadata' => ['amount' => $this->amount]
            ]);
            
            return true;
        }
        return false;
    }

    /**
     * Get compliance status with human-readable description
     */
    public function getComplianceStatusDescription(): string
    {
        return match($this->compliance_status) {
            'ready' => 'Awaiting verification completion',
            'locked' => 'Ready for payment - all criteria met',
            'paid' => 'Payment completed',
            default => 'Unknown status'
        };
    }

    /**
     * Get missing compliance criteria
     */
    public function getMissingCriteria(): array
    {
        $missing = [];
        
        if (!$this->payment_verified) {
            $missing[] = 'Payment verification';
        }
        
        if (!$this->otp_submitted) {
            $missing[] = 'OTP submission';
        }
        
        if (!$this->friday_photo_approved) {
            $missing[] = 'Friday photo approval';
        }
        
        return $missing;
    }

    /**
     * Check if compliance is overdue (older than 48 hours without completion)
     */
    public function isOverdue(): bool
    {
        if ($this->compliance_status === 'paid') {
            return false;
        }
        
        return $this->created_at->diffInHours(now()) > 48;
    }

    /**
     * Get compliance priority level
     */
    public function getPriorityLevel(): string
    {
        if ($this->isOverdue()) {
            return 'urgent';
        }
        
        if ($this->compliance_status === 'locked') {
            return 'high';
        }
        
        $score = $this->calculateComplianceScore();
        if ($score >= 75) {
            return 'medium';
        }
        
        return 'low';
    }

    // Scopes
    public function scopeReady($query)
    {
        return $query->where('compliance_status', 'ready');
    }

    public function scopeLocked($query)
    {
        return $query->where('compliance_status', 'locked');
    }

    public function scopePaid($query)
    {
        return $query->where('compliance_status', 'paid');
    }

    public function scopeReadyForPayment($query)
    {
        return $query->where('payment_verified', true)
                    ->where('otp_submitted', true)
                    ->where('friday_photo_approved', true)
                    ->where('three_way_match', true);
    }

    public function scopeOverdue($query)
    {
        return $query->where('compliance_status', '!=', 'paid')
                    ->where('created_at', '<', now()->subHours(48));
    }
}
