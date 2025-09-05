<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function getInsights()
    {
        $insights = [
            'top_stocked_products' => [
                ['id' => 1, 'name' => 'Bandages', 'stock_count' => 850],
                ['id' => 2, 'name' => 'Aspirin', 'stock_count' => 750],
                ['id' => 3, 'name' => 'Thermometers', 'stock_count' => 680],
                ['id' => 4, 'name' => 'Face Masks', 'stock_count' => 620],
                ['id' => 5, 'name' => 'Hand Sanitizer', 'stock_count' => 580]
            ],
            'lowest_stock_products' => [
                ['id' => 15, 'name' => 'Blood Pressure Monitor', 'stock_count' => 2],
                ['id' => 22, 'name' => 'Wheelchair', 'stock_count' => 3],
                ['id' => 8, 'name' => 'Nebulizer', 'stock_count' => 5],
                ['id' => 12, 'name' => 'Oxygen Tank', 'stock_count' => 8],
                ['id' => 19, 'name' => 'Crutches', 'stock_count' => 12]
            ],
            'total_products' => 156,
            'stock_movements_this_week' => 47,
            'potential_hoarding_bins' => [
                ['id' => 'BIN-A12', 'location' => 'Warehouse A - Row 1', 'days_without_outflow' => 9],
                ['id' => 'BIN-C05', 'location' => 'Warehouse C - Row 2', 'days_without_outflow' => 12],
                ['id' => 'BIN-B08', 'location' => 'Warehouse B - Row 3', 'days_without_outflow' => 7]
            ],
            'generated_at' => now()->toISOString()
        ];

        return response()->json([
            'success' => true,
            'data' => $insights
        ]);
    }

    public function getAdjustmentsLog()
    {
        $adjustments = [
            [
                'id' => 3001,
                'bin_id' => 601,
                'product' => 'Fulani Shampoo',
                'previous_qty' => 45,
                'new_qty' => 40,
                'adjustment_amount' => -5,
                'adjusted_by' => 'Benjamin',
                'reason' => 'Confirmed overcount after photo verification',
                'approved_by' => 'Oladapo FC',
                'timestamp' => '2025-07-09 16:02:00',
                'adjustment_type' => 'correction',
                'status' => 'approved'
            ],
            [
                'id' => 3002,
                'bin_id' => 602,
                'product' => 'Fulani Pomade',
                'previous_qty' => 30,
                'new_qty' => 33,
                'adjustment_amount' => 3,
                'adjusted_by' => 'Benjamin',
                'reason' => 'DA returned extra units after delivery completion',
                'approved_by' => 'Oladapo FC',
                'timestamp' => '2025-07-08 10:44:00',
                'adjustment_type' => 'return',
                'status' => 'approved'
            ],
            [
                'id' => 3003,
                'bin_id' => 345,
                'product' => 'Vitamin C Tablets',
                'previous_qty' => 120,
                'new_qty' => 115,
                'adjustment_amount' => -5,
                'adjusted_by' => 'Sarah Chen',
                'reason' => 'Damaged units removed from inventory',
                'approved_by' => 'Oladapo FC',
                'timestamp' => '2025-07-08 09:15:00',
                'adjustment_type' => 'write-off',
                'status' => 'approved'
            ]
        ];

        $summary = [
            'total_adjustments' => count($adjustments),
            'net_adjustment' => array_sum(array_column($adjustments, 'adjustment_amount')),
            'approval_rate' => '100%'
        ];

        return response()->json([
            'status' => 'success',
            'data' => $adjustments,
            'summary' => $summary
        ]);
    }
}
