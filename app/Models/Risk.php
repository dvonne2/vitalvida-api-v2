<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Risk extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_title',
        'severity',
        'probability',
        'impact_description',
        'mitigation_plan',
        'owner',
        'status',
        'identified_date',
        'target_resolution_date',
        'resolved_date',
        'financial_impact',
        'notes'
    ];

    protected $casts = [
        'identified_date' => 'date',
        'target_resolution_date' => 'date',
        'resolved_date' => 'date',
        'financial_impact' => 'decimal:2',
    ];

    // Scopes
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
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
        return $query->whereIn('status', ['active', 'escalated']);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', ['high', 'critical'])
            ->whereIn('probability', ['high', 'confirmed']);
    }

    // Helper methods
    public function getRiskScoreAttribute(): int
    {
        $severityScores = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4
        ];

        $probabilityScores = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'confirmed' => 4
        ];

        $severityScore = $severityScores[$this->severity] ?? 1;
        $probabilityScore = $probabilityScores[$this->probability] ?? 1;

        return $severityScore * $probabilityScore;
    }

    public function getRiskLevelAttribute(): string
    {
        $score = $this->risk_score;

        if ($score >= 12) {
            return 'critical';
        } elseif ($score >= 8) {
            return 'high';
        } elseif ($score >= 4) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    public function getDaysOpenAttribute(): int
    {
        return $this->identified_date->diffInDays(now());
    }

    public function getDaysUntilTargetAttribute(): int
    {
        if (!$this->target_resolution_date) {
            return 0;
        }

        return $this->target_resolution_date->diffInDays(now(), false);
    }

    public function getFormattedFinancialImpactAttribute(): string
    {
        if (!$this->financial_impact) {
            return '₦0';
        }

        return '₦' . number_format($this->financial_impact, 0);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->target_resolution_date) {
            return false;
        }

        return $this->target_resolution_date->isPast() && $this->status !== 'resolved';
    }
}
