<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'required_for_investor_type',
        'display_order',
        'is_active',
        'icon',
        'color'
    ];

    protected $casts = [
        'required_for_investor_type' => 'array',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function documents()
    {
        return $this->hasMany(InvestorDocument::class, 'category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    public function scopeByInvestorType($query, $investorType)
    {
        return $query->whereJsonContains('required_for_investor_type', $investorType);
    }

    // Business Logic Methods
    public function getDocumentCount()
    {
        return $this->documents()->count();
    }

    public function getCompletedDocumentCount()
    {
        return $this->documents()
            ->where('completion_status', InvestorDocument::COMPLETION_COMPLETE)
            ->count();
    }

    public function getCompletionPercentage()
    {
        $total = $this->getDocumentCount();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->getCompletedDocumentCount();
        return round(($completed / $total) * 100, 1);
    }

    public function getInProgressDocumentCount()
    {
        return $this->documents()
            ->where('completion_status', InvestorDocument::COMPLETION_PENDING)
            ->count();
    }

    public function getNotReadyDocumentCount()
    {
        return $this->documents()
            ->where('completion_status', InvestorDocument::COMPLETION_INCOMPLETE)
            ->count();
    }

    public function isRequiredForInvestorType($investorType)
    {
        if (empty($this->required_for_investor_type)) {
            return true; // Required for all if no specific types listed
        }

        return in_array($investorType, $this->required_for_investor_type);
    }

    public function getIconClass()
    {
        return $this->icon ?? 'fas fa-folder';
    }

    public function getColor()
    {
        return $this->color ?? '#6c757d';
    }

    public function getStatusColor()
    {
        $percentage = $this->getCompletionPercentage();
        
        if ($percentage >= 80) {
            return '#28a745'; // Green
        } elseif ($percentage >= 50) {
            return '#ffc107'; // Yellow
        } else {
            return '#dc3545'; // Red
        }
    }

    public function getProgressData()
    {
        return [
            'total' => $this->getDocumentCount(),
            'completed' => $this->getCompletedDocumentCount(),
            'in_progress' => $this->getInProgressDocumentCount(),
            'not_ready' => $this->getNotReadyDocumentCount(),
            'percentage' => $this->getCompletionPercentage(),
            'status_color' => $this->getStatusColor()
        ];
    }
}
