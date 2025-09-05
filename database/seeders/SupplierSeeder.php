<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'VitalVida Health Solutions',
                'contact_person' => 'Dr. Sarah Johnson',
                'phone' => '+234 801 234 5678',
                'email' => 'contact@vitalvidahealth.com',
                'address' => '123 Health Street, Victoria Island, Lagos',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'products' => ['vitamins', 'supplements', 'herbal_products'],
                'status' => 'active',
                'payment_terms' => 'Net 30',
                'credit_limit' => 500000.00,
                'tax_id' => 'NG123456789',
                'bank_details' => [
                    'bank_name' => 'First Bank of Nigeria',
                    'account_number' => '1234567890',
                    'account_name' => 'VitalVida Health Solutions'
                ],
                'rating' => 4.8
            ],
            [
                'name' => 'Natural Health Products Ltd',
                'contact_person' => 'Mr. Michael Adebayo',
                'phone' => '+234 802 345 6789',
                'email' => 'info@naturalhealthng.com',
                'address' => '456 Wellness Avenue, Ikeja, Lagos',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'products' => ['herbal_supplements', 'organic_products'],
                'status' => 'active',
                'payment_terms' => 'Net 45',
                'credit_limit' => 300000.00,
                'tax_id' => 'NG987654321',
                'bank_details' => [
                    'bank_name' => 'Zenith Bank',
                    'account_number' => '0987654321',
                    'account_name' => 'Natural Health Products Ltd'
                ],
                'rating' => 4.5
            ],
            [
                'name' => 'Premium Vitamins Co.',
                'contact_person' => 'Mrs. Fatima Hassan',
                'phone' => '+234 803 456 7890',
                'email' => 'sales@premiumvitamins.com',
                'address' => '789 Supplement Road, Abuja',
                'city' => 'Abuja',
                'state' => 'FCT',
                'country' => 'Nigeria',
                'products' => ['vitamins', 'minerals', 'amino_acids'],
                'status' => 'active',
                'payment_terms' => 'Net 30',
                'credit_limit' => 400000.00,
                'tax_id' => 'NG456789123',
                'bank_details' => [
                    'bank_name' => 'GT Bank',
                    'account_number' => '1122334455',
                    'account_name' => 'Premium Vitamins Co.'
                ],
                'rating' => 4.7
            ],
            [
                'name' => 'Herbal Remedies International',
                'contact_person' => 'Dr. Oluwaseun Oke',
                'phone' => '+234 804 567 8901',
                'email' => 'orders@herbalremedies.com',
                'address' => '321 Traditional Way, Ibadan',
                'city' => 'Ibadan',
                'state' => 'Oyo',
                'country' => 'Nigeria',
                'products' => ['herbal_medicines', 'traditional_remedies'],
                'status' => 'active',
                'payment_terms' => 'Net 60',
                'credit_limit' => 250000.00,
                'tax_id' => 'NG789123456',
                'bank_details' => [
                    'bank_name' => 'UBA',
                    'account_number' => '5566778899',
                    'account_name' => 'Herbal Remedies International'
                ],
                'rating' => 4.3
            ],
            [
                'name' => 'NutriCare Pharmaceuticals',
                'contact_person' => 'Mr. David Okonkwo',
                'phone' => '+234 805 678 9012',
                'email' => 'info@nutricarepharma.com',
                'address' => '654 Nutrition Boulevard, Port Harcourt',
                'city' => 'Port Harcourt',
                'state' => 'Rivers',
                'country' => 'Nigeria',
                'products' => ['pharmaceuticals', 'supplements', 'medical_devices'],
                'status' => 'active',
                'payment_terms' => 'Net 30',
                'credit_limit' => 600000.00,
                'tax_id' => 'NG321654987',
                'bank_details' => [
                    'bank_name' => 'Access Bank',
                    'account_number' => '9988776655',
                    'account_name' => 'NutriCare Pharmaceuticals'
                ],
                'rating' => 4.9
            ]
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        $this->command->info('Suppliers seeded successfully!');
    }
} 