<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StockDeductionService
{
    public function deductStock(Order $order, int $deliveryAgentId): array
    {
        try {
            DB::beginTransaction();

            $result = [
                'success' => false,
                'items_deducted' => [],
                'failures' => []
            ];

            foreach ($order->items as $item) {
                $itemName = $item['name'];
                $quantity = $item['quantity'];

                try {
                    $binItem = DB::table('delivery_agent_bins')
                        ->where('delivery_agent_id', $deliveryAgentId)
                        ->where('item_name', $itemName)
                        ->lockForUpdate()
                        ->first();

                    if (!$binItem) {
                        $result['failures'][] = [
                            'item' => $itemName,
                            'reason' => 'Item not found in delivery agent BIN'
                        ];
                        continue;
                    }

                    if ($binItem->quantity < $quantity) {
                        $result['failures'][] = [
                            'item' => $itemName,
                            'reason' => "Insufficient stock. Available: {$binItem->quantity}, Required: {$quantity}"
                        ];
                        continue;
                    }

                    $newQuantity = $binItem->quantity - $quantity;
                    
                    DB::table('delivery_agent_bins')
                        ->where('id', $binItem->id)
                        ->update([
                            'quantity' => $newQuantity,
                            'updated_at' => now()
                        ]);

                    DB::table('stock_movements')->insert([
                        'delivery_agent_id' => $deliveryAgentId,
                        'item_name' => $itemName,
                        'movement_type' => 'delivery',
                        'quantity_change' => -$quantity,
                        'previous_quantity' => $binItem->quantity,
                        'new_quantity' => $newQuantity,
                        'order_number' => $order->order_number,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    $result['items_deducted'][] = [
                        'item' => $itemName,
                        'quantity_deducted' => $quantity,
                        'remaining_stock' => $newQuantity
                    ];

                } catch (\Exception $e) {
                    $result['failures'][] = [
                        'item' => $itemName,
                        'reason' => 'Database error: ' . $e->getMessage()
                    ];
                }
            }

            $totalItems = count($order->items);
            $successfulDeductions = count($result['items_deducted']);

            if ($successfulDeductions === $totalItems) {
                $result['success'] = true;
                DB::commit();
                
                Log::info('Stock deduction completed successfully', [
                    'order_number' => $order->order_number,
                    'delivery_agent_id' => $deliveryAgentId,
                    'items_deducted' => $result['items_deducted']
                ]);
            } else {
                DB::rollBack();
                
                Log::warning('Partial or failed stock deduction', [
                    'order_number' => $order->order_number,
                    'delivery_agent_id' => $deliveryAgentId,
                    'successful_deductions' => $successfulDeductions,
                    'total_items' => $totalItems,
                    'failures' => $result['failures']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Stock deduction failed', [
                'order_number' => $order->order_number,
                'delivery_agent_id' => $deliveryAgentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items_deducted' => [],
                'failures' => []
            ];
        }
    }
}
