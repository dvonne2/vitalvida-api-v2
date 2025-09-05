<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\BinLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InventoryMovementService
{
    /**
     * Log an inventory movement
     */
    public function logMovement(array $data): InventoryMovement
    {
        // Get current stock before movement
        $currentStock = $this->getCurrentStock($data['item_id'], $data['bin_id']);
        
        // Get BIN details
        $binLocation = BinLocation::where('bin_id', $data['bin_id'])->first();
        
        // Calculate quantities
        $quantityChanged = $data['quantity_changed'];
        $quantityBefore = $currentStock;
        $quantityAfter = $currentStock + $quantityChanged;

        // Create movement record
        $movementData = [
            'movement_id' => $this->generateMovementId(),
            'order_number' => $data['order_number'] ?? null,
            'item_id' => $data['item_id'],
            'item_name' => $data['item_name'] ?? null,
            'item_sku' => $data['item_sku'] ?? null,
            
            // BIN information
            'bin_id' => $data['bin_id'],
            'bin_name' => $binLocation->bin_name ?? null,
            'warehouse_id' => $binLocation->warehouse_id ?? $data['warehouse_id'] ?? null,
            'zone' => $binLocation->zone ?? null,
            'aisle' => $binLocation->aisle ?? null,
            'rack' => $binLocation->rack ?? null,
            'shelf' => $binLocation->shelf ?? null,
            
            // Movement details
            'movement_type' => $data['movement_type'],
            'quantity_before' => $quantityBefore,
            'quantity_changed' => $quantityChanged,
            'quantity_after' => $quantityAfter,
            
            // Source information
            'source_type' => $data['source_type'],
            'source_reference' => $data['source_reference'] ?? null,
            'source_details' => $data['source_details'] ?? null,
            
            // User information
            'user_id' => Auth::id(),
            'performed_by' => Auth::user()->name ?? 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // Integration info
            'zoho_transaction_id' => $data['zoho_transaction_id'] ?? null,
            'zoho_response' => $data['zoho_response'] ?? null,
            'synced_to_zoho' => $data['synced_to_zoho'] ?? false,
            'synced_at' => $data['synced_at'] ?? null,
            
            // Additional info
            'status' => $data['status'] ?? 'completed',
            'notes' => $data['notes'] ?? null,
            'reason' => $data['reason'] ?? null,
            'movement_at' => now()
        ];

        return InventoryMovement::create($movementData);
    }

    /**
     * Log outbound movement (stock removal)
     */
    public function logOutbound(array $data): InventoryMovement
    {
        $data['movement_type'] = 'outbound';
        $data['quantity_changed'] = -abs($data['quantity']); // Ensure negative
        
        return $this->logMovement($data);
    }

    /**
     * Log inbound movement (stock addition)
     */
    public function logInbound(array $data): InventoryMovement
    {
        $data['movement_type'] = 'inbound';
        $data['quantity_changed'] = abs($data['quantity']); // Ensure positive
        
        return $this->logMovement($data);
    }

    /**
     * Get movement history for a BIN
     */
    public function getBinMovementHistory(string $binId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = InventoryMovement::where('bin_id', $binId)
            ->with(['user'])
            ->orderBy('movement_at', 'desc');

        // Apply filters
        if (isset($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (isset($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('movement_at', [$filters['start_date'], $filters['end_date']]);
        }

        if (isset($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get movement history for an item
     */
    public function getItemMovementHistory(string $itemId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = InventoryMovement::where('item_id', $itemId)
            ->with(['user', 'binLocation'])
            ->orderBy('movement_at', 'desc');

        if (isset($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (isset($filters['bin_id'])) {
            $query->where('bin_id', $filters['bin_id']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get movement summary for reporting
     */
    public function getMovementSummary(array $filters = []): array
    {
        $query = InventoryMovement::query();

        // Apply date filter
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('movement_at', [$filters['start_date'], $filters['end_date']]);
        }

        // Get totals by movement type
        $byType = $query->select('movement_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(ABS(quantity_changed)) as total_quantity'))
            ->groupBy('movement_type')
            ->get()
            ->keyBy('movement_type');

        // Get totals by source type
        $bySource = $query->select('source_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(ABS(quantity_changed)) as total_quantity'))
            ->groupBy('source_type')
            ->get()
            ->keyBy('source_type');

        return [
            'by_type' => $byType,
            'by_source' => $bySource,
            'total_movements' => $query->count()
        ];
    }

    private function generateMovementId(): string
    {
        return 'MOV-' . strtoupper(Str::random(8)) . '-' . now()->format('YmdHis');
    }

    private function getCurrentStock(string $itemId, string $binId): int
    {
        return DB::table('inventory_cache')
            ->where('item_id', $itemId)
            ->where('bin_id', $binId)
            ->value('available_stock') ?? 0;
    }
}
