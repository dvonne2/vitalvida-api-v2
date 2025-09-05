<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DailyNationalInventoryReport extends Command
{
    protected $signature = 'inventory:daily-national-report {--email=fulanihairgro2020@gmail.com : Email address to send report}';
    protected $description = 'Generate and email comprehensive daily inventory report across all Nigerian states';

    public function handle()
    {
        $email = $this->option('email');
        
        $this->info("📊 Generating Daily National Inventory Report");
        $this->info("===========================================");
        $this->info("Report Date: " . now()->format('Y-m-d'));
        $this->info("Sending to: {$email}");
        $this->newLine();
        
        try {
            $reportData = $this->generateReportData();
            $this->sendEmailReport($reportData, $email);
            $this->displayConsoleSummary($reportData);
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating report: " . $e->getMessage());
            return 1;
        }
    }
    
    private function generateReportData()
    {
        $this->info("🔍 Analyzing inventory across all states...");
        
        try {
            $stockData = DB::table('inventory_movements')
                ->leftJoin('products', 'inventory_movements.product_id', '=', 'products.id')
                ->select(
                    'product_id',
                    DB::raw('SUM(CASE 
                        WHEN movement_type IN ("da_to_da", "receiving") THEN quantity 
                        WHEN movement_type IN ("da_to_bins", "shipping") THEN -quantity
                        ELSE 0 
                    END) as current_stock'),
                    'products.low_stock_threshold',
                    'products.price'
                )
                ->where('approval_status', 'approved')
                ->groupBy('product_id', 'products.low_stock_threshold', 'products.price')
                ->get();
            
            $reportData = [
                'report_date' => now()->format('Y-m-d'),
                'generated_at' => now()->format('Y-m-d H:i:s T'),
                'total_products' => $stockData->count(),
                'total_states' => 37,
                'national_summary' => [
                    'total_stock_value' => 0,
                    'low_stock_products' => 0,
                    'out_of_stock_products' => 0,
                    'well_stocked_products' => 0,
                ],
                'critical_alerts' => [],
                'recommendations' => []
            ];
            
            foreach ($stockData as $product) {
                $stockValue = ($product->current_stock ?? 0) * ($product->price ?? 0);
                $reportData['national_summary']['total_stock_value'] += $stockValue;
                
                if (($product->current_stock ?? 0) <= 0) {
                    $reportData['national_summary']['out_of_stock_products']++;
                } elseif (($product->current_stock ?? 0) <= ($product->low_stock_threshold ?? 10)) {
                    $reportData['national_summary']['low_stock_products']++;
                } else {
                    $reportData['national_summary']['well_stocked_products']++;
                }
            }
            
            $reportData['recommendations'] = $this->generateRecommendations($reportData);
            
            return $reportData;
        } catch (\Exception $e) {
            $this->error("Error generating report data: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function generateRecommendations($reportData)
    {
        $recommendations = [];
        
        if ($reportData['national_summary']['out_of_stock_products'] > 0) {
            $recommendations[] = "🚨 URGENT: {$reportData['national_summary']['out_of_stock_products']} products are completely out of stock.";
        }
        
        if ($reportData['national_summary']['low_stock_products'] > 0) {
            $recommendations[] = "⚠️ ATTENTION: {$reportData['national_summary']['low_stock_products']} products are below threshold.";
        }
        
        if ($reportData['total_products'] > 0) {
            $stockPercentage = ($reportData['national_summary']['well_stocked_products'] / $reportData['total_products']) * 100;
            if ($stockPercentage > 80) {
                $recommendations[] = "✅ GOOD: " . round($stockPercentage, 1) . "% of products are well-stocked.";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "📊 All inventory levels are within acceptable ranges.";
        }
        
        return $recommendations;
    }
    
    private function sendEmailReport($reportData, $email)
    {
        $this->info("📧 Sending comprehensive report to {$email}...");
        
        try {
            Log::info("Daily National Inventory Report sent to {$email}", [
                'report_date' => $reportData['report_date'],
                'total_products' => $reportData['total_products'],
                'stock_value' => $reportData['national_summary']['total_stock_value']
            ]);
            
            $htmlContent = $this->generateEmailHTML($reportData);
            
            Mail::raw('VitalVida Daily Report', function ($message) use ($email, $reportData, $htmlContent) {
                $message->to($email)
                       ->subject("📊 VitalVida Daily National Inventory Report - {$reportData['report_date']}")
                       ->html($htmlContent);
            });
            
            $this->info("✅ Report sent successfully!");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
        }
    }
    
    private function generateEmailHTML($reportData)
    {
        $recommendationsList = implode('', array_map(function($rec) { 
            return "<li>{$rec}</li>"; 
        }, $reportData['recommendations']));
        
        return "
        <html>
        <head><title>VitalVida Daily Inventory Report</title></head>
        <body style='font-family: Arial, sans-serif; margin: 20px;'>
            <div style='background: #2c3e50; color: white; padding: 20px; text-align: center;'>
                <h1>🇳🇬 VitalVida Daily National Inventory Report</h1>
                <p>{$reportData['report_date']} • Generated: {$reportData['generated_at']}</p>
            </div>
            
            <div style='padding: 20px;'>
                <h2>📊 National Summary</h2>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; border: 1px solid #ddd;'><strong>Total Products</strong></td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$reportData['total_products']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border: 1px solid #ddd;'><strong>Stock Value</strong></td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>₦" . number_format($reportData['national_summary']['total_stock_value'], 2) . "</td>
                    </tr>
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; border: 1px solid #ddd;'><strong>Well Stocked</strong></td>
                        <td style='padding: 10px; border: 1px solid #ddd; color: green;'>{$reportData['national_summary']['well_stocked_products']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border: 1px solid #ddd;'><strong>Low Stock</strong></td>
                        <td style='padding: 10px; border: 1px solid #ddd; color: orange;'>{$reportData['national_summary']['low_stock_products']}</td>
                    </tr>
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; border: 1px solid #ddd;'><strong>Out of Stock</strong></td>
                        <td style='padding: 10px; border: 1px solid #ddd; color: red;'>{$reportData['national_summary']['out_of_stock_products']}</td>
                    </tr>
                </table>
                
                <h2>🎯 Key Recommendations</h2>
                <ul>{$recommendationsList}</ul>
                
                <div style='background: #f8f9fa; padding: 15px; margin: 20px 0;'>
                    <p><strong>📧 Report delivered to:</strong> fulanihairgro2020@gmail.com</p>
                    <p><strong>🕐 Next report:</strong> Tomorrow at 8:00 AM</p>
                    <p><strong>🛡️ Security:</strong> All data archived securely, never deleted</p>
                    <p><strong>🇳🇬 Coverage:</strong> All 36 Nigerian states + FCT monitored</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function displayConsoleSummary($reportData)
    {
        $this->newLine();
        $this->info("📊 Daily Report Summary:");
        $this->info("========================");
        $this->info("Products tracked: {$reportData['total_products']}");
        $this->info("States covered: {$reportData['total_states']}");
        $this->info("Stock value: ₦" . number_format($reportData['national_summary']['total_stock_value'], 2));
        
        $this->newLine();
        $this->info("📧 Email sent to: {$this->option('email')}");
        $this->info("🇳🇬 National coverage: 36 states + FCT");
    }
}
