<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HRWebSocketEvents implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $eventType;
    public $data;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(string $eventType, array $data)
    {
        $this->eventType = $eventType;
        $this->data = $data;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('hr-dashboard'),
            new Channel('hr-talent-pipeline'),
            new Channel('hr-performance'),
            new Channel('hr-training'),
            new Channel('hr-payroll'),
            new Channel('hr-exit')
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'data' => $this->data,
            'timestamp' => $this->timestamp
        ];
    }

    /**
     * Get the event name.
     */
    public function broadcastAs(): string
    {
        return 'hr-event';
    }
}

/**
 * Specific HR Event Classes
 */

class NewJobApplication extends HRWebSocketEvents
{
    public function __construct(array $applicationData)
    {
        parent::__construct('new_job_application', $applicationData);
    }
}

class CandidateStatusUpdated extends HRWebSocketEvents
{
    public function __construct(array $candidateData)
    {
        parent::__construct('candidate_status_updated', $candidateData);
    }
}

class PerformanceAlertTriggered extends HRWebSocketEvents
{
    public function __construct(array $performanceData)
    {
        parent::__construct('performance_alert_triggered', $performanceData);
    }
}

class AttendanceViolation extends HRWebSocketEvents
{
    public function __construct(array $attendanceData)
    {
        parent::__construct('attendance_violation', $attendanceData);
    }
}

class TrainingMilestoneReached extends HRWebSocketEvents
{
    public function __construct(array $trainingData)
    {
        parent::__construct('training_milestone_reached', $trainingData);
    }
}

class ExitProcessStarted extends HRWebSocketEvents
{
    public function __construct(array $exitData)
    {
        parent::__construct('exit_process_started', $exitData);
    }
}

class PayrollApprovalRequired extends HRWebSocketEvents
{
    public function __construct(array $payrollData)
    {
        parent::__construct('payroll_approval_required', $payrollData);
    }
}

class AIScreeningCompleted extends HRWebSocketEvents
{
    public function __construct(array $screeningData)
    {
        parent::__construct('ai_screening_completed', $screeningData);
    }
}

class EmployeePerformanceUpdated extends HRWebSocketEvents
{
    public function __construct(array $performanceData)
    {
        parent::__construct('employee_performance_updated', $performanceData);
    }
}

class LeaveRequestSubmitted extends HRWebSocketEvents
{
    public function __construct(array $leaveData)
    {
        parent::__construct('leave_request_submitted', $leaveData);
    }
}

class SystemAccessGranted extends HRWebSocketEvents
{
    public function __construct(array $accessData)
    {
        parent::__construct('system_access_granted', $accessData);
    }
}

class OnboardingCertificateIssued extends HRWebSocketEvents
{
    public function __construct(array $certificateData)
    {
        parent::__construct('onboarding_certificate_issued', $certificateData);
    }
}

class ExitChecklistUpdated extends HRWebSocketEvents
{
    public function __construct(array $checklistData)
    {
        parent::__construct('exit_checklist_updated', $checklistData);
    }
}

class HRDashboardUpdated extends HRWebSocketEvents
{
    public function __construct(array $dashboardData)
    {
        parent::__construct('hr_dashboard_updated', $dashboardData);
    }
}

class TalentPipelineUpdated extends HRWebSocketEvents
{
    public function __construct(array $pipelineData)
    {
        parent::__construct('talent_pipeline_updated', $pipelineData);
    }
}

class PerformanceReviewCompleted extends HRWebSocketEvents
{
    public function __construct(array $reviewData)
    {
        parent::__construct('performance_review_completed', $reviewData);
    }
}

class TrainingProgressUpdated extends HRWebSocketEvents
{
    public function __construct(array $progressData)
    {
        parent::__construct('training_progress_updated', $progressData);
    }
}

class PayrollProcessed extends HRWebSocketEvents
{
    public function __construct(array $payrollData)
    {
        parent::__construct('payroll_processed', $payrollData);
    }
}

class ExitProcessCompleted extends HRWebSocketEvents
{
    public function __construct(array $exitData)
    {
        parent::__construct('exit_process_completed', $exitData);
    }
}

class AIInsightGenerated extends HRWebSocketEvents
{
    public function __construct(array $insightData)
    {
        parent::__construct('ai_insight_generated', $insightData);
    }
}

class HRAlertTriggered extends HRWebSocketEvents
{
    public function __construct(array $alertData)
    {
        parent::__construct('hr_alert_triggered', $alertData);
    }
}

class EmployeeStatusChanged extends HRWebSocketEvents
{
    public function __construct(array $statusData)
    {
        parent::__construct('employee_status_changed', $statusData);
    }
}

class RecruitmentMetricsUpdated extends HRWebSocketEvents
{
    public function __construct(array $metricsData)
    {
        parent::__construct('recruitment_metrics_updated', $metricsData);
    }
}

class TrainingMetricsUpdated extends HRWebSocketEvents
{
    public function __construct(array $metricsData)
    {
        parent::__construct('training_metrics_updated', $metricsData);
    }
}

class PerformanceMetricsUpdated extends HRWebSocketEvents
{
    public function __construct(array $metricsData)
    {
        parent::__construct('performance_metrics_updated', $metricsData);
    }
}

class PayrollMetricsUpdated extends HRWebSocketEvents
{
    public function __construct(array $metricsData)
    {
        parent::__construct('payroll_metrics_updated', $metricsData);
    }
}

class ExitMetricsUpdated extends HRWebSocketEvents
{
    public function __construct(array $metricsData)
    {
        parent::__construct('exit_metrics_updated', $metricsData);
    }
} 