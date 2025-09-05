<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'validation_type', 'ai_score', 'confidence_level', 'validation_result',
        'passed', 'status', 'failure_reason', 'validation_date', 'processing_started_at',
        'processing_completed_at', 'processing_duration_ms', 'ai_model_version', 'ai_provider',
        'model_parameters', 'risk_level', 'risk_factors', 'risk_mitigation_suggestions',
        'requires_manual_review', 'assigned_to', 'manual_review_date', 'manual_review_notes'
    ];

    protected $casts = [
        'ai_score' => 'decimal:2',
        'confidence_level' => 'decimal:2',
        'validation_result' => 'array',
        'passed' => 'boolean',
        'validation_date' => 'datetime',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
        'model_parameters' => 'array',
        'risk_factors' => 'array',
        'requires_manual_review' => 'boolean',
        'manual_review_date' => 'datetime'
    ];

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function getValidationTypeTextAttribute()
    {
        return match($this->validation_type) {
            'document' => 'Document Verification',
            'data' => 'Data Validation',
            'guarantor' => 'Guarantor Verification',
            'overall' => 'Overall Assessment',
            'requirements' => 'Requirements Check',
            'identity' => 'Identity Verification',
            default => 'Unknown Validation'
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown Status'
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray'
        };
    }

    public function getRiskLevelTextAttribute()
    {
        return match($this->risk_level) {
            'low' => 'Low Risk',
            'medium' => 'Medium Risk',
            'high' => 'High Risk',
            'critical' => 'Critical Risk',
            default => 'Unknown Risk'
        };
    }

    public function getRiskLevelColorAttribute()
    {
        return match($this->risk_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function getAiScoreColorAttribute()
    {
        if ($this->ai_score >= 90) {
            return 'green';
        } elseif ($this->ai_score >= 80) {
            return 'blue';
        } elseif ($this->ai_score >= 70) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function getAiScoreTextAttribute()
    {
        if ($this->ai_score >= 90) {
            return 'Excellent';
        } elseif ($this->ai_score >= 80) {
            return 'Good';
        } elseif ($this->ai_score >= 70) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    public function getConfidenceLevelColorAttribute()
    {
        if ($this->confidence_level >= 95) {
            return 'green';
        } elseif ($this->confidence_level >= 85) {
            return 'blue';
        } elseif ($this->confidence_level >= 75) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function startProcessing()
    {
        $this->update([
            'status' => 'processing',
            'processing_started_at' => now()
        ]);
    }

    public function completeProcessing($result, $score, $confidence)
    {
        $processingDuration = $this->processing_started_at ? 
            $this->processing_started_at->diffInMilliseconds(now()) : null;

        $this->update([
            'status' => 'completed',
            'processing_completed_at' => now(),
            'processing_duration_ms' => $processingDuration,
            'ai_score' => $score,
            'confidence_level' => $confidence,
            'validation_result' => $result,
            'passed' => $score >= 85,
            'risk_level' => $this->calculateRiskLevel($score, $result)
        ]);

        // Log activity
        SystemActivity::logActivity(
            'AI_VALIDATION_COMPLETED',
            $this->agent_id,
            $this->passed ? 'SUCCESS' : 'REJECTED',
            "AI validation {$this->validation_type} completed for agent {$this->agent->agent_id}",
            [
                'validation_id' => $this->id,
                'score' => $score,
                'confidence' => $confidence,
                'passed' => $this->passed,
                'risk_level' => $this->risk_level
            ]
        );
    }

    public function failProcessing($reason)
    {
        $this->update([
            'status' => 'failed',
            'processing_completed_at' => now(),
            'failure_reason' => $reason
        ]);

        // Log activity
        SystemActivity::logActivity(
            'AI_VALIDATION_FAILED',
            $this->agent_id,
            'REJECTED',
            "AI validation {$this->validation_type} failed for agent {$this->agent->agent_id}",
            ['validation_id' => $this->id, 'reason' => $reason]
        );
    }

    private function calculateRiskLevel($score, $result)
    {
        if ($score >= 90) {
            return 'low';
        } elseif ($score >= 80) {
            return 'medium';
        } elseif ($score >= 70) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    public function assignForManualReview($assignedTo)
    {
        $this->update([
            'requires_manual_review' => true,
            'assigned_to' => $assignedTo
        ]);

        // Log activity
        SystemActivity::logActivity(
            'MANUAL_REVIEW_ASSIGNED',
            $this->agent_id,
            'PENDING',
            "AI validation {$this->validation_type} assigned for manual review",
            ['validation_id' => $this->id, 'assigned_to' => $assignedTo]
        );
    }

    public function completeManualReview($notes, $reviewer)
    {
        $this->update([
            'requires_manual_review' => false,
            'manual_review_date' => now(),
            'manual_review_notes' => $notes,
            'assigned_to' => $reviewer
        ]);

        // Log activity
        SystemActivity::logActivity(
            'MANUAL_REVIEW_COMPLETED',
            $this->agent_id,
            'SUCCESS',
            "Manual review completed for AI validation {$this->validation_type}",
            ['validation_id' => $this->id, 'reviewer' => $reviewer]
        );
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function requiresManualReview()
    {
        return $this->requires_manual_review;
    }

    public function getProcessingDurationAttribute()
    {
        if (!$this->processing_started_at || !$this->processing_completed_at) {
            return null;
        }
        return $this->processing_started_at->diffInMilliseconds($this->processing_completed_at);
    }

    public function getProcessingDurationHumanAttribute()
    {
        $duration = $this->processing_duration_ms;
        if (!$duration) return 'N/A';
        
        if ($duration < 1000) {
            return $duration . 'ms';
        } elseif ($duration < 60000) {
            return round($duration / 1000, 2) . 's';
        } else {
            return round($duration / 60000, 2) . 'm';
        }
    }

    // Scopes
    public function scopePassed($query)
    {
        return $query->where('passed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('passed', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('validation_type', $type);
    }

    public function scopeByRiskLevel($query, $level)
    {
        return $query->where('risk_level', $level);
    }

    public function scopeRequiresManualReview($query)
    {
        return $query->where('requires_manual_review', true);
    }

    public function scopeHighConfidence($query, $minConfidence = 85)
    {
        return $query->where('confidence_level', '>=', $minConfidence);
    }
}
