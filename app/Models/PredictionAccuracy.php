<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionAccuracy extends Model
{
    protected $table = 'prediction_accuracy';
    
    protected $fillable = [
        'model_name', 'prediction_type', 'evaluation_date',
        'accuracy_percentage', 'mean_absolute_error', 'root_mean_square_error',
        'total_predictions', 'correct_predictions', 'performance_metrics',
        'model_parameters'
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'accuracy_percentage' => 'decimal:2',
        'mean_absolute_error' => 'decimal:2',
        'root_mean_square_error' => 'decimal:2',
        'performance_metrics' => 'array',
        'model_parameters' => 'array'
    ];

    public function scopeByModel($query, $modelName)
    {
        return $query->where('model_name', $modelName);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('evaluation_date', '>=', now()->subDays($days));
    }

    public function getPerformanceGradeAttribute()
    {
        if ($this->accuracy_percentage >= 90) return 'A+';
        if ($this->accuracy_percentage >= 85) return 'A';
        if ($this->accuracy_percentage >= 80) return 'B+';
        if ($this->accuracy_percentage >= 75) return 'B';
        if ($this->accuracy_percentage >= 70) return 'C';
        return 'D';
    }
}
