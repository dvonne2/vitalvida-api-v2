<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AgentDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'document_type', 'file_path', 'file_name', 'file_size',
        'mime_type', 'file_hash', 'verification_status', 'ai_verification_score',
        'rejection_reason', 'ai_analysis_result', 'uploaded_at', 'verified_at',
        'rejected_at', 'verified_by', 'document_metadata', 'is_duplicate', 'duplicate_of_document_id'
    ];

    protected $casts = [
        'ai_verification_score' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ai_analysis_result' => 'array',
        'document_metadata' => 'array',
        'is_duplicate' => 'boolean'
    ];

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function getFileUrl()
    {
        return Storage::url($this->file_path);
    }

    public function getFileSizeHuman()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDocumentTypeTextAttribute()
    {
        return match($this->document_type) {
            'passport_photo' => 'Passport Photo',
            'government_id' => 'Government ID',
            'utility_bill' => 'Utility Bill',
            'drivers_license' => 'Driver\'s License',
            'bank_statement' => 'Bank Statement',
            default => 'Unknown Document'
        };
    }

    public function getVerificationStatusTextAttribute()
    {
        return match($this->verification_status) {
            'pending' => 'Pending Review',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'processing' => 'Processing',
            default => 'Unknown Status'
        };
    }

    public function getVerificationStatusColorAttribute()
    {
        return match($this->verification_status) {
            'verified' => 'green',
            'pending' => 'yellow',
            'rejected' => 'red',
            'processing' => 'blue',
            default => 'gray'
        };
    }

    public function runAiValidation()
    {
        // Simulate AI document validation
        $score = rand(75, 98) + (rand(0, 99) / 100);
        
        $this->update([
            'ai_verification_score' => $score,
            'verification_status' => $score >= 85 ? 'verified' : 'pending',
            'verified_at' => $score >= 85 ? now() : null,
            'verified_by' => $score >= 85 ? 'AI System' : null
        ]);

        // Create AI validation record
        AiValidation::create([
            'agent_id' => $this->agent_id,
            'validation_type' => 'document',
            'ai_score' => $score,
            'confidence_level' => rand(85, 99),
            'validation_result' => [
                'document_type' => $this->document_type,
                'clarity_score' => rand(80, 95),
                'authenticity_score' => rand(85, 98),
                'completeness_score' => rand(90, 100),
                'file_quality' => $this->file_size > 100000 ? 'good' : 'poor'
            ],
            'passed' => $score >= 85,
            'status' => 'completed',
            'processing_completed_at' => now(),
            'processing_duration_ms' => rand(1000, 5000)
        ]);

        return $score;
    }

    public function approveDocument($approvedBy = 'Admin')
    {
        $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $approvedBy
        ]);

        // Log activity
        SystemActivity::logActivity(
            'DOCUMENT_VERIFIED',
            $this->agent_id,
            'SUCCESS',
            "Document {$this->document_type} verified for agent {$this->agent->agent_id}",
            ['document_id' => $this->id, 'approved_by' => $approvedBy]
        );
    }

    public function rejectDocument($reason, $rejectedBy = 'Admin')
    {
        $this->update([
            'verification_status' => 'rejected',
            'rejected_at' => now(),
            'verified_by' => $rejectedBy,
            'rejection_reason' => $reason
        ]);

        // Log activity
        SystemActivity::logActivity(
            'DOCUMENT_REJECTED',
            $this->agent_id,
            'REJECTED',
            "Document {$this->document_type} rejected for agent {$this->agent->agent_id}",
            ['document_id' => $this->id, 'reason' => $reason, 'rejected_by' => $rejectedBy]
        );
    }

    public function isVerified()
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected()
    {
        return $this->verification_status === 'rejected';
    }

    public function isPending()
    {
        return $this->verification_status === 'pending';
    }

    public function isProcessing()
    {
        return $this->verification_status === 'processing';
    }

    public function getAiScoreColorAttribute()
    {
        if ($this->ai_verification_score >= 90) {
            return 'green';
        } elseif ($this->ai_verification_score >= 80) {
            return 'blue';
        } elseif ($this->ai_verification_score >= 70) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function getAiScoreTextAttribute()
    {
        if ($this->ai_verification_score >= 90) {
            return 'Excellent';
        } elseif ($this->ai_verification_score >= 80) {
            return 'Good';
        } elseif ($this->ai_verification_score >= 70) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    public function deleteFile()
    {
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            $document->deleteFile();
        });
    }
}
