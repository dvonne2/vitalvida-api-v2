<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Vitamins',
                'description' => 'Essential vitamins and supplements for health and wellness',
                'sort_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Supplements',
                'description' => 'Nutritional supplements and dietary aids',
                'sort_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Herbal Supplements',
                'description' => 'Natural herbal remedies and traditional medicines',
                'sort_order' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Hair Care',
                'description' => 'Hair care products and treatments',
                'sort_order' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Skin Care',
                'description' => 'Skin care products and treatments',
                'sort_order' => 5,
                'is_active' => true
            ],
            [
                'name' => 'Weight Management',
                'description' => 'Products for weight loss and management',
                'sort_order' => 6,
                'is_active' => true
            ],
            [
                'name' => 'Energy & Performance',
                'description' => 'Energy boosters and performance enhancers',
                'sort_order' => 7,
                'is_active' => true
            ],
            [
                'name' => 'Immune Support',
                'description' => 'Products to boost immune system',
                'sort_order' => 8,
                'is_active' => true
            ],
            [
                'name' => 'Digestive Health',
                'description' => 'Products for digestive health and gut support',
                'sort_order' => 9,
                'is_active' => true
            ],
            [
                'name' => 'Bone & Joint Health',
                'description' => 'Products for bone strength and joint health',
                'sort_order' => 10,
                'is_active' => true
            ]
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }

        // Create subcategories
        $vitaminsCategory = Category::where('name', 'Vitamins')->first();
        if ($vitaminsCategory) {
            $subcategories = [
                [
                    'name' => 'Vitamin C',
                    'description' => 'Vitamin C supplements and products',
                    'parent_id' => $vitaminsCategory->id,
                    'sort_order' => 1,
                    'is_active' => true
                ],
                [
                    'name' => 'Vitamin D',
                    'description' => 'Vitamin D supplements and products',
                    'parent_id' => $vitaminsCategory->id,
                    'sort_order' => 2,
                    'is_active' => true
                ],
                [
                    'name' => 'Vitamin B Complex',
                    'description' => 'B-complex vitamin supplements',
                    'parent_id' => $vitaminsCategory->id,
                    'sort_order' => 3,
                    'is_active' => true
                ]
            ];

            foreach ($subcategories as $subcategoryData) {
                Category::create($subcategoryData);
            }
        }

        $this->command->info('Categories seeded successfully!');
    }
} 