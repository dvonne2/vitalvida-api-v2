<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    use HasFactory;

    protected $fillable = [
        'decision_date',
        'decision_title',
        'context',
        'outcome',
        'lesson_learned',
        'impact_score',
        'department',
        'decision_maker',
        'category',
        'tags'
    ];

    protected $casts = [
        'decision_date' => 'date',
        'tags' => 'array',
    ];

    // Scopes
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('decision_date', [$startDate, $endDate]);
    }

    public function scopeHighImpact($query)
    {
        return $query->where('impact_score', '>=', 7);
    }

    public function scopeByDecisionMaker($query, $decisionMaker)
    {
        return $query->where('decision_maker', $decisionMaker);
    }

    // Helper methods
    public function getImpactLevelAttribute(): string
    {
        if ($this->impact_score >= 8) {
            return 'high';
        } elseif ($this->impact_score >= 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    public function getFormattedDecisionDateAttribute(): string
    {
        return $this->decision_date->format('M d, Y');
    }

    public function getDaysSinceAttribute(): int
    {
        return $this->decision_date->diffInDays(now());
    }

    public function getTagsListAttribute(): array
    {
        return $this->tags ?? [];
    }
}
