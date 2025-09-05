<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Form;

class FormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Form::create([
            'name' => 'VitalVida Hair Care Order Form',
            'slug' => 'vitalvida-hair-care-order',
            'header_text' => 'Order Your VitalVida Hair Care Products',
            'sub_header_text' => 'Transform your hair with our premium natural products',
            'fields_config' => [
                'name' => ['required' => true, 'show' => true, 'label' => 'Full Name'],
                'phone' => ['required' => true, 'show' => true, 'label' => 'Phone Number'],
                'email' => ['required' => false, 'show' => true, 'label' => 'Email Address'],
                'state' => ['required' => true, 'show' => true, 'label' => 'Your Delivery State'],
                'address' => ['required' => true, 'show' => true, 'label' => 'Delivery Address'],
                'promo_code' => ['required' => false, 'show' => true, 'label' => 'Promo Code'],
                'source' => ['required' => true, 'show' => true, 'label' => 'How did you hear about us?']
            ],
            'products' => [
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
            ],
            'payment_methods' => [
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
            ],
            'delivery_options' => [
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
            ],
            'thank_you_message' => 'Thanks! Your order has been received. One of our team members will call you shortly to confirm.',
            'background_color' => '#f8f9fa',
            'primary_color' => '#DAA520',
            'font_family' => 'Montserrat',
            'headline_font' => 'Playfair Display',
            'show_country_code' => true,
            'require_email' => false,
            'honeypot_enabled' => true,
            'is_active' => true
        ]);

        Form::create([
            'name' => 'Special Promo Form',
            'slug' => 'special-promo-form',
            'header_text' => 'Limited Time Offer - 50% Off!',
            'sub_header_text' => 'Don\'t miss this amazing deal on our premium products',
            'fields_config' => [
                'name' => ['required' => true, 'show' => true, 'label' => 'Full Name'],
                'phone' => ['required' => true, 'show' => true, 'label' => 'Phone Number'],
                'email' => ['required' => true, 'show' => true, 'label' => 'Email Address'],
                'state' => ['required' => true, 'show' => true, 'label' => 'Your Delivery State'],
                'address' => ['required' => true, 'show' => true, 'label' => 'Delivery Address'],
                'promo_code' => ['required' => false, 'show' => false, 'label' => 'Promo Code'],
                'source' => ['required' => false, 'show' => true, 'label' => 'How did you hear about us?']
            ],
            'products' => [
                [
                    'name' => 'PROMO PACK',
                    'description' => 'Special promotional package with 50% discount',
                    'price' => 16375,
                    'active' => true
                ]
            ],
            'payment_methods' => [
                [
                    'name' => 'Pay on Delivery',
                    'description' => 'Pay cash when your order arrives',
                    'badge' => 'Most Popular',
                    'active' => true
                ]
            ],
            'delivery_options' => [
                [
                    'name' => 'Free Delivery',
                    'description' => 'Free delivery for this promo',
                    'price' => 0,
                    'active' => true
                ]
            ],
            'thank_you_message' => 'Thank you for your order! Our team will contact you within 24 hours.',
            'background_color' => '#fff3cd',
            'primary_color' => '#dc3545',
            'font_family' => 'Arial',
            'headline_font' => 'Georgia',
            'show_country_code' => true,
            'require_email' => true,
            'honeypot_enabled' => true,
            'is_active' => true
        ]);
    }
} 