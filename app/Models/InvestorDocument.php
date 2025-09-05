<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvestorDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category_id',
        'status',
        'completion_status',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'access_permissions',
        'due_date',
        'completed_date',
        'assigned_to',
        'created_by',
        'updated_by',
        'notes',
        'is_required',
        'priority'
    ];

    protected $casts = [
        'access_permissions' => 'array',
        'due_date' => 'date',
        'completed_date' => 'date',
        'is_required' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Status constants
    const STATUS_READY = 'ready';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_NOT_READY = 'not_ready';

    // Completion status constants
    const COMPLETION_COMPLETE = 'complete';
    const COMPLETION_INCOMPLETE = 'incomplete';
    const COMPLETION_PENDING = 'pending';

    // Priority constants
    const PRIORITY_LOW = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_HIGH = 3;

    // Relationships
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function investors()
    {
        return $this->belongsToMany(Investor::class, 'investor_document_access')
            ->withPivot('can_view', 'can_download', 'can_edit')
            ->withTimestamps();
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCompletionStatus($query, $completionStatus)
    {
        return $query->where('completion_status', $completionStatus);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('completion_status', '!=', self::COMPLETION_COMPLETE);
    }

    // Business Logic Methods
    public function getStatusDisplayName()
    {
        $statusNames = [
            self::STATUS_READY => 'Ready',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_NOT_READY => 'Not Ready'
        ];

        return $statusNames[$this->status] ?? $this->status;
    }

    public function getCompletionStatusDisplayName()
    {
        $completionNames = [
            self::COMPLETION_COMPLETE => 'Complete',
            self::COMPLETION_INCOMPLETE => 'Incomplete',
            self::COMPLETION_PENDING => 'Pending'
        ];

        return $completionNames[$this->completion_status] ?? $this->completion_status;
    }

    public function getPriorityDisplayName()
    {
        $priorityNames = [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High'
        ];

        return $priorityNames[$this->priority] ?? $this->priority;
    }

    public function getFileSizeFormatted()
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function isOverdue()
    {
        return $this->due_date && 
               $this->due_date < now() && 
               $this->completion_status !== self::COMPLETION_COMPLETE;
    }

    public function isAccessibleByInvestor($investor)
    {
        if (empty($this->access_permissions)) {
            return true; // No restrictions
        }

        return in_array($investor->role, $this->access_permissions);
    }

    public function markAsComplete()
    {
        $this->update([
            'completion_status' => self::COMPLETION_COMPLETE,
            'completed_date' => now()
        ]);
    }

    public function markAsInProgress()
    {
        $this->update([
            'completion_status' => self::COMPLETION_PENDING,
            'status' => self::STATUS_IN_PROGRESS
        ]);
    }

    public function getProgressPercentage()
    {
        switch ($this->completion_status) {
            case self::COMPLETION_COMPLETE:
                return 100;
            case self::COMPLETION_PENDING:
                return 50;
            case self::COMPLETION_INCOMPLETE:
                return 0;
            default:
                return 0;
        }
    }

    public function getFileUrl()
    {
        if (!$this->file_path) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }

    public function canBeDownloadedBy($investor)
    {
        if (!$this->isAccessibleByInvestor($investor)) {
            return false;
        }

        $access = $this->investors()->where('investor_id', $investor->id)->first();
        
        if (!$access) {
            return false; // No access record found
        }

        return $access->pivot->can_download ?? false;
    }

    public function canBeViewedBy($investor)
    {
        if (!$this->isAccessibleByInvestor($investor)) {
            return false;
        }

        $access = $this->investors()->where('investor_id', $investor->id)->first();
        
        if (!$access) {
            return false; // No access record found
        }

        return $access->pivot->can_view ?? false;
    }
}
