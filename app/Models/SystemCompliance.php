<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCompliance extends Model
{
    protected $table = 'system_compliance';

    protected $fillable = [
        'accountant_id', 'compliance_date', 'payment_matching_rate',
        'escalation_discipline_rate', 'documentation_integrity_rate',
        'bonus_log_accuracy_rate', 'overall_compliance_score',
        'system_health_score', 'cache_hit_rate', 'strikes_count',
        'penalties_total'
    ];

    protected $casts = [
        'compliance_date' => 'date',
        'payment_matching_rate' => 'decimal:2',
        'escalation_discipline_rate' => 'decimal:2',
        'documentation_integrity_rate' => 'decimal:2',
        'bonus_log_accuracy_rate' => 'decimal:2',
        'overall_compliance_score' => 'decimal:2',
        'system_health_score' => 'decimal:2',
        'cache_hit_rate' => 'decimal:2',
        'strikes_count' => 'integer',
        'penalties_total' => 'decimal:2'
    ];

    public function accountant()
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('compliance_date', $date);
    }

    public function scopeByAccountant($query, $accountantId)
    {
        return $query->where('accountant_id', $accountantId);
    }

    public function scopeHighCompliance($query)
    {
        return $query->where('overall_compliance_score', '>=', 90);
    }

    public function scopeLowCompliance($query)
    {
        return $query->where('overall_compliance_score', '<', 70);
    }

    public function calculateOverallScore()
    {
        $scores = [
            $this->payment_matching_rate,
            $this->escalation_discipline_rate,
            $this->documentation_integrity_rate,
            $this->bonus_log_accuracy_rate
        ];

        $this->overall_compliance_score = round(array_sum($scores) / count($scores), 2);
        $this->save();

        return $this->overall_compliance_score;
    }

    public function getComplianceGrade()
    {
        return match(true) {
            $this->overall_compliance_score >= 95 => 'A+',
            $this->overall_compliance_score >= 90 => 'A',
            $this->overall_compliance_score >= 85 => 'B+',
            $this->overall_compliance_score >= 80 => 'B',
            $this->overall_compliance_score >= 75 => 'C+',
            $this->overall_compliance_score >= 70 => 'C',
            $this->overall_compliance_score >= 65 => 'D+',
            $this->overall_compliance_score >= 60 => 'D',
            default => 'F'
        };
    }

    public function getComplianceColor()
    {
        return match(true) {
            $this->overall_compliance_score >= 90 => 'green',
            $this->overall_compliance_score >= 80 => 'blue',
            $this->overall_compliance_score >= 70 => 'yellow',
            default => 'red'
        };
    }

    public function getSystemHealthColor()
    {
        return match(true) {
            $this->system_health_score >= 90 => 'green',
            $this->system_health_score >= 80 => 'yellow',
            default => 'red'
        };
    }

    public function getFormattedComplianceDate()
    {
        return $this->compliance_date->format('M d, Y');
    }

    public function getFormattedPenaltiesTotal()
    {
        return '₦' . number_format($this->penalties_total, 2);
    }

    public function getComplianceTrend()
    {
        $previousDay = $this->accountant->complianceRecords()
                                      ->where('compliance_date', '<', $this->compliance_date)
                                      ->latest('compliance_date')
                                      ->first();

        if (!$previousDay) return 'new';

        $difference = $this->overall_compliance_score - $previousDay->overall_compliance_score;

        return match(true) {
            $difference > 5 => 'improving',
            $difference < -5 => 'declining',
            default => 'stable'
        };
    }

    public function getTrendIcon()
    {
        return match($this->getComplianceTrend()) {
            'improving' => '📈',
            'declining' => '📉',
            default => '➡️'
        };
    }

    public function getTrendColor()
    {
        return match($this->getComplianceTrend()) {
            'improving' => 'green',
            'declining' => 'red',
            default => 'gray'
        };
    }

    public function getComplianceSummary()
    {
        return [
            'overall_score' => $this->overall_compliance_score,
            'grade' => $this->getComplianceGrade(),
            'color' => $this->getComplianceColor(),
            'trend' => $this->getComplianceTrend(),
            'trend_icon' => $this->getTrendIcon(),
            'trend_color' => $this->getTrendColor(),
            'system_health' => $this->system_health_score,
            'strikes' => $this->strikes_count,
            'penalties' => $this->penalties_total
        ];
    }

    public function getDetailedMetrics()
    {
        return [
            [
                'name' => 'Payment Matching',
                'score' => $this->payment_matching_rate,
                'target' => 98,
                'status' => $this->payment_matching_rate >= 98 ? 'pass' : 'fail'
            ],
            [
                'name' => 'Escalation Discipline',
                'score' => $this->escalation_discipline_rate,
                'target' => 100,
                'status' => $this->escalation_discipline_rate >= 100 ? 'pass' : 'fail'
            ],
            [
                'name' => 'Documentation Integrity',
                'score' => $this->documentation_integrity_rate,
                'target' => 100,
                'status' => $this->documentation_integrity_rate >= 100 ? 'pass' : 'fail'
            ],
            [
                'name' => 'Bonus Log Accuracy',
                'score' => $this->bonus_log_accuracy_rate,
                'target' => 100,
                'status' => $this->bonus_log_accuracy_rate >= 100 ? 'pass' : 'fail'
            ]
        ];
    }

    public function isExcellent()
    {
        return $this->overall_compliance_score >= 95;
    }

    public function isGood()
    {
        return $this->overall_compliance_score >= 85 && $this->overall_compliance_score < 95;
    }

    public function isFair()
    {
        return $this->overall_compliance_score >= 75 && $this->overall_compliance_score < 85;
    }

    public function isPoor()
    {
        return $this->overall_compliance_score < 75;
    }

    public function needsAttention()
    {
        return $this->overall_compliance_score < 80 || $this->strikes_count > 0;
    }

    public function getRecommendations()
    {
        $recommendations = [];

        if ($this->payment_matching_rate < 98) {
            $recommendations[] = 'Improve payment matching accuracy through better verification processes';
        }

        if ($this->escalation_discipline_rate < 100) {
            $recommendations[] = 'Ensure all payments follow proper escalation procedures';
        }

        if ($this->documentation_integrity_rate < 100) {
            $recommendations[] = 'Upload all required receipts and documentation promptly';
        }

        if ($this->bonus_log_accuracy_rate < 100) {
            $recommendations[] = 'Ensure all bonus payments are pre-approved by Financial Controller';
        }

        if ($this->strikes_count > 0) {
            $recommendations[] = 'Address active strikes to improve compliance score';
        }

        return $recommendations;
    }
} 