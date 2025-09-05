<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPortalAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'portal',
        'can_view',
        'can_manage',
        'meta',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_manage' => 'boolean',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
