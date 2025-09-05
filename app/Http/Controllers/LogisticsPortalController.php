<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Consignment;
use App\Models\DeliveryAgent;
use App\Models\FraudAlert;
use App\Models\ActivityLog;

class LogisticsPortalController extends Controller
{
    /**
     * Show the login page
     */
    public function login()
    {
        return view('logistics-portal.login');
    }

    /**
     * Handle login authentication
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Demo credentials for testing
        $validCredentials = [
            'logistics' => 'manager123',
            'ceo' => 'ceo123',
        ];

        if (isset($validCredentials[$credentials['username']]) && 
            $validCredentials[$credentials['username']] === $credentials['password']) {
            
            // Set session
            session(['logistics_user' => $credentials['username']]);
            session(['logistics_role' => $credentials['username'] === 'ceo' ? 'Admin' : 'Logistics Manager']);
            
            return redirect()->route('logistics.dashboard');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Show the dashboard
     */
    public function dashboard()
    {
        // Mock data for demonstration
        $overview = [
            'today_consignments' => 24,
            'week_in_transit' => 12,
            'week_active_das' => 15,
            'month_fraud_alerts' => 2,
        ];

        $pipeline = [
            'waiting' => 7,
            'delivered' => 2,
            'orders' => [
                [
                    'id' => '10057',
                    'customer' => 'Adam J.',
                    'product' => 'Multivitamin Pack',
                    'da_called' => '09:50',
                    'out_delivery' => '10:11',
                    'delivery_cost' => '₦2,500',
                    'additional_cost' => '₦200',
                    'payment_received' => '10:21',
                    'delivered' => '12:18',
                    'warnings' => '-'
                ],
                [
                    'id' => '10056',
                    'customer' => 'Omololu A.',
                    'product' => 'Omega 3 Softgels',
                    'da_called' => '08:28',
                    'out_delivery' => '08:48',
                    'delivery_cost' => '₦2,200',
                    'additional_cost' => '-',
                    'payment_received' => '09:07',
                    'delivered' => '11:14',
                    'warnings' => '-'
                ]
            ]
        ];

        $kpis = [
            'dispatch_accuracy' => ['value' => 98.5, 'target' => 100, 'status' => 'excellent'],
            'delivery_chain_match' => ['value' => 97.2, 'target' => 95, 'status' => 'excellent'],
            'proof_compliance' => ['value' => 94.8, 'target' => 100, 'status' => 'good'],
            'sla_relay_completion' => ['value' => 96.3, 'target' => 95, 'status' => 'excellent'],
            'fraud_response_time' => ['value' => 26, 'target' => 30, 'status' => 'excellent'],
        ];

        return view('logistics-portal.dashboard.index', compact('overview', 'pipeline', 'kpis'));
    }

    /**
     * Show the live activity feed
     */
    public function liveActivity()
    {
        // Mock activity data
        $activities = [
            [
                'type' => 'pickup',
                'text' => 'DA_Kano picked up #FH20220506-003 from Jibowu Park',
                'time' => '10:44AM',
                'timestamp' => '17:53:59',
                'icon' => 'fas fa-box',
                'color' => 'blue'
            ],
            [
                'type' => 'delivery',
                'text' => 'OTP submitted for Order #10056 by DA_FCT-001',
                'time' => '11:19AM',
                'timestamp' => '17:52:34',
                'icon' => 'fas fa-check-circle',
                'color' => 'green',
                'status' => 'DELIVERED'
            ],
            [
                'type' => 'mismatch',
                'text' => 'Mismatch flagged on #FH20220506-002: Qty difference',
                'time' => '11:30AM',
                'timestamp' => '17:51:59',
                'icon' => 'fas fa-exclamation-triangle',
                'color' => 'red',
                'status' => 'FLAGGED'
            ],
            [
                'type' => 'call',
                'text' => 'DA_LAG-007 called customer for Order #10058',
                'time' => '11:29AM',
                'timestamp' => '17:50:59',
                'icon' => 'fas fa-phone',
                'color' => 'blue'
            ],
            [
                'type' => 'transit',
                'text' => 'Order #10059 marked in Transit by DA_ABJ-012',
                'time' => '11:28AM',
                'timestamp' => '17:49:59',
                'icon' => 'fas fa-truck',
                'color' => 'yellow'
            ]
        ];

        $statistics = [
            'pickups_today' => 24,
            'deliveries_today' => 18,
            'mismatches_today' => 3,
            'calls_today' => 42
        ];

        return view('logistics-portal.activity.live', compact('activities', 'statistics'));
    }

    /**
     * Show the consignments management page
     */
    public function consignments()
    {
        // Mock consignment data
        $consignments = [
            [
                'id' => 'VV-2024-001',
                'from_location' => 'HQ Lagos',
                'to_location' => 'DA_FCT-001',
                'quantity' => '2-2-2',
                'port' => 'Berger Motor Park',
                'driver_phone' => '+234901234567',
                'time' => '2024-01-15 09:30',
                'status' => 'in_transit'
            ],
            [
                'id' => 'VV-2024-002',
                'from_location' => 'DA_LAG-005',
                'to_location' => 'DA_OGU-003',
                'quantity' => '1-1-1',
                'port' => 'Mile 2 Park',
                'driver_phone' => '+234906544321',
                'time' => '2024-01-15 08:15',
                'status' => 'delivered'
            ],
            [
                'id' => 'VV-2024-003',
                'from_location' => 'Warehouse Kano',
                'to_location' => 'DA_KAN-008',
                'quantity' => '3-3-3',
                'port' => 'Jibowu Park',
                'driver_phone' => '+234907654321',
                'time' => '2024-01-15 10:00',
                'status' => 'pending'
            ]
        ];

        $statistics = [
            'pending' => 5,
            'in_transit' => 12,
            'delivered' => 18,
            'cancelled' => 2
        ];

        return view('logistics-portal.consignments.index', compact('consignments', 'statistics'));
    }

