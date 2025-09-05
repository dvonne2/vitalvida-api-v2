<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImDailyLog extends Model
{
    protected $fillable = [
        'user_id', 'log_date', 'login_time', 'completed_da_review',
        'das_reviewed_count', 'recommendations_executed', 'penalty_amount', 'bonus_amount'
    ];

    protected $dates = ['log_date'];

    public function user() { return $this->belongsTo(User::class); }
    public function getNetPerformanceAttribute() { return $this->bonus_amount - $this->penalty_amount; }
}
