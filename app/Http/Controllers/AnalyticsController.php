<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function getAgingReport()
    {
        $agingData = [
            [
                'bin_id' => 410,
                'product' => 'Fulani Conditioner',
                'last_movement' => '2025-06-15',
                'days_in_bin' => 24,
                'quantity' => 45,
                'location' => 'Warehouse A - Row 2',
                'aging_category' => 'slow_moving',
                'value' => 1350.00
            ],
            [
                'bin_id' => 411,
                'product' => 'Fulani Shampoo',
                'last_movement' => '2025-07-07',
                'days_in_bin' => 2,
                'quantity' => 78,
                'location' => 'Warehouse B - Row 1',
                'aging_category' => 'fast_moving',
                'value' => 2340.00
            ],
            [
                'bin_id' => 412,
                'product' => 'Fulani Pomade',
                'last_movement' => '2025-06-01',
                'days_in_bin' => 38,
                'quantity' => 32,
                'location' => 'Warehouse C - Row 3',
                'aging_category' => 'very_slow',
                'value' => 960.00
            ],
            [
                'bin_id' => 318,
                'product' => 'Hand Sanitizer 500ml',
                'last_movement' => '2025-05-20',
                'days_in_bin' => 50,
                'quantity' => 65,
                'location' => 'Warehouse B - Row 4',
                'aging_category' => 'stagnant',
                'value' => 975.00
            ]
        ];

        $summary = [
            'total_bins_analyzed' => count($agingData),
            'average_days_in_bin' => 28.5,
            'total_inventory_value' => array_sum(array_column($agingData, 'value')),
            'risk_items' => 2
        ];

        return response()->json([
            'status' => 'success',
            'data' => $agingData,
            'summary' => $summary
        ]);
    }
}
