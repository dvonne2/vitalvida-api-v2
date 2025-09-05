<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoAudit extends Model
{
    protected $fillable = [
        'delivery_agent_id', 'audit_date', 'photo_url', 'photo_uploaded_at',
        'da_claimed_shampoo', 'da_claimed_pomade', 'da_claimed_conditioner',
        'im_counted_shampoo', 'im_counted_pomade', 'im_counted_conditioner',
        'zoho_recorded_shampoo', 'zoho_recorded_pomade', 'zoho_recorded_conditioner',
        'is_match', 'status', 'notes'
    ];

    protected $casts = [
        'audit_date' => 'date',
        'photo_uploaded_at' => 'datetime',
        'is_match' => 'boolean'
    ];

    public function deliveryAgent() { return $this->belongsTo(DeliveryAgent::class); }
    public function scopeToday($query) { return $query->where('audit_date', today()); }
    public function getPenaltyAmountAttribute() { return $this->is_match === false ? 2500 : 0; }
}
