<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SealLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'custody_transfer_id',
        'seal_id',
        'seal_type',
        'seal_status',
        'checked_by',
        'check_location',
        'check_notes',
        'anomaly_detected',
        'anomaly_details',
        'photo_evidence',
        'gps_coordinates',
        'checked_at'
    ];

    protected $casts = [
        'anomaly_detected' => 'boolean',
        'anomaly_details' => 'array',
        'photo_evidence' => 'array',
        'gps_coordinates' => 'array',
        'checked_at' => 'datetime'
    ];

    // Relationships
    public function custodyTransfer()
    {
        return $this->belongsTo(CustodyTransfer::class, 'custody_transfer_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // Seal status constants
    const STATUS_INTACT = 'intact';
    const STATUS_BROKEN = 'broken';
    const STATUS_TAMPERED = 'tampered';
    const STATUS_MISSING = 'missing';
    const STATUS_REPLACED = 'replaced';

    // Seal types
    const TYPE_SECURITY = 'security';
    const TYPE_TAMPER_EVIDENT = 'tamper_evident';
    const TYPE_RFID = 'rfid';
    const TYPE_BARCODE = 'barcode';

    // Scopes
    public function scopeIntact($query)
    {
        return $query->where('seal_status', self::STATUS_INTACT);
    }

    public function scopeCompromised($query)
    {
        return $query->whereIn('seal_status', [
            self::STATUS_BROKEN,
            self::STATUS_TAMPERED,
            self::STATUS_MISSING
        ]);
    }

    public function scopeWithAnomalies($query)
    {
        return $query->where('anomaly_detected', true);
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        $colors = [
            'intact' => 'green',
            'broken' => 'red',
            'tampered' => 'orange',
            'missing' => 'red',
            'replaced' => 'blue'
        ];

        return $colors[$this->seal_status] ?? 'gray';
    }

    public function getRiskLevelAttribute()
    {
        if ($this->seal_status === self::STATUS_INTACT && !$this->anomaly_detected) {
            return 'low';
        }

        if (in_array($this->seal_status, [self::STATUS_BROKEN, self::STATUS_MISSING])) {
            return 'critical';
        }

        if ($this->seal_status === self::STATUS_TAMPERED || $this->anomaly_detected) {
            return 'high';
        }

        return 'medium';
    }
}
