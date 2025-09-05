<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'zoho_lead_id', 'lead_number', 'customer_name', 'customer_phone', 
        'customer_email', 'product', 'promo_code', 'payment_method', 'delivery_preference',
        'delivery_cost', 'address', 'status', 'source', 'notes', 'potential_value',
        'assigned_to', 'assigned_at', 'last_contact_at', 'whatsapp_otp', 
        'whatsapp_verified', 'whatsapp_verified_at', 'interaction_history'
    ];

    protected $casts = [
        'potential_value' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'assigned_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'whatsapp_verified' => 'boolean',
        'whatsapp_verified_at' => 'datetime',
        'interaction_history' => 'array'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($lead) {
            if (empty($lead->lead_number)) {
                $lead->lead_number = 'LEAD-' . date('Ymd') . '-' . str_pad(static::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'new' => 'bg-primary',
            'assigned' => 'bg-info',
            'contacted' => 'bg-warning',
            'quoted' => 'bg-secondary',
            'converted' => 'bg-success',
            'closed' => 'bg-dark',
            'lost' => 'bg-danger'
        ];

        return $badges[$this->status] ?? 'bg-secondary';
    }

    public function getFormattedPhoneAttribute()
    {
        // Format phone number for display
        $phone = $this->customer_phone;
        if (strlen($phone) > 10) {
            return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7, 3) . ' ' . substr($phone, 10);
        }
        return $phone;
    }

    public function getTotalValueAttribute()
    {
        // Calculate total order value (product price + delivery cost)
        $productPrice = 0;
        if ($this->form && $this->form->products) {
            foreach ($this->form->products as $product) {
                if ($product['name'] === $this->product) {
                    $productPrice = $product['price'];
                    break;
                }
            }
        }
        
        return $productPrice + $this->delivery_cost;
    }
} 