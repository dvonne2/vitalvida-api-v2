<?php

namespace App\Services;

use App\Models\InventoryAudit;
use App\Models\Order;
use App\Models\BinLocation;
use App\Exceptions\UnauthorizedInventoryException;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class InventoryControlService
{
    private $zohoApiUrl;
    private $zohoHeaders;
    
    public function __construct()
    {
        $this->zohoApiUrl = config('zoho.inventory_api_url');
        $this->zohoHeaders = [
            'Authorization' => 'Zoho-oauthtoken ' . config('zoho.access_token'),
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * MAIN DEDUCTION METHOD - Only way to deduct inventory
     */
    public function deductInventory(array $deductionData): array
    {
        // Validate all required fields
        $this->validateDeductionRequest($deductionData);
        
        DB::beginTransaction();
        
        try {
            // Step 1: Pre-deduction validation
            $this->validateBusinessRules($deductionData);
            
            // Step 2: Check current stock levels
            $currentStock = $this->getCurrentBinStock($deductionData['bin_id'], $deductionData['item_id']);
            
            if ($currentStock < $deductionData['quantity']) {
                throw new InsufficientStockException("Insufficient stock in BIN {$deductionData['bin_id']}");
            }
            
            // Step 3: Execute deduction via Zoho API
            $zohoResponse = $this->executeZohoDeduction($deductionData);
            
            // Step 4: Log the deduction
            $auditLog = $this->logDeduction($deductionData, $zohoResponse);
            
            // Step 5: Update local cache/tracking
            $this->updateLocalInventoryCache($deductionData);
            
            DB::commit();
            
            return [
                'success' => true,
                'audit_id' => $auditLog->id,
                'zoho_adjustment_id' => $zohoResponse['inventory_adjustment']['inventory_adjustment_id'],
                'remaining_stock' => $currentStock - $deductionData['quantity']
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Inventory deduction failed', [
                'error' => $e->getMessage(),
                'data' => $deductionData
            ]);
            throw $e;
        }
    }

    /**
     * Validate deduction request has all required fields
     */
    private function validateDeductionRequest(array $data): void
    {
        $required = ['order_number', 'item_id', 'bin_id', 'quantity', 'user_id', 'reason'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Validate business rules before allowing deduction
     */
    private function validateBusinessRules(array $data): void
    {
        // Rule 1: Order must exist and be paid
        $order = Order::where('order_number', $data['order_number'])->first();
        if (!$order) {
            throw new UnauthorizedInventoryException("Order {$data['order_number']} not found");
        }
        
        if ($order->payment_status !== 'paid') {
            throw new UnauthorizedInventoryException("Order {$data['order_number']} is not paid");
        }
        
        // Rule 2: User must be authorized
        if (!$this->isUserAuthorized($data['user_id'], $data['reason'])) {
            throw new UnauthorizedInventoryException("User {$data['user_id']} not authorized for inventory deduction");
        }
        
        // Rule 3: BIN must exist and be active
        $bin = BinLocation::where('bin_id', $data['bin_id'])->where('is_active', true)->first();
        if (!$bin) {
            throw new UnauthorizedInventoryException("BIN {$data['bin_id']} not found or inactive");
        }
        
        // Rule 4: Reason must be valid business event
        $validReasons = ['package_dispatch', 'order_fulfillment', 'quality_control', 'return_processing'];
        if (!in_array($data['reason'], $validReasons)) {
            throw new UnauthorizedInventoryException("Invalid deduction reason: {$data['reason']}");
        }
    }

    /**
     * Check if user is authorized for inventory operations
     */
    private function isUserAuthorized(int $userId, string $reason): bool
    {
        // Implement your authorization logic here
        $user = \App\Models\User::find($userId);
        
        if (!$user) return false;
        
        // Check role-based permissions
        return $user->hasPermission('inventory.deduct') || 
               $user->hasRole(['warehouse_manager', 'fulfillment_agent']);
    }

    /**
     * Get current stock levels from Zoho
     */
    private function getCurrentBinStock(string $binId, string $itemId): int
    {
        $response = Http::withHeaders($this->zohoHeaders)
            ->get("{$this->zohoApiUrl}/inventory/bins/{$binId}/items/{$itemId}");
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch current stock from Zoho");
        }
        
        $data = $response->json();
        return $data['bin']['available_stock'] ?? 0;
    }

    /**
     * Execute the actual deduction via Zoho API
     */
    private function executeZohoDeduction(array $data): array
    {
        $adjustmentData = [
            'reason' => $data['reason'],
            'date' => now()->format('Y-m-d'),
            'reference_number' => $data['order_number'],
            'description' => "Laravel controlled deduction - {$data['reason']}",
            'line_items' => [
                [
                    'item_id' => $data['item_id'],
                    'bin_id' => $data['bin_id'],
                    'quantity_adjusted' => -$data['quantity'], // Negative for deduction
                    'warehouse_id' => $data['warehouse_id'] ?? config('zoho.default_warehouse_id')
                ]
            ]
        ];
        
        $response = Http::withHeaders($this->zohoHeaders)
            ->post("{$this->zohoApiUrl}/inventory/adjustments", $adjustmentData);
        
        if (!$response->successful()) {
            throw new \Exception("Zoho API deduction failed: " . $response->body());
        }
        
        return $response->json();
    }

    /**
     * Log deduction for audit trail
     */
    private function logDeduction(array $data, array $zohoResponse): InventoryAudit
    {
        return InventoryAudit::create([
            'order_number' => $data['order_number'],
            'item_id' => $data['item_id'],
            'bin_id' => $data['bin_id'],
            'quantity_deducted' => $data['quantity'],
            'reason' => $data['reason'],
            'user_id' => $data['user_id'],
            'zoho_adjustment_id' => $zohoResponse['inventory_adjustment']['inventory_adjustment_id'],
            'zoho_response' => json_encode($zohoResponse),
            'deducted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Update local inventory cache
     */
    private function updateLocalInventoryCache(array $data): void
    {
        // Update your local inventory tracking table
        DB::table('inventory_cache')
            ->where('bin_id', $data['bin_id'])
            ->where('item_id', $data['item_id'])
            ->decrement('available_stock', $data['quantity']);
    }
}
