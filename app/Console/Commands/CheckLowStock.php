<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AlertService;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock 
                            {--state=all : Check specific state or all states}
                            {--threshold=10 : Override default threshold}
                            {--update-status : Update database status}
                            {--send-alerts : Send email alerts}
                            {--test-email= : Send test email to address}';

    protected $description = 'Check for low stock products with email alerts';

    private $alertService;

    public function __construct(AlertService $alertService)
    {
        parent::__construct();
        $this->alertService = $alertService;
    }

    public function handle()
    {
        $this->info('🔍 Starting Low Stock Check...');
        
        $state = $this->option('state');
        $threshold = $this->option('threshold');
        $updateStatus = $this->option('update-status');
        $sendAlerts = $this->option('send-alerts');
        $testEmail = $this->option('test-email');

        // Handle test email
        if ($testEmail) {
            return $this->sendTestEmail($testEmail);
        }
        
        $this->info("🌍 State: {$state}, Threshold: {$threshold}");
        
        $stockLevels = $this->calculateCurrentStock();
        
        $lowStockProducts = [];
        $checkedCount = 0;
        $lowStockCount = 0;
        
        foreach ($stockLevels as $stock) {
            $checkedCount++;
            
            $effectiveThreshold = $threshold ?: ($stock->low_stock_threshold ?? 10);
            
            if ($stock->current_stock <= $effectiveThreshold) {
                $lowStockCount++;
                $lowStockProducts[] = [
                    'product_id' => $stock->product_id,
                    'current_stock' => $stock->current_stock,
                    'threshold' => $effectiveThreshold,
                    'shortage' => max(0, $effectiveThreshold - $stock->current_stock),
                    'state' => $state
                ];
                
                $this->warn("⚠️  Product {$stock->product_id}: {$stock->current_stock} units (threshold: {$effectiveThreshold})");
            }
        }
        
        if ($updateStatus) {
            $this->updateLowStockStatus($lowStockProducts);
        }
        
        if ($sendAlerts && !empty($lowStockProducts)) {
            $this->sendEmailAlerts($lowStockProducts, $state);
        }
        
        $this->displaySummary($checkedCount, $lowStockCount, $lowStockProducts);
        
        return 0;
    }

    private function sendTestEmail($email)
    {
        $this->info("📧 Sending test email to: {$email}");
        
        $success = $this->alertService->sendTestAlert($email);
        
        if ($success) {
            $this->info("✅ Test email sent successfully!");
        } else {
            $this->error("❌ Failed to send test email. Check logs for details.");
        }

        return $success ? 0 : 1;
    }

    private function sendEmailAlerts($lowStockProducts, $state)
    {
        $this->info('📧 Sending email alerts...');
        
        $success = $this->alertService->sendLowStockAlert($lowStockProducts, $state);
        
        if ($success) {
            $this->info("✅ Email alerts sent successfully!");
        } else {
            $this->error("❌ Failed to send email alerts. Check logs for details.");
        }
    }
    
    private function calculateCurrentStock()
    {
        return DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw('SUM(CASE 
                WHEN movement_type IN ("da_to_da", "receiving") THEN quantity 
                WHEN movement_type IN ("da_to_bins", "shipping") THEN -quantity
                ELSE 0 
            END) as current_stock')
            ->leftJoin('products', 'inventory_movements.product_id', '=', 'products.id')
            ->selectRaw('products.low_stock_threshold')
            ->where('approval_status', 'approved')
            ->groupBy('product_id', 'products.low_stock_threshold')
            ->get();
    }
    
    private function updateLowStockStatus($lowStockProducts)
    {
        $this->info('📝 Updating database status...');
        
        DB::table('products')->update([
            'is_low_stock' => false,
            'last_stock_check' => now()
        ]);
        
        $lowStockIds = array_column($lowStockProducts, 'product_id');
        if (!empty($lowStockIds)) {
            DB::table('products')
                ->whereIn('id', $lowStockIds)
                ->update([
                    'is_low_stock' => true,
                    'last_stock_check' => now()
                ]);
        }
        
        $this->info("✅ Updated " . count($lowStockIds) . " products");
    }
    
    private function displaySummary($checkedCount, $lowStockCount, $lowStockProducts)
    {
        $this->newLine();
        $this->info('📊 LOW STOCK SUMMARY');
        $this->info("Products Checked: {$checkedCount}");
        $this->info("Low Stock Products: {$lowStockCount}");
        
        if (!empty($lowStockProducts)) {
            $headers = ['Product ID', 'Stock', 'Threshold', 'Shortage'];
            $rows = array_map(function($product) {
                return [
                    $product['product_id'],
                    $product['current_stock'],
                    $product['threshold'],
                    $product['shortage']
                ];
            }, $lowStockProducts);
            
            $this->table($headers, $rows);
        } else {
            $this->info('✅ All products have sufficient stock!');
        }
    }
}
