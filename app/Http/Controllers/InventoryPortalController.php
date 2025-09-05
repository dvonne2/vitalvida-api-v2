<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class InventoryPortalController extends Controller
{
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = config('app.url') . '/api/inventory-portal';
    }

    /**
     * Show the main dashboard
     */
    public function dashboard()
    {
        try {
            // Fetch dashboard data from API endpoints
            $overview = $this->callAPI('/dashboard/enhanced-overview');
            $alerts = $this->callAPI('/alerts/critical');
            $regionalOverview = $this->callAPI('/dashboard/regional-overview');

            return view('inventory-portal.dashboard.index', compact('overview', 'alerts', 'regionalOverview'));
        } catch (\Exception $e) {
            // Return view with empty data if API fails
            return view('inventory-portal.dashboard.index', [
                'overview' => ['success' => false, 'data' => []],
                'alerts' => ['success' => false, 'data' => []],
                'regionalOverview' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the inventory flow control page
     */
    public function inventoryFlow()
    {
        try {
            $flowData = $this->callAPI('/inventory/flow/summary');
            $purchaseOrders = $this->callAPI('/purchase-orders');
            $stockMovements = $this->callAPI('/inventory/movements');

            return view('inventory-portal.inventory.flow', compact('flowData', 'purchaseOrders', 'stockMovements'));
        } catch (\Exception $e) {
            return view('inventory-portal.inventory.flow', [
                'flowData' => ['success' => false, 'data' => []],
                'purchaseOrders' => ['success' => false, 'data' => []],
                'stockMovements' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the goods out page
     */
    public function goodsOut()
    {
        try {
            $goodsOutData = $this->callAPI('/inventory/goods-out');
            $daList = $this->callAPI('/da/agents');

            return view('inventory-portal.inventory.goods-out', compact('goodsOutData', 'daList'));
        } catch (\Exception $e) {
            return view('inventory-portal.inventory.goods-out', [
                'goodsOutData' => ['success' => false, 'data' => []],
                'daList' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the returns page
     */
    public function returns()
    {
        try {
            $returnsData = $this->callAPI('/inventory/returns');
            $pendingReturns = $this->callAPI('/inventory/returns/pending');

            return view('inventory-portal.inventory.returns', compact('returnsData', 'pendingReturns'));
        } catch (\Exception $e) {
            return view('inventory-portal.inventory.returns', [
                'returnsData' => ['success' => false, 'data' => []],
                'pendingReturns' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the DA enforcement page
     */
    public function daEnforcement()
    {
        try {
            $complianceData = $this->callAPI('/da/compliance/overview');
            $violations = $this->callAPI('/da/violations');
            $agents = $this->callAPI('/da/agents');

            return view('inventory-portal.da.enforcement', compact('complianceData', 'violations', 'agents'));
        } catch (\Exception $e) {
            return view('inventory-portal.da.enforcement', [
                'complianceData' => ['success' => false, 'data' => []],
                'violations' => ['success' => false, 'data' => []],
                'agents' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the DA performance page
     */
    public function daPerformance()
    {
        try {
            $performanceData = $this->callAPI('/da/performance/overview');
            $rankings = $this->callAPI('/da/performance/rankings');
            $recentViolations = $this->callAPI('/da/violations/recent');

            return view('inventory-portal.da.performance', compact('performanceData', 'rankings', 'recentViolations'));
        } catch (\Exception $e) {
            return view('inventory-portal.da.performance', [
                'performanceData' => ['success' => false, 'data' => []],
                'rankings' => ['success' => false, 'data' => []],
                'recentViolations' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the DA compliance page
     */
    public function daCompliance()
    {
        try {
            $weeklyCompliance = $this->callAPI('/da/compliance/weekly');
            $photoCompliance = $this->callAPI('/da/compliance/photos');
            $complianceTrends = $this->callAPI('/da/compliance/trends');

            return view('inventory-portal.da.compliance', compact('weeklyCompliance', 'photoCompliance', 'complianceTrends'));
        } catch (\Exception $e) {
            return view('inventory-portal.da.compliance', [
                'weeklyCompliance' => ['success' => false, 'data' => []],
                'photoCompliance' => ['success' => false, 'data' => []],
                'complianceTrends' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the stock overview page
     */
    public function stock()
    {
        try {
            $stockOverview = $this->callAPI('/stock/overview');
            $stateOverview = $this->callAPI('/stock/state-overview');
            $liveStockLevels = $this->callAPI('/stock/live-levels');

            return view('inventory-portal.stock.overview', compact('stockOverview', 'stateOverview', 'liveStockLevels'));
        } catch (\Exception $e) {
            return view('inventory-portal.stock.overview', [
                'stockOverview' => ['success' => false, 'data' => []],
                'stateOverview' => ['success' => false, 'data' => []],
                'liveStockLevels' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the bins management page
     */
    public function bins()
    {
        try {
            $binData = $this->callAPI('/bins');
            $binAlerts = $this->callAPI('/stock/alerts/critical');
            $restockRecommendations = $this->callAPI('/bins/restock');

            return view('inventory-portal.stock.bins', compact('binData', 'binAlerts', 'restockRecommendations'));
        } catch (\Exception $e) {
            return view('inventory-portal.stock.bins', [
                'binData' => ['success' => false, 'data' => []],
                'binAlerts' => ['success' => false, 'data' => []],
                'restockRecommendations' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the regional overview page
     */
    public function regional()
    {
        try {
            $regionalData = $this->callAPI('/regional/overview');
            $stockSummary = $this->callAPI('/regional/stock-summary');
            $agentDistribution = $this->callAPI('/regional/agent-distribution');

            return view('inventory-portal.regional.overview', compact('regionalData', 'stockSummary', 'agentDistribution'));
        } catch (\Exception $e) {
            return view('inventory-portal.regional.overview', [
                'regionalData' => ['success' => false, 'data' => []],
                'stockSummary' => ['success' => false, 'data' => []],
                'agentDistribution' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Show the alerts page
     */
    public function alerts()
    {
        try {
            $criticalAlerts = $this->callAPI('/stock/alerts/critical');
            $lowStockAlerts = $this->callAPI('/stock/alerts/low-stock');
            $agingInventory = $this->callAPI('/stock/alerts/aging-inventory');

            return view('inventory-portal.alerts.index', compact('criticalAlerts', 'lowStockAlerts', 'agingInventory'));
        } catch (\Exception $e) {
            return view('inventory-portal.alerts.index', [
                'criticalAlerts' => ['success' => false, 'data' => []],
                'lowStockAlerts' => ['success' => false, 'data' => []],
                'agingInventory' => ['success' => false, 'data' => []]
            ]);
        }
    }

    /**
     * Make API call to backend endpoints
     */
    protected function callAPI($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->apiBaseUrl . $endpoint;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Add authentication token if available
        if (Auth::check()) {
            $token = Auth::user()->createToken('portal-token')->plainTextToken;
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = Http::withHeaders($headers);

            if ($method === 'GET') {
                $response = $response->get($url);
            } elseif ($method === 'POST') {
                $response = $response->post($url, $data);
            } elseif ($method === 'PUT') {
                $response = $response->put($url, $data);
            } elseif ($method === 'DELETE') {
                $response = $response->delete($url);
            }

            if ($response->successful()) {
                return $response->json();
            } else {
                // Return error response
                return [
                    'success' => false,
                    'message' => 'API request failed: ' . $response->status(),
                    'data' => []
                ];
            }
        } catch (\Exception $e) {
            // Return error response for network/connection issues
            return [
                'success' => false,
                'message' => 'Network error: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Handle form submissions from views
     */
    public function handleFormSubmission(Request $request)
    {
        $action = $request->input('action');
        $data = $request->except(['_token', 'action']);

        try {
            switch ($action) {
                case 'create_purchase_order':
                    $response = $this->callAPI('/purchase-orders', 'POST', $data);
                    break;
                    
                case 'receive_stock':
                    $response = $this->callAPI('/inventory/receive', 'POST', $data);
                    break;
                    
                case 'supply_to_da':
                    $response = $this->callAPI('/inventory/supply', 'POST', $data);
                    break;
                    
                case 'resolve_violation':
                    $response = $this->callAPI('/da/violations/resolve', 'POST', $data);
                    break;
                    
                case 'add_stock_to_bin':
                    $response = $this->callAPI('/bins/add-stock', 'POST', $data);
                    break;
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown action'
                    ]);
            }

            if ($response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Operation completed successfully',
                    'data' => $response['data'] ?? []
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Operation failed'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get real-time data for AJAX requests
     */
    public function getRealTimeData(Request $request)
    {
        $type = $request->input('type', 'dashboard');
        
        try {
            switch ($type) {
                case 'dashboard':
                    $data = [
                        'overview' => $this->callAPI('/dashboard/enhanced-overview'),
                        'alerts' => $this->callAPI('/alerts/critical')
                    ];
                    break;
                    
                case 'inventory':
                    $data = [
                        'flow' => $this->callAPI('/inventory/flow/summary'),
                        'alerts' => $this->callAPI('/stock/alerts/critical')
                    ];
                    break;
                    
                case 'da_enforcement':
                    $data = [
                        'compliance' => $this->callAPI('/da/compliance/overview'),
                        'violations' => $this->callAPI('/da/violations')
                    ];
                    break;
                    
                case 'stock':
                    $data = [
                        'overview' => $this->callAPI('/stock/overview'),
                        'bins' => $this->callAPI('/bins')
                    ];
                    break;
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown data type'
                    ]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch real-time data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export data to various formats
     */
    public function exportData(Request $request)
    {
        $type = $request->input('type');
        $format = $request->input('format', 'csv');
        
        try {
            switch ($type) {
                case 'da_performance':
                    $data = $this->callAPI('/da/performance/rankings');
                    break;
                    
                case 'inventory_movements':
                    $data = $this->callAPI('/inventory/movements');
                    break;
                    
                case 'stock_levels':
                    $data = $this->callAPI('/stock/live-levels');
                    break;
                    
                case 'compliance_report':
                    $data = $this->callAPI('/da/compliance/weekly');
                    break;
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown export type'
                    ]);
            }

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data for export'
                ]);
            }

            // Generate export file
            $filename = $type . '_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            
            if ($format === 'csv') {
                return $this->generateCSV($data['data'], $filename);
            } elseif ($format === 'excel') {
                return $this->generateExcel($data['data'], $filename);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported export format'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate CSV export
     */
    protected function generateCSV($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            if (!empty($data)) {
                fputcsv($file, array_keys($data[0]));
            }
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate Excel export (placeholder)
     */
    protected function generateExcel($data, $filename)
    {
        // This would require a package like PhpSpreadsheet
        // For now, return CSV format
        return $this->generateCSV($data, str_replace('.xlsx', '.csv', $filename));
    }
} 