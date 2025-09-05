<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentGuarantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'guarantor_type', 'full_name', 'email', 'phone_number',
        'organization', 'position', 'employee_id', 'address', 'city', 'state',
        'postal_code', 'verification_status', 'verification_code', 'verification_code_sent_at',
        'verified_at', 'rejected_at', 'rejection_reason', 'verification_attempts',
        'last_verification_attempt', 'relationship', 'relationship_details', 'years_known',
        'guarantor_score', 'is_primary_guarantor'
    ];

    protected $casts = [
        'verification_code_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'last_verification_attempt' => 'datetime',
        'guarantor_score' => 'decimal:2',
        'is_primary_guarantor' => 'boolean'
    ];

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function getGuarantorTypeTextAttribute()
    {
        return match($this->guarantor_type) {
            'bank_staff' => 'Bank Staff',
            'civil_servant' => 'Civil Servant',
            'business_owner' => 'Business Owner',
            'professional' => 'Professional',
            default => 'Unknown'
        };
    }

    public function getVerificationStatusTextAttribute()
    {
        return match($this->verification_status) {
            'pending' => 'Pending Verification',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            default => 'Unknown Status'
        };
    }

    public function getVerificationStatusColorAttribute()
    {
        return match($this->verification_status) {
            'verified' => 'green',
            'pending' => 'yellow',
            'rejected' => 'red',
            'expired' => 'orange',
            default => 'gray'
        };
    }

    public function getRelationshipTextAttribute()
    {
        return match($this->relationship) {
            'family' => 'Family Member',
            'friend' => 'Friend',
            'colleague' => 'Colleague',
            'employer' => 'Employer',
            'other' => 'Other',
            default => 'Not specified'
        };
    }

    public function generateVerificationCode()
    {
        $code = strtoupper(Str::random(6));
        $this->update([
            'verification_code' => $code,
            'verification_code_sent_at' => now(),
            'verification_attempts' => 0
        ]);
        
        return $code;
    }

    public function verifyCode($code)
    {
        if ($this->verification_status !== 'pending') {
            return false;
        }

        if ($this->verification_code !== strtoupper($code)) {
            $this->increment('verification_attempts');
            $this->update(['last_verification_attempt' => now()]);
            return false;
        }

        // Check if code is expired (24 hours)
        if ($this->verification_code_sent_at->diffInHours(now()) > 24) {
            $this->update(['verification_status' => 'expired']);
            return false;
        }

        // Verify the guarantor
        $this->update([
            'verification_status' => 'verified',
            'verified_at' => now()
        ]);

        // Calculate guarantor score
        $this->calculateGuarantorScore();

        // Log activity
        SystemActivity::logActivity(
            'GUARANTOR_VERIFIED',
            $this->agent_id,
            'SUCCESS',
            "Guarantor {$this->full_name} verified for agent {$this->agent->agent_id}",
            ['guarantor_id' => $this->id, 'guarantor_score' => $this->guarantor_score]
        );

        return true;
    }

    public function calculateGuarantorScore()
    {
        $score = 0;
        
        // Guarantor Type Score (30 points)
        $typeScore = match($this->guarantor_type) {
            'bank_staff' => 30,
            'civil_servant' => 25,
            'business_owner' => 20,
            'professional' => 15,
            default => 10
        };
        $score += $typeScore;
        
        // Organization Score (20 points)
        if (!empty($this->organization)) {
            $score += 20;
        }
        
        // Position Score (15 points)
        if (!empty($this->position)) {
            $score += 15;
        }
        
        // Employee ID Score (10 points)
        if (!empty($this->employee_id)) {
            $score += 10;
        }
        
        // Relationship Score (15 points)
        $relationshipScore = match($this->relationship) {
            'family' => 15,
            'friend' => 10,
            'colleague' => 12,
            'employer' => 8,
            'other' => 5,
            default => 0
        };
        $score += $relationshipScore;
        
        // Years Known Score (10 points)
        if ($this->years_known) {
            $score += min($this->years_known * 2, 10);
        }
        
        $this->update(['guarantor_score' => $score]);
        return $score;
    }

    public function rejectGuarantor($reason)
    {
        $this->update([
            'verification_status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);

        // Log activity
        SystemActivity::logActivity(
            'GUARANTOR_REJECTED',
            $this->agent_id,
            'REJECTED',
            "Guarantor {$this->full_name} rejected for agent {$this->agent->agent_id}",
            ['guarantor_id' => $this->id, 'reason' => $reason]
        );
    }

    public function resendVerificationCode()
    {
        if ($this->verification_status !== 'pending') {
            return false;
        }

        // Check if we can resend (max 3 attempts per day)
        $todayAttempts = $this->verification_attempts;
        if ($todayAttempts >= 3) {
            return false;
        }

        $code = $this->generateVerificationCode();
        
        // Log activity
        SystemActivity::logActivity(
            'GUARANTOR_REMINDED',
            $this->agent_id,
            'PENDING',
            "Verification code resent to guarantor {$this->full_name}",
            ['guarantor_id' => $this->id, 'attempt' => $todayAttempts + 1]
        );

        return $code;
    }

    public function isVerified()
    {
        return $this->verification_status === 'verified';
    }

    public function isPending()
    {
        return $this->verification_status === 'pending';
    }

    public function isRejected()
    {
        return $this->verification_status === 'rejected';
    }

    public function isExpired()
    {
        return $this->verification_status === 'expired';
    }

    public function getGuarantorScoreColorAttribute()
    {
        if ($this->guarantor_score >= 80) {
            return 'green';
        } elseif ($this->guarantor_score >= 60) {
            return 'blue';
        } elseif ($this->guarantor_score >= 40) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function getGuarantorScoreTextAttribute()
    {
        if ($this->guarantor_score >= 80) {
            return 'Excellent';
        } elseif ($this->guarantor_score >= 60) {
            return 'Good';
        } elseif ($this->guarantor_score >= 40) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    public function canResendCode()
    {
        return $this->verification_status === 'pending' && $this->verification_attempts < 3;
    }

    public function getDaysSinceCodeSentAttribute()
    {
        if (!$this->verification_code_sent_at) {
            return null;
        }
        return $this->verification_code_sent_at->diffInDays(now());
    }

    public function getHoursSinceCodeSentAttribute()
    {
        if (!$this->verification_code_sent_at) {
            return null;
        }
        return $this->verification_code_sent_at->diffInHours(now());
    }
}
