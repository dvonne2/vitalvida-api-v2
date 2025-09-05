<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExitInterview extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_process_id',
        'conducted_date',
        'interviewer',
        'key_findings',
        'recommendations',
        'recording_url',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'conducted_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function exitProcess()
    {
        return $this->belongsTo(ExitProcess::class);
    }
}
