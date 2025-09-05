<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'has_smartphone', 'has_transportation', 'transportation_type',
        'has_drivers_license', 'can_store_products', 'comfortable_with_portal', 'delivery_areas',
        'has_bank_account', 'bank_name', 'account_number', 'account_name',
        'preferred_communication', 'can_receive_notifications', 'availability_hours',
        'available_weekends', 'available_holidays', 'delivery_experience', 'previous_experience',
        'requirements_score', 'meets_minimum_requirements'
    ];

    protected $casts = [
        'has_smartphone' => 'boolean',
        'has_transportation' => 'boolean',
        'has_drivers_license' => 'boolean',
        'can_store_products' => 'boolean',
        'comfortable_with_portal' => 'boolean',
        'delivery_areas' => 'array',
        'has_bank_account' => 'boolean',
        'can_receive_notifications' => 'boolean',
        'availability_hours' => 'array',
        'available_weekends' => 'boolean',
        'available_holidays' => 'boolean',
        'requirements_score' => 'integer',
        'meets_minimum_requirements' => 'boolean'
    ];

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function getRequirementScore()
    {
        $score = 0;
        
        // Essential Requirements (20 points each)
        $score += $this->has_smartphone ? 20 : 0;
        $score += $this->has_transportation ? 20 : 0;
        $score += $this->has_drivers_license ? 20 : 0;
        $score += $this->can_store_products ? 20 : 0;
        $score += $this->comfortable_with_portal ? 20 : 0;
        
        // Additional Requirements (10 points each)
        $score += $this->has_bank_account ? 10 : 0;
        $score += $this->can_receive_notifications ? 10 : 0;
        $score += $this->available_weekends ? 10 : 0;
        $score += $this->available_holidays ? 10 : 0;
        
        // Experience Bonus (up to 20 points)
        $experienceScore = match($this->delivery_experience) {
            'none' => 0,
            'less_than_1_year' => 5,
            '1_3_years' => 10,
            '3_5_years' => 15,
            '5_plus_years' => 20,
            default => 0
        };
        $score += $experienceScore;
        
        // Delivery Areas Bonus (up to 10 points)
        if ($this->delivery_areas && count($this->delivery_areas) > 0) {
            $score += min(count($this->delivery_areas) * 2, 10);
        }
        
        return $score;
    }

    public function meetsMinimumRequirements()
    {
        return $this->has_smartphone && 
               $this->has_transportation && 
               $this->can_store_products && 
               $this->comfortable_with_portal;
    }

    public function calculateAndSaveScore()
    {
        $score = $this->getRequirementScore();
        $meetsMinimum = $this->meetsMinimumRequirements();
        
        $this->update([
            'requirements_score' => $score,
            'meets_minimum_requirements' => $meetsMinimum
        ]);
        
        return $score;
    }

    public function getTransportationTypeTextAttribute()
    {
        return match($this->transportation_type) {
            'motorcycle' => 'Motorcycle',
            'car' => 'Car',
            'bicycle' => 'Bicycle',
            'tricycle' => 'Tricycle',
            'walking' => 'Walking',
            default => 'Not specified'
        };
    }

    public function getDeliveryExperienceTextAttribute()
    {
        return match($this->delivery_experience) {
            'none' => 'No experience',
            'less_than_1_year' => 'Less than 1 year',
            '1_3_years' => '1-3 years',
            '3_5_years' => '3-5 years',
            '5_plus_years' => '5+ years',
            default => 'Not specified'
        };
    }

    public function getPreferredCommunicationTextAttribute()
    {
        return match($this->preferred_communication) {
            'whatsapp' => 'WhatsApp',
            'phone' => 'Phone Call',
            'email' => 'Email',
            default => 'Not specified'
        };
    }

    public function getRequirementsStatusAttribute()
    {
        if ($this->requirements_score >= 80) {
            return 'excellent';
        } elseif ($this->requirements_score >= 60) {
            return 'good';
        } elseif ($this->requirements_score >= 40) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    public function getRequirementsStatusColorAttribute()
    {
        return match($this->requirements_status) {
            'excellent' => 'green',
            'good' => 'blue',
            'fair' => 'yellow',
            'poor' => 'red',
            default => 'gray'
        };
    }

    public function getMissingRequirementsAttribute()
    {
        $missing = [];
        
        if (!$this->has_smartphone) $missing[] = 'Smartphone';
        if (!$this->has_transportation) $missing[] = 'Transportation';
        if (!$this->has_drivers_license) $missing[] = 'Driver\'s License';
        if (!$this->can_store_products) $missing[] = 'Storage Space';
        if (!$this->comfortable_with_portal) $missing[] = 'Portal Comfort';
        
        return $missing;
    }

    public function getStrengthsAttribute()
    {
        $strengths = [];
        
        if ($this->has_smartphone) $strengths[] = 'Has Smartphone';
        if ($this->has_transportation) $strengths[] = 'Has Transportation';
        if ($this->has_drivers_license) $strengths[] = 'Has Driver\'s License';
        if ($this->can_store_products) $strengths[] = 'Can Store Products';
        if ($this->comfortable_with_portal) $strengths[] = 'Portal Comfortable';
        if ($this->has_bank_account) $strengths[] = 'Has Bank Account';
        if ($this->available_weekends) $strengths[] = 'Available Weekends';
        if ($this->available_holidays) $strengths[] = 'Available Holidays';
        
        return $strengths;
    }
}
