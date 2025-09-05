<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roadmap extends Model
{
    use HasFactory;

    protected $fillable = [
        'initiative',
        'owner',
        'completion_percentage',
        'quarter',
        'status',
        'milestones',
        'current_value',
        'target_value',
        'value_unit',
        'start_date',
        'target_date',
        'description'
    ];

    protected $casts = [
        'milestones' => 'array',
        'start_date' => 'date',
        'target_date' => 'date',
        'current_value' => 'decimal:2',
        'target_value' => 'decimal:2',
    ];

    // Scopes
    public function scopeByQuarter($query, $quarter)
    {
        return $query->where('quarter', $quarter);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByOwner($query, $owner)
    {
        return $query->where('owner', $owner);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed']);
    }

    // Helper methods
    public function getProgressColorAttribute(): string
    {
        if ($this->completion_percentage >= 80) {
            return 'green';
        } elseif ($this->completion_percentage >= 60) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function getRemainingDaysAttribute(): int
    {
        if (!$this->target_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->target_date, false));
    }

    public function getMilestonesCompletedAttribute(): int
    {
        if (!$this->milestones) {
            return 0;
        }

        return collect($this->milestones)->where('completed', true)->count();
    }

    public function getTotalMilestonesAttribute(): int
    {
        if (!$this->milestones) {
            return 0;
        }

        return count($this->milestones);
    }

    public function getFormattedCurrentValueAttribute(): string
    {
        if (!$this->current_value) {
            return '0';
        }

        if ($this->value_unit === 'percentage') {
            return number_format($this->current_value, 1) . '%';
        }

        if ($this->value_unit === 'revenue') {
            return '₦' . number_format($this->current_value, 0);
        }

        return number_format($this->current_value, 0);
    }

    public function getFormattedTargetValueAttribute(): string
    {
        if (!$this->target_value) {
            return '0';
        }

        if ($this->value_unit === 'percentage') {
            return number_format($this->target_value, 1) . '%';
        }

        if ($this->value_unit === 'revenue') {
            return '₦' . number_format($this->target_value, 0);
        }

        return number_format($this->target_value, 0);
    }
}