    /**
     * Show the bird eye panel (movements tracking)
     */
    public function birdEyePanel()
    {
        // Mock movement data (empty for now)
        $movements = [];
        
        $statistics = [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0
        ];

        return view('logistics-portal.movements.tracking', compact('movements', 'statistics'));
    }

    /**
     * Show the fraud detection page
     */
    public function fraudAlerts()
    {
        // Mock fraud alert data
        $alerts = [
            [
                'id' => 'quantity-mismatch',
                'type' => 'QUANTITY MISMATCH',
                'status' => 'active',
                'severity' => 'critical',
                'description' => 'Inventory dispatched 2-2-2, Logistics entered 1-1-1, DA received 1-1-1',
                'ids' => ['VV-2024-001', 'DA_FCT-001'],
                'escalated_to' => ['Inventory Manager', 'Financial Controller', 'COO', 'CEO'],
                'auto_actions' => ['Next consignment blocked', 'WhatsApp alerts sent', 'Email notifications sent'],
                'detected_time' => '2 hours ago'
            ],
            [
                'id' => 'delayed-pickup',
                'type' => 'DELAYED PICKUP',
                'status' => 'monitoring',
                'severity' => 'warning',
                'description' => 'Consignment at motor park for 4+ hours without pickup',
                'ids' => ['VV-2024-002', 'DA_LAG-005'],
                'escalated_to' => ['Logistics Manager'],
                'auto_actions' => ['Reminder sent to DA', 'Guarantor notified'],
                'detected_time' => '1 hour ago'
            ],
            [
                'id' => 'unscanned-waybill',
                'type' => 'UNSCANNED WAYBILL',
                'status' => 'resolved',
                'severity' => 'resolved',
                'description' => 'Waybill not scanned within expected timeframe',
                'ids' => ['VV-2024-003', 'DA_OGU-003'],
                'auto_actions' => ['Auto-reminder sent'],
                'resolution' => 'Waybill scanned successfully',
                'resolved_time' => '30 minutes ago'
            ]
        ];

        $statistics = [
            'active' => 2,
            'monitoring' => 1,
            'resolved' => 1,
            'total_today' => 4
        ];

        return view('logistics-portal.fraud.alerts', compact('alerts', 'statistics'));
    }

    /**
     * Show the reports page
     */
    public function reports()
    {
        // Mock report data
        $recentReports = [
            [
                'name' => 'Daily Operations Summary',
                'generated_by' => 'Logistics Manager',
                'date' => '2024-01-15 08:00',
                'status' => 'completed'
            ],
            [
                'name' => 'Inventory Audit Trail',
                'generated_by' => 'System Auto',
                'date' => '2024-01-15 07:30',
                'status' => 'completed'
            ],
            [
                'name' => 'Fraud Detection Report',
                'generated_by' => 'AI System',
                'date' => '2024-01-15 06:00',
                'status' => 'processing'
            ]
        ];

        $systemStats = [
            'last_sync' => '2 minutes ago',
            'total_events' => 1247,
            'ai_checks' => 856
        ];

        return view('logistics-portal.reports.index', compact('recentReports', 'systemStats'));
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        session()->forget(['logistics_user', 'logistics_role']);
        return redirect()->route('logistics.login');
    }

    /**
     * API method to get dashboard data
     */
    public function getDashboardData()
    {
        // This would typically call your API endpoints
        $data = [
            'overview' => $this->getOverviewData(),
            'pipeline' => $this->getPipelineData(),
            'kpis' => $this->getKPIData(),
        ];

        return response()->json($data);
    }

    /**
     * API method to get activity feed data
     */
    public function getActivityData()
    {
        // This would typically call your API endpoints
        $activities = $this->getActivityFeedData();
        
        return response()->json($activities);
    }

    /**
     * API method to get consignments data
     */
    public function getConsignmentsData()
    {
        // This would typically call your API endpoints
        $consignments = $this->getConsignmentsList();
        
        return response()->json($consignments);
    }

    /**
     * API method to get fraud alerts data
     */
    public function getFraudAlertsData()
    {
        // This would typically call your API endpoints
        $alerts = $this->getFraudAlertsList();
        
        return response()->json($alerts);
    }

    /**
     * Mock API call method
     */
    private function callAPI($endpoint)
    {
        // In a real implementation, this would make actual API calls
        // For now, return mock data
        return [];
    }

    /**
     * Mock data methods
     */
    private function getOverviewData()
    {
        return [
            'today_consignments' => 24,
            'week_in_transit' => 12,
            'week_active_das' => 15,
            'month_fraud_alerts' => 2,
        ];
    }

    private function getPipelineData()
    {
        return [
            'waiting' => 7,
            'delivered' => 2,
            'orders' => []
        ];
    }

    private function getKPIData()
    {
        return [
            'dispatch_accuracy' => ['value' => 98.5, 'target' => 100, 'status' => 'excellent'],
            'delivery_chain_match' => ['value' => 97.2, 'target' => 95, 'status' => 'excellent'],
            'proof_compliance' => ['value' => 94.8, 'target' => 100, 'status' => 'good'],
            'sla_relay_completion' => ['value' => 96.3, 'target' => 95, 'status' => 'excellent'],
            'fraud_response_time' => ['value' => 26, 'target' => 30, 'status' => 'excellent'],
        ];
    }

    private function getActivityFeedData()
    {
        return [];
    }

    private function getConsignmentsList()
    {
        return [];
    }

    private function getFraudAlertsList()
    {
        return [];
    }
} 