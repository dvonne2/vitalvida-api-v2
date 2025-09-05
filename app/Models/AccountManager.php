<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountManager extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'rating', 'status', 'specialties', 'conversion_rate',
        'avg_assignment_time', 'current_load', 'region'
    ];

    protected $casts = [
        'specialties' => 'json',
        'rating' => 'decimal:1'
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')->where('current_load', '<', 10);
    }

    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->rating, 1) . '/5.0';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->current_load < 10;
    }

    public function getSpecialtiesList(): array
    {
        return $this->specialties ?? [];
    }

    public function hasSpecialty(string $specialty): bool
    {
        return in_array($specialty, $this->getSpecialtiesList());
    }

    public function incrementLoad(): void
    {
        $this->increment('current_load');
    }

    public function decrementLoad(): void
    {
        $this->decrement('current_load');
    }

    public function getPerformanceScore(): float
    {
        return ($this->rating * 0.4) + ($this->conversion_rate * 0.4) + (100 - $this->avg_assignment_time * 2);
    }
}
