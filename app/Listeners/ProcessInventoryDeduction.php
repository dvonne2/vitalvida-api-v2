<?php

namespace App\Listeners;

use App\Events\DeliveryConfirmed;
use App\Services\InventoryService;
use App\Services\PaymentVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProcessInventoryDeduction
{
    private $inventoryService;
    private $paymentService;

    public function __construct(
        InventoryService $inventoryService,
        PaymentVerificationService $paymentService
    ) {
        $this->inventoryService = $inventoryService;
        $this->paymentService = $paymentService;
    }

    public function handle(DeliveryConfirmed $event)
    {
        Log::info('Processing automatic inventory deduction', [
            'order_number' => $event->orderNumber
        ]);

        try {
            // Get order items
            $orderItems = DB::table('order_items')
                ->where('order_number', $event->orderNumber)
                ->get();

            // Process each item
            foreach ($orderItems as $item) {
                $this->processItemDeduction($item, $event);
            }

            // Mark as processed
            DB::table('orders')
                ->where('order_number', $event->orderNumber)
                ->update([
                    'inventory_processed' => true,
                    'inventory_processed_at' => now()
                ]);

            Log::info('Automatic inventory deduction completed', [
                'order_number' => $event->orderNumber,
                'items_count' => count($orderItems)
            ]);

        } catch (\Exception $e) {
            Log::error('Automatic inventory deduction failed', [
                'order_number' => $event->orderNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processItemDeduction(object $item, DeliveryConfirmed $event): void
    {
        // Get BIN location for item
        $binLocation = DB::table('inventory_cache')
            ->join('bin_locations', 'inventory_cache.bin_id', '=', 'bin_locations.bin_id')
            ->where('inventory_cache.item_id', $item->item_id)
            ->where('inventory_cache.available_stock', '>', 0)
            ->where('bin_locations.is_active', true)
            ->select('bin_locations.*')
            ->first();

        if (!$binLocation) {
            Log::warning('No BIN found for item', ['item_id' => $item->item_id]);
            return;
        }

        // Set user context
        Auth::loginUsingId($event->confirmedBy);

        // Prepare deduction data
        $deductionData = [
            'order_number' => $event->orderNumber,
            'item_id' => $item->item_id,
            'bin_id' => $binLocation->bin_id,
            'quantity' => $item->quantity,
            'reason' => 'order_fulfillment',
            'warehouse_id' => $binLocation->warehouse_id
        ];

        // Process deduction
        $this->inventoryService->deductInventory($deductionData);
    }
}
