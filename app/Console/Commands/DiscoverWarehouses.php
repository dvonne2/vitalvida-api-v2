<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiscoverWarehouses extends Command
{
    protected $signature = 'zoho:warehouses';
    protected $description = 'Show all Zoho warehouses';

    public function handle()
    {
        $this->info('🔍 Getting your Zoho warehouses...');
        
        $token = config('services.zoho.access_token');
        $orgId = config('services.zoho.organization_id');
        
        $this->info("Using Org ID: {$orgId}");
        $this->info("Token present: " . (empty($token) ? 'No' : 'Yes'));
        
        $baseUrl = 'https://www.zohoapis.com/inventory/v1';
        $this->info("Using endpoint: {$baseUrl}");
        
        try {
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => "Zoho-oauthtoken {$token}"
            ])->get("{$baseUrl}/warehouses", [
                'organization_id' => $orgId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->info("📋 Full API Response:");
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                
                $warehouses = $data['warehouses'] ?? [];
                $this->info("✅ Found " . count($warehouses) . " warehouses in response");
                
                if (empty($warehouses)) {
                    $this->warn("⚠️  No warehouses found. This might mean:");
                    $this->line("   • No warehouses have been created in your Zoho account");
                    $this->line("   • Wrong organization ID");
                    $this->line("   • Different API response structure");
                }
                
                foreach ($warehouses as $warehouse) {
                    $this->line("📦 {$warehouse['warehouse_name']} — ID: {$warehouse['warehouse_id']}");
                }
                return 0;
            } else {
                $this->error("❌ API Error: HTTP {$response->status()} - {$response->body()}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Connection Error: " . $e->getMessage());
        }
        
        return 1;
    }
}
