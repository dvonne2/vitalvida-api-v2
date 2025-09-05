<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'header_text', 'sub_header_text', 'fields_config',
        'products', 'payment_methods', 'delivery_options', 'thank_you_message',
        'background_color', 'primary_color', 'font_family', 'headline_font',
        'show_country_code', 'require_email', 'honeypot_enabled', 'webhook_url',
        'is_active', 'total_submissions', 'last_submission_at'
    ];

    protected $casts = [
        'fields_config' => 'array',
        'products' => 'array',
        'payment_methods' => 'array',
        'delivery_options' => 'array',
        'show_country_code' => 'boolean',
        'require_email' => 'boolean',
        'honeypot_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_submission_at' => 'datetime'
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
        });
    }

    public function getDefaultFieldsConfig()
    {
        return [
            'name' => ['required' => true, 'show' => true, 'label' => 'Full Name'],
            'phone' => ['required' => true, 'show' => true, 'label' => 'Phone Number'],
            'email' => ['required' => false, 'show' => true, 'label' => 'Email Address'],
            'state' => ['required' => true, 'show' => true, 'label' => 'Your Delivery State'],
            'address' => ['required' => true, 'show' => true, 'label' => 'Delivery Address'],
            'promo_code' => ['required' => false, 'show' => true, 'label' => 'Promo Code'],
            'source' => ['required' => true, 'show' => true, 'label' => 'How did you hear about us?']
        ];
    }

    public function getDefaultProducts()
    {
        return [
            [
                'name' => 'SELF LOVE PLUS',
                'description' => 'Buy 1 shampoo, 1 pomade plus 1 conditioner',
                'price' => 32750,
                'active' => true
            ],
            [
                'name' => 'SELF LOVE B2GOF',
                'description' => 'Buy 2 shampoo, 2 pomade & Get 1 shampoo, 1 pomade FREE',
                'price' => 52750,
                'active' => true
            ],
            [
                'name' => 'FAMILY SAVES',
                'description' => 'Buy 6 shampoos, 6 pomades, 6 conditioners and Get 4 shampoos, 4 pomades, 4 conditioners FREE',
                'price' => 215750,
                'active' => true
            ]
        ];
    }

    public function getDefaultPaymentMethods()
    {
        return [
            [
                'name' => 'Pay on Delivery',
                'description' => 'Pay cash when your order arrives',
                'badge' => 'Most Popular',
                'active' => true
            ],
            [
                'name' => 'Pay Before Delivery',
                'description' => 'Bank transfer or online payment',
                'badge' => 'Secure',
                'active' => true
            ]
        ];
    }

    public function getDefaultDeliveryOptions()
    {
        return [
            [
                'name' => 'Same-Day Delivery',
                'description' => 'Orders before 12 noon only',
                'price' => 4000,
                'active' => true
            ],
            [
                'name' => 'Express Delivery',
                'description' => '1-2 days delivery',
                'price' => 3500,
                'active' => true
            ],
            [
                'name' => 'Standard Delivery',
                'description' => '3-5 days delivery',
                'price' => 2500,
                'active' => true
            ]
        ];
    }
} 