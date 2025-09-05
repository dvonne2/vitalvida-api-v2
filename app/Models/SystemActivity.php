<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type', 'agent_id', 'event_id', 'status', 'event_time', 'description', 'metadata'
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'metadata' => 'array'
    ];

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public static function logActivity($eventType, $agentId, $status, $description = null, $metadata = [])
    {
        $agent = DeliveryAgent::find($agentId);
        
        return self::create([
            'event_type' => $eventType,
            'agent_id' => $agentId,
            'event_id' => $agent ? $agent->agent_id : null,
            'status' => $status,
            'event_time' => now(),
            'description' => $description,
            'metadata' => $metadata
        ]);
    }

    public function getFormattedTimeAttribute()
    {
        return $this->event_time->format('H:i:s');
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'SUCCESS' => 'green',
            'PENDING' => 'orange',
            'REJECTED' => 'red',
            'FAILED' => 'red',
            'PROCESSING' => 'blue',
            default => 'gray'
        };
    }

    public function getEventTypeTextAttribute()
    {
        return match($this->event_type) {
            'AUTO_APPROVED' => 'Auto Approved',
            'MANUAL_APPROVED' => 'Manually Approved',
            'APPLICATION_REJECTED' => 'Application Rejected',
            'GUARANTOR_REMINDED' => 'Guarantor Reminded',
            'GUARANTOR_VERIFIED' => 'Guarantor Verified',
            'GUARANTOR_REJECTED' => 'Guarantor Rejected',
            'DOCUMENT_VERIFIED' => 'Document Verified',
            'DOCUMENT_REJECTED' => 'Document Rejected',
            'APPLICATION_SUBMITTED' => 'Application Submitted',
            'EMAIL_INVALID' => 'Invalid Email',
            'AI_VALIDATION_COMPLETED' => 'AI Validation Completed',
            'AI_VALIDATION_FAILED' => 'AI Validation Failed',
            'MANUAL_REVIEW_ASSIGNED' => 'Manual Review Assigned',
            'MANUAL_REVIEW_COMPLETED' => 'Manual Review Completed',
            default => $this->event_type
        };
    }

    public function getEventTypeIconAttribute()
    {
        return match($this->event_type) {
            'AUTO_APPROVED' => 'check-circle',
            'MANUAL_APPROVED' => 'user-check',
            'APPLICATION_REJECTED' => 'x-circle',
            'GUARANTOR_REMINDED' => 'bell',
            'GUARANTOR_VERIFIED' => 'shield-check',
            'GUARANTOR_REJECTED' => 'shield-x',
            'DOCUMENT_VERIFIED' => 'document-check',
            'DOCUMENT_REJECTED' => 'document-x',
            'APPLICATION_SUBMITTED' => 'clipboard-list',
            'EMAIL_INVALID' => 'exclamation-triangle',
            'AI_VALIDATION_COMPLETED' => 'cpu-chip',
            'AI_VALIDATION_FAILED' => 'cpu-chip-x',
            'MANUAL_REVIEW_ASSIGNED' => 'user-plus',
            'MANUAL_REVIEW_COMPLETED' => 'user-check',
            default => 'information-circle'
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'SUCCESS' => 'Success',
            'PENDING' => 'Pending',
            'REJECTED' => 'Rejected',
            'FAILED' => 'Failed',
            'PROCESSING' => 'Processing',
            default => 'Unknown'
        };
    }

    public function getTimeAgoAttribute()
    {
        return $this->event_time->diffForHumans();
    }

    public function getFormattedDateAttribute()
    {
        return $this->event_time->format('M d, Y');
    }

    public function getFormattedDateTimeAttribute()
    {
        return $this->event_time->format('M d, Y H:i:s');
    }

    // Scopes
    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('event_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('event_time', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('event_time', now()->month)
                    ->whereYear('event_time', now()->year);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('event_time', '>=', now()->subHours($hours));
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    // Static methods for common activities
    public static function logAutoApproval($agentId, $aiScore)
    {
        return self::logActivity(
            'AUTO_APPROVED',
            $agentId,
            'SUCCESS',
            "Agent automatically approved with AI score: {$aiScore}",
            ['ai_score' => $aiScore]
        );
    }

    public static function logManualApproval($agentId, $approvedBy)
    {
        return self::logActivity(
            'MANUAL_APPROVED',
            $agentId,
            'SUCCESS',
            "Agent manually approved by {$approvedBy}",
            ['approved_by' => $approvedBy]
        );
    }

    public static function logApplicationRejection($agentId, $reason, $rejectedBy)
    {
        return self::logActivity(
            'APPLICATION_REJECTED',
            $agentId,
            'REJECTED',
            "Application rejected by {$rejectedBy}: {$reason}",
            ['reason' => $reason, 'rejected_by' => $rejectedBy]
        );
    }

    public static function logGuarantorReminder($agentId, $guarantorName)
    {
        return self::logActivity(
            'GUARANTOR_REMINDED',
            $agentId,
            'PENDING',
            "Reminder sent to guarantor: {$guarantorName}",
            ['guarantor_name' => $guarantorName]
        );
    }

    public static function logDocumentVerification($agentId, $documentType, $verifiedBy)
    {
        return self::logActivity(
            'DOCUMENT_VERIFIED',
            $agentId,
            'SUCCESS',
            "Document {$documentType} verified by {$verifiedBy}",
            ['document_type' => $documentType, 'verified_by' => $verifiedBy]
        );
    }

    public static function logDocumentRejection($agentId, $documentType, $reason, $rejectedBy)
    {
        return self::logActivity(
            'DOCUMENT_REJECTED',
            $agentId,
            'REJECTED',
            "Document {$documentType} rejected by {$rejectedBy}: {$reason}",
            ['document_type' => $documentType, 'reason' => $reason, 'rejected_by' => $rejectedBy]
        );
    }

    public static function logApplicationSubmission($agentId)
    {
        return self::logActivity(
            'APPLICATION_SUBMITTED',
            $agentId,
            'SUCCESS',
            "Application submitted successfully",
            ['submitted_at' => now()]
        );
    }

    public static function logInvalidEmail($agentId, $email)
    {
        return self::logActivity(
            'EMAIL_INVALID',
            $agentId,
            'REJECTED',
            "Invalid email address: {$email}",
            ['email' => $email]
        );
    }

    // Dashboard statistics methods
    public static function getTodayStats()
    {
        $today = self::today();
        
        return [
            'total_activities' => $today->count(),
            'successful' => $today->success()->count(),
            'pending' => $today->pending()->count(),
            'rejected' => $today->rejected()->count(),
            'auto_approved' => $today->byEventType('AUTO_APPROVED')->count(),
            'manual_approved' => $today->byEventType('MANUAL_APPROVED')->count(),
            'guarantor_reminders' => $today->byEventType('GUARANTOR_REMINDED')->count(),
            'document_verifications' => $today->byEventType('DOCUMENT_VERIFIED')->count()
        ];
    }

    public static function getRecentActivities($limit = 10)
    {
        return self::with('agent')
                   ->orderBy('event_time', 'desc')
                   ->limit($limit)
                   ->get();
    }

    public static function getActivitySummary($days = 7)
    {
        $startDate = now()->subDays($days);
        
        return self::where('event_time', '>=', $startDate)
                   ->selectRaw('DATE(event_time) as date, COUNT(*) as total, status')
                   ->groupBy('date', 'status')
                   ->orderBy('date', 'desc')
                   ->get();
    }
}
