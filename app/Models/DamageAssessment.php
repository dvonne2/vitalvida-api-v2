<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamageAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_item_id',
        'assessed_by',
        'damage_type',
        'damage_severity',
        'damage_percentage',
        'salvage_value',
        'repair_cost',
        'disposal_cost',
        'assessment_notes',
        'photo_evidence',
        'recommended_disposition',
        'assessment_metadata',
        'assessed_at'
    ];

    protected $casts = [
        'damage_percentage' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'repair_cost' => 'decimal:2',
        'disposal_cost' => 'decimal:2',
        'photo_evidence' => 'array',
        'assessment_metadata' => 'array',
        'assessed_at' => 'datetime'
    ];

    // Relationships
    public function returnItem()
    {
        return $this->belongsTo(ReturnItem::class, 'return_item_id');
    }

    public function assessedBy()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // Damage type constants
    const TYPE_PHYSICAL = 'physical';
    const TYPE_WATER = 'water';
    const TYPE_CONTAMINATION = 'contamination';
    const TYPE_EXPIRY = 'expiry';
    const TYPE_PACKAGING = 'packaging';
    const TYPE_TEMPERATURE = 'temperature';
    const TYPE_CHEMICAL = 'chemical';

    // Severity constants
    const SEVERITY_MINOR = 'minor';
    const SEVERITY_MODERATE = 'moderate';
    const SEVERITY_MAJOR = 'major';
    const SEVERITY_TOTAL = 'total';

    // Accessors
    public function getSeverityColorAttribute()
    {
        $colors = [
            'minor' => 'green',
            'moderate' => 'yellow',
            'major' => 'orange',
            'total' => 'red'
        ];

        return $colors[$this->damage_severity] ?? 'gray';
    }

    public function getRecoveryRateAttribute()
    {
        $originalValue = $this->returnItem->estimated_value ?? 0;
        
        if ($originalValue == 0) {
            return 0;
        }

        return round(($this->salvage_value / $originalValue) * 100, 2);
    }
}
