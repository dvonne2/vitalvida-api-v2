<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncERPData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $companyId;
    protected $syncType;
    protected $dataRange;

    public function __construct($companyId, $syncType = 'all', $dataRange = 'daily')
    {
        $this->companyId = $companyId;
        $this->syncType = $syncType; // 'customers', 'sales', 'products', 'all'
        $this->dataRange = $dataRange; // 'daily', 'weekly', 'monthly'
    }

    public function handle()
    {
        try {
            Log::info("Starting ERP data sync for marketing", [
                'company_id' => $this->companyId,
                'sync_type' => $this->syncType,
                'data_range' => $this->dataRange
            ]);

            $startDate = $this->getStartDate();

            if ($this->syncType === 'all' || $this->syncType === 'customers') {
                $this->syncCustomerData($startDate);
            }

            if ($this->syncType === 'all' || $this->syncType === 'sales') {
                $this->syncSalesData($startDate);
            }

            if ($this->syncType === 'all' || $this->syncType === 'products') {
                $this->syncProductData($startDate);
            }

            Log::info("ERP data sync completed successfully", [
                'company_id' => $this->companyId,
                'sync_type' => $this->syncType
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to sync ERP data for marketing", [
                'company_id' => $this->companyId,
                'sync_type' => $this->syncType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function syncCustomerData($startDate)
    {
        Log::info("Syncing customer data for marketing", [
            'company_id' => $this->companyId,
            'start_date' => $startDate
        ]);

        // Get new or updated customers
        $customers = Customer::where('company_id', $this->companyId)
            ->where('updated_at', '>=', $startDate)
            ->get();

        foreach ($customers as $customer) {
            // Update customer segments and marketing preferences
            $this->updateCustomerMarketingProfile($customer);
        }

        Log::info("Customer data sync completed", [
            'company_id' => $this->companyId,
            'customers_processed' => $customers->count()
        ]);
    }

    private function syncSalesData($startDate)
    {
        Log::info("Syncing sales data for marketing", [
            'company_id' => $this->companyId,
            'start_date' => $startDate
        ]);

        // Get sales that might be attributed to marketing touchpoints
        $sales = Sale::where('company_id', $this->companyId)
            ->where('created_at', '>=', $startDate)
            ->with(['customer'])
            ->get();

        foreach ($sales as $sale) {
            $this->attributeSaleToMarketing($sale);
        }

        Log::info("Sales data sync completed", [
            'company_id' => $this->companyId,
            'sales_processed' => $sales->count()
        ]);
    }

    private function syncProductData($startDate)
    {
        Log::info("Syncing product data for marketing", [
            'company_id' => $this->companyId,
            'start_date' => $startDate
        ]);

        // Get updated products for marketing content generation
        $products = Product::where('company_id', $this->companyId)
            ->where('updated_at', '>=', $startDate)
            ->get();

        foreach ($products as $product) {
            $this->updateProductMarketingData($product);
        }

        Log::info("Product data sync completed", [
            'company_id' => $this->companyId,
            'products_processed' => $products->count()
        ]);
    }

    private function updateCustomerMarketingProfile($customer)
    {
        // Calculate customer lifetime value
        $totalPurchases = Sale::where('customer_id', $customer->id)->sum('total_amount');
        $purchaseCount = Sale::where('customer_id', $customer->id)->count();
        $avgOrderValue = $purchaseCount > 0 ? $totalPurchases / $purchaseCount : 0;

        // Determine customer segment
        $segment = $this->determineCustomerSegment($totalPurchases, $purchaseCount);

        // Update customer marketing attributes
        $customer->update([
            'marketing_segment' => $segment,
            'lifetime_value' => $totalPurchases,
            'avg_order_value' => $avgOrderValue,
            'purchase_frequency' => $purchaseCount,
            'last_marketing_sync' => Carbon::now()
        ]);

        Log::debug("Updated customer marketing profile", [
            'customer_id' => $customer->id,
            'segment' => $segment,
            'lifetime_value' => $totalPurchases
        ]);
    }

    private function attributeSaleToMarketing($sale)
    {
        // Look for recent marketing touchpoints for this customer
        $recentTouchpoints = MarketingCustomerTouchpoint::where('customer_id', $sale->customer_id)
            ->where('created_at', '>=', Carbon::parse($sale->created_at)->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentTouchpoints->isNotEmpty()) {
            // Attribute sale to the most recent touchpoint
            $lastTouchpoint = $recentTouchpoints->first();
            
            // Create attribution record
            $sale->update([
                'marketing_attributed' => true,
                'attribution_touchpoint_id' => $lastTouchpoint->id,
                'attribution_campaign_id' => $lastTouchpoint->campaign_id
            ]);

            // Update touchpoint with conversion
            $lastTouchpoint->update([
                'interaction_type' => 'converted',
                'conversion_value' => $sale->total_amount,
                'converted_at' => $sale->created_at
            ]);

            Log::debug("Sale attributed to marketing", [
                'sale_id' => $sale->id,
                'touchpoint_id' => $lastTouchpoint->id,
                'conversion_value' => $sale->total_amount
            ]);
        }
    }

    private function updateProductMarketingData($product)
    {
        // Update product performance metrics for marketing
        $salesCount = Sale::whereHas('items', function($query) use ($product) {
            $query->where('product_id', $product->id);
        })->count();

        $totalRevenue = Sale::whereHas('items', function($query) use ($product) {
            $query->where('product_id', $product->id);
        })->sum('total_amount');

        $product->update([
            'marketing_sales_count' => $salesCount,
            'marketing_total_revenue' => $totalRevenue,
            'last_marketing_sync' => Carbon::now()
        ]);
    }

    private function determineCustomerSegment($totalPurchases, $purchaseCount)
    {
        if ($totalPurchases >= 500000 && $purchaseCount >= 10) {
            return 'vip';
        } elseif ($totalPurchases >= 100000 && $purchaseCount >= 5) {
            return 'high_value';
        } elseif ($totalPurchases >= 50000 && $purchaseCount >= 3) {
            return 'regular';
        } elseif ($purchaseCount >= 1) {
            return 'new_customer';
        } else {
            return 'prospect';
        }
    }

    private function getStartDate()
    {
        return match($this->dataRange) {
            'daily' => Carbon::now()->subDay(),
            'weekly' => Carbon::now()->subWeek(),
            'monthly' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay()
        };
    }
}
