<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyProgressTracking extends Model
{
    protected $table = 'daily_progress_tracking';

    protected $fillable = [
        'accountant_id', 'task_date', 'task_type', 'task_description',
        'amount', 'status', 'completed_at', 'notes'
    ];

    protected $casts = [
        'task_date' => 'date',
        'amount' => 'decimal:2',
        'completed_at' => 'datetime'
    ];

    public function accountant()
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('task_date', $date);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('task_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeToday($query)
    {
        return $query->where('task_date', now()->toDateString());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('task_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function markAsInProgress()
    {
        $this->update([
            'status' => 'in_progress'
        ]);
    }

    public function getTaskTypeDisplayName()
    {
        return match($this->task_type) {
            'upload_proofs' => 'Upload Proofs',
            'process_bonus' => 'Process Bonus',
            'process_payments' => 'Process Payments',
            'upload_receipt' => 'Upload Receipt',
            'escalation_review' => 'Escalation Review',
            default => 'Unknown Task'
        };
    }

    public function getTaskTypeIcon()
    {
        return match($this->task_type) {
            'upload_proofs' => '📤',
            'process_bonus' => '💰',
            'process_payments' => '💳',
            'upload_receipt' => '🧾',
            'escalation_review' => '⚠️',
            default => '📋'
        };
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'completed' => 'green',
            'in_progress' => 'blue',
            default => 'yellow'
        };
    }

    public function getStatusText()
    {
        return match($this->status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            default => 'Pending'
        };
    }

    public function getFormattedAmount()
    {
        return $this->amount ? '₦' . number_format($this->amount, 2) : 'N/A';
    }

    public function getFormattedTaskDate()
    {
        return $this->task_date->format('M d, Y');
    }

    public function getProcessingTime()
    {
        if ($this->completed_at) {
            return $this->created_at->diffInMinutes($this->completed_at);
        }
        return $this->created_at->diffInMinutes(now());
    }

    public function getFormattedProcessingTime()
    {
        $minutes = $this->getProcessingTime();
        
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . ' hr';
        }
        
        return $hours . ' hr ' . $remainingMinutes . ' min';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isOverdue()
    {
        return $this->isPending() && $this->task_date < now()->toDateString();
    }

    public function getPriority()
    {
        return match($this->task_type) {
            'upload_receipt' => 'high',
            'process_payments' => 'high',
            'escalation_review' => 'medium',
            'process_bonus' => 'medium',
            'upload_proofs' => 'low',
            default => 'low'
        };
    }

    public function getPriorityColor()
    {
        return match($this->getPriority()) {
            'high' => 'red',
            'medium' => 'orange',
            default => 'green'
        };
    }

    public function getPriorityIcon()
    {
        return match($this->getPriority()) {
            'high' => '🔴',
            'medium' => '🟡',
            default => '🟢'
        };
    }

    public function getTaskSummary()
    {
        return [
            'id' => $this->id,
            'type' => $this->task_type,
            'type_display' => $this->getTaskTypeDisplayName(),
            'type_icon' => $this->getTaskTypeIcon(),
            'description' => $this->task_description,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'status_color' => $this->getStatusColor(),
            'priority' => $this->getPriority(),
            'priority_color' => $this->getPriorityColor(),
            'priority_icon' => $this->getPriorityIcon(),
            'amount' => $this->getFormattedAmount(),
            'date' => $this->getFormattedTaskDate(),
            'processing_time' => $this->getFormattedProcessingTime(),
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes
        ];
    }

    public function getCompletionRate()
    {
        $totalTasks = $this->accountant->dailyProgress()
                                     ->where('task_date', $this->task_date)
                                     ->count();
        
        if ($totalTasks === 0) return 0;
        
        $completedTasks = $this->accountant->dailyProgress()
                                         ->where('task_date', $this->task_date)
                                         ->where('status', 'completed')
                                         ->count();
        
        return round(($completedTasks / $totalTasks) * 100, 1);
    }
} 