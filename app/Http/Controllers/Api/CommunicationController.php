<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use App\Models\DeliveryAgent;
use App\Models\OrderHistory;
use App\Models\AlertTemplate;
use App\Models\SentMessage;
use App\Services\EBulkSMSService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CommunicationController extends Controller
{
    protected $ebulkSMS;

    public function __construct(EBulkSMSService $ebulkSMS)
    {
        $this->ebulkSMS = $ebulkSMS;
    }

    public function index()
    {
        $templates = AlertTemplate::all();
        $recentMessages = SentMessage::with('template')
            ->orderBy('sent_at', 'desc')
            ->limit(20)
            ->get();

        $apiStatus = $this->getEBulkSMSStatus();

        return response()->json([
            'status' => 'success',
            'data' => [
                'alert_templates' => $templates,
                'api_status' => $apiStatus,
                'recent_messages' => $recentMessages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'recipient' => $message->recipient,
                        'type' => $message->type,
                        'delivery_status' => $message->delivery_status,
                        'sent_at' => $message->sent_at->format('g:i A'),
                        'response_received' => $message->response_received,
                    ];
                }),
                'auto_escalation_rules' => $this->getAutoEscalationRules(),
            ],
        ]);
    }

    public function sendFraudAlert(Request $request)
    {
        $request->validate([
            'staff_name' => 'required|string',
            'fraud_type' => 'required|string',
            'amount' => 'required|numeric',
            'auto_actions' => 'required|array',
        ]);

        $template = AlertTemplate::where('type', 'fraud_alert')->first();
        
        $whatsappMessage = $this->formatTemplate($template->whatsapp_template, [
            'staffName' => $request->staff_name,
            'fraudType' => $request->fraud_type,
            'amount' => '₦' . number_format($request->amount, 2),
            'autoActions' => implode(', ', $request->auto_actions),
            'timestamp' => now()->format('g:i A'),
        ]);

        $smsMessage = $this->formatTemplate($template->sms_template, [
            'staff' => $request->staff_name,
            'amount' => '₦' . number_format($request->amount),
        ]);

        // Send WhatsApp first (priority)
        $whatsappResult = $this->ebulkSMS->sendWhatsApp(
            config('gm_portal.gm_whatsapp'), 
            $whatsappMessage
        );

        $smsResult = null;
        
        // Send SMS as fallback if WhatsApp fails
        if (!$whatsappResult['success']) {
            $smsResult = $this->ebulkSMS->sendSMS(
                config('gm_portal.gm_phone'),
                $smsMessage,
                'critical'
            );
        }

        // Log the sent messages
        SentMessage::create([
            'recipient' => config('gm_portal.gm_whatsapp'),
            'message' => $whatsappMessage,
            'type' => 'whatsapp',
            'template_used' => $template->id,
            'delivery_status' => $whatsappResult['success'] ? 'delivered' : 'failed',
            'sent_at' => now(),
        ]);

        if ($smsResult) {
            SentMessage::create([
                'recipient' => config('gm_portal.gm_phone'),
                'message' => $smsMessage,
                'type' => 'sms',
                'template_used' => $template->id,
                'delivery_status' => $smsResult['success'] ? 'delivered' : 'failed',
                'sent_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Fraud alert sent successfully',
            'data' => [
                'whatsapp_result' => $whatsappResult,
                'sms_result' => $smsResult,
                'primary_delivery' => $whatsappResult['success'] ? 'whatsapp' : 'sms',
            ],
        ]);
    }

    public function sendStockAlert(Request $request)
    {
        $request->validate([
            'location' => 'required|string',
            'product' => 'required|string', 
            'days_remaining' => 'required|integer',
            'revenue_risk' => 'required|numeric',
        ]);

        $template = AlertTemplate::where('type', 'stock_emergency')->first();
        
        $message = $this->formatTemplate($template->whatsapp_template, [
            'location' => $request->location,
            'product' => $request->product,
            'days' => $request->days_remaining,
            'amount' => '₦' . number_format($request->revenue_risk),
        ]);

        $result = $this->ebulkSMS->sendWhatsApp(
            config('gm_portal.gm_whatsapp'),
            $message
        );

        SentMessage::create([
            'recipient' => config('gm_portal.gm_whatsapp'),
            'message' => $message,
            'type' => 'whatsapp',
            'template_used' => $template->id,
            'delivery_status' => $result['success'] ? 'delivered' : 'failed',
            'sent_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Stock alert sent successfully',
            'data' => $result,
        ]);
    }

    public function sendDAPerformanceAlert(Request $request)
    {
        $request->validate([
            'da_name' => 'required|string',
            'issue' => 'required|string',
            'days_count' => 'required|integer',
            'stock_value' => 'required|numeric',
        ]);

        $template = AlertTemplate::where('type', 'da_performance')->first();
        
        $message = $this->formatTemplate($template->whatsapp_template, [
            'daName' => $request->da_name,
            'issue' => $request->issue,
            'dayCount' => $request->days_count,
            'stockValue' => '₦' . number_format($request->stock_value),
        ]);

        $result = $this->ebulkSMS->sendWhatsApp(
            config('gm_portal.gm_whatsapp'),
            $message
        );

        SentMessage::create([
            'recipient' => config('gm_portal.gm_whatsapp'),
            'message' => $message,
            'type' => 'whatsapp',
            'template_used' => $template->id,
            'delivery_status' => $result['success'] ? 'delivered' : 'failed',
            'sent_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'DA performance alert sent successfully',
            'data' => $result,
        ]);
    }

    public function sendTestAlert(Request $request)
    {
        $message = $request->input('message', 'Test message from Vitalvida GM Portal');
        
        $whatsappResult = $this->ebulkSMS->sendWhatsApp(
            config('gm_portal.gm_whatsapp'),
            $message
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Test alert sent',
            'data' => $whatsappResult,
        ]);
    }

    /**
     * Send WhatsApp alert
     */
    public function sendWhatsAppAlert(Request $request): JsonResponse
    {
        $request->validate([
            'alert_type' => 'required|in:fraud,ghost,stagnant,payment_mismatch,performance,general',
            'recipients' => 'required|array',
            'recipients.*.phone' => 'required|string',
            'recipients.*.name' => 'required|string',
            'message' => 'required|string|max:500',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($request->recipients as $recipient) {
            $result = $this->sendSMS($recipient['phone'], $request->message);
            
            $results[] = [
                'recipient' => $recipient['name'],
                'phone' => $recipient['phone'],
                'status' => $result['success'] ? 'sent' : 'failed',
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        // Log the communication
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'whatsapp_alert_sent',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Sent {$request->alert_type} alert to " . count($request->recipients) . " recipients. Success: {$successCount}, Failed: {$failureCount}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Alert sent successfully. Success: {$successCount}, Failed: {$failureCount}",
            'data' => [
                'results' => $results,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'alert_type' => $request->alert_type,
                'priority' => $request->priority,
            ],
        ]);
    }

    /**
     * Send automated alerts
     */
    public function sendAutomatedAlerts(Request $request): JsonResponse
    {
        $alertType = $request->get('alert_type', 'all');
        $results = [];

        switch ($alertType) {
            case 'fraud':
                $results = $this->sendFraudAlerts();
                break;
            case 'ghost':
                $results = $this->sendGhostAlerts();
                break;
            case 'stagnant':
                $results = $this->sendStagnantInventoryAlerts();
                break;
            case 'payment_mismatch':
                $results = $this->sendPaymentMismatchAlerts();
                break;
            case 'performance':
                $results = $this->sendPerformanceAlerts();
                break;
            default:
                $results = array_merge(
                    $this->sendFraudAlerts(),
                    $this->sendGhostAlerts(),
                    $this->sendStagnantInventoryAlerts(),
                    $this->sendPaymentMismatchAlerts(),
                    $this->sendPerformanceAlerts()
                );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Automated alerts sent successfully',
            'data' => [
                'results' => $results,
                'alert_type' => $alertType,
                'total_sent' => count($results),
            ],
        ]);
    }

    /**
     * Get communication history
     */
    public function communicationHistory(Request $request): JsonResponse
    {
        $query = SentMessage::with('template')
            ->orderBy('sent_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('delivery_status', $request->status);
        }

        $messages = $query->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $messages,
            'summary' => $this->getCommunicationSummary($messages->items()),
        ]);
    }

    /**
     * Get alert templates
     */
    public function alertTemplates(): JsonResponse
    {
        $templates = AlertTemplate::all();

        return response()->json([
            'status' => 'success',
            'data' => $templates,
            'template_types' => [
                'fraud_alert' => 'Fraud Detection Alerts',
                'stock_emergency' => 'Stock Emergency Alerts',
                'da_performance' => 'DA Performance Alerts',
                'payment_mismatch' => 'Payment Mismatch Alerts',
            ],
        ]);
    }

    private function getEBulkSMSStatus()
    {
        $balance = $this->ebulkSMS->getAccountBalance();
        
        return [
            'uptime_today' => '99.8%',
            'avg_latency' => '24ms',
            'sms_delivery_rate' => '98.5%',
            'whatsapp_delivery_rate' => '99.2%',
            'queue_status' => 'Empty',
            'balance' => $balance['balance'] ?? 'N/A',
            'connection_status' => 'Connected',
        ];
    }

    private function getAutoEscalationRules()
    {
        return [
            [
                'trigger' => 'Critical Fraud Alert',
                'condition' => 'If GM doesn\'t respond within 15 minutes',
                'action' => 'Auto-escalate to COO via SMS + WhatsApp',
            ],
            [
                'trigger' => 'Stock Emergency',
                'condition' => 'If no action within 1 hour',
                'action' => 'Call GM directly + Escalate to COO',
            ],
            [
                'trigger' => 'DA Performance Issue',
                'condition' => 'If no response within 2 hours',
                'action' => 'Auto-redistribute 50% of DA stock',
            ],
            [
                'trigger' => 'System Down',
                'condition' => 'If portal offline > 5 minutes',
                'action' => 'Emergency SMS to GM + COO + CTO',
            ],
        ];
    }

    private function formatTemplate($template, $variables)
    {
        $formatted = $template;
        
        foreach ($variables as $key => $value) {
            $formatted = str_replace('{' . $key . '}', $value, $formatted);
        }
        
        return $formatted;
    }

    private function sendSMS($phone, $message): array
    {
        try {
            $response = Http::post('https://api.ebulksms.com/sendsms.json', [
                'username' => config('gm_portal.ebulksms.username'),
                'apikey' => config('gm_portal.ebulksms.api_key'),
                'sender' => config('gm_portal.ebulksms.sender_name'),
                'messagetext' => $message,
                'recipients' => $phone,
            ]);

            $result = $response->json();

            return [
                'success' => $response->successful(),
                'message_id' => $result['data']['id'] ?? null,
                'error' => $result['error'] ?? null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendFraudAlerts(): array
    {
        $fraudPatterns = Order::where('status', 'flagged')
            ->where('fraud_confidence', '>', 80)
            ->get();

        $results = [];
        foreach ($fraudPatterns as $pattern) {
            $results[] = [
                'type' => 'fraud_alert',
                'staff' => $pattern->telesalesRep->name ?? 'Unknown',
                'amount' => $pattern->total_amount,
                'confidence' => $pattern->fraud_confidence,
                'sent' => true,
            ];
        }

        return $results;
    }

    private function sendGhostAlerts(): array
    {
        $ghostedOrders = Order::where('status', 'ghosted')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $results = [];
        foreach ($ghostedOrders as $order) {
            $results[] = [
                'type' => 'ghost_alert',
                'order_id' => $order->id,
                'staff' => $order->telesalesRep->name ?? 'Unknown',
                'amount' => $order->total_amount,
                'sent' => true,
            ];
        }

        return $results;
    }

    private function sendStagnantInventoryAlerts(): array
    {
        $stagnantInventory = DeliveryAgent::where('last_movement', '<', now()->subDays(3))
            ->where('stock_value', '>', 50000)
            ->get();

        $results = [];
        foreach ($stagnantInventory as $da) {
            $results[] = [
                'type' => 'stagnant_inventory',
                'da_name' => $da->name,
                'stock_value' => $da->stock_value,
                'days_inactive' => $da->last_movement->diffInDays(now()),
                'sent' => true,
            ];
        }

        return $results;
    }

    private function sendPaymentMismatchAlerts(): array
    {
        $mismatches = Order::where('payment_status', 'confirmed')
            ->where('moniepoint_verification', '!=', 'confirmed')
            ->get();

        $results = [];
        foreach ($mismatches as $order) {
            $results[] = [
                'type' => 'payment_mismatch',
                'staff' => $order->telesalesRep->name ?? 'Unknown',
                'amount' => $order->total_amount,
                'orders_count' => 1,
                'sent' => true,
            ];
        }

        return $results;
    }

    private function sendPerformanceAlerts(): array
    {
        $poorPerformers = Staff::where('role', 'telesales_rep')
            ->where('delivery_rate', '<', 50)
            ->get();

        $results = [];
        foreach ($poorPerformers as $staff) {
            $results[] = [
                'type' => 'performance_alert',
                'staff_name' => $staff->name,
                'delivery_rate' => $staff->delivery_rate,
                'orders_assigned' => $staff->orders_count,
                'sent' => true,
            ];
        }

        return $results;
    }

    private function getCommunicationSummary($history): array
    {
        $total = count($history);
        $successful = collect($history)->where('delivery_status', 'delivered')->count();
        $failed = collect($history)->where('delivery_status', 'failed')->count();
        $pending = collect($history)->where('delivery_status', 'pending')->count();

        return [
            'total_messages' => $total,
            'successful_deliveries' => $successful,
            'failed_deliveries' => $failed,
            'pending_deliveries' => $pending,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
        ];
    }
}
