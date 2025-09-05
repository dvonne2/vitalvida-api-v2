<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExitChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_process_id',
        'item_name',
        'description',
        'status',
        'notes',
        'completion_date',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'completion_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function exitProcess()
    {
        return $this->belongsTo(ExitProcess::class);
    }
}
