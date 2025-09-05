<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Marketing\MarketingWhatsAppLog;
use App\Models\Customer;
use App\Services\Marketing\MarketingWhatsAppBusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MarketingWhatsAppController extends Controller
{
    protected $whatsappService;
    
    public function __construct(MarketingWhatsAppBusinessService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'message' => 'required|string',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'template_id' => 'nullable|string',
            'campaign_id' => 'nullable|uuid|exists:marketing_campaigns,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find customer in ERP
            $customer = Customer::where('phone', $request->phone)
                ->where('company_id', auth()->user()->company_id)
                ->first();
            
            // Send message with 3-provider failover system
            $response = $this->whatsappService->sendMessage(
                $request->phone, 
                $request->message,
                auth()->user()->company_id
            );
            
            // Log touchpoint
            MarketingCustomerTouchpoint::create([
                'customer_id' => $customer?->id,
                'brand_id' => $request->brand_id,
                'channel' => 'whatsapp',
                'touchpoint_type' => 'message_sent',
                'interaction_type' => 'sent',
                'whatsapp_provider' => $response['provider'] ?? null,
                'metadata' => [
                    'template_id' => $request->template_id,
                    'campaign_id' => $request->campaign_id,
                    'response' => $response
                ],
                'company_id' => auth()->user()->company_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $response
            ]);
            
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function bulkSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array|min:1',
            'recipients.*.phone' => 'required|string',
            'message' => 'required|string',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'campaign_id' => 'nullable|uuid|exists:marketing_campaigns,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $results = $this->whatsappService->bulkSend(
                $request->recipients,
                $request->message,
                auth()->user()->company_id
            );
            
            // Log touchpoints for successful sends
            foreach ($results as $result) {
                if ($result['status'] === 'sent' || $result['status'] === 'sent_with_failover') {
                    $customer = Customer::where('phone', $result['phone'])
                        ->where('company_id', auth()->user()->company_id)
                        ->first();
                        
                    MarketingCustomerTouchpoint::create([
                        'customer_id' => $customer?->id,
                        'brand_id' => $request->brand_id,
                        'channel' => 'whatsapp',
                        'touchpoint_type' => 'bulk_message_sent',
                        'interaction_type' => 'sent',
                        'whatsapp_provider' => $result['provider'] ?? null,
                        'metadata' => [
                            'campaign_id' => $request->campaign_id,
                            'bulk_send_result' => $result
                        ],
                        'company_id' => auth()->user()->company_id
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Bulk send completed',
                'data' => [
                    'total' => count($request->recipients),
                    'results' => $results,
                    'successful' => count(array_filter($results, fn($r) => $r['status'] === 'sent' || $r['status'] === 'sent_with_failover')),
                    'failed' => count(array_filter($results, fn($r) => $r['status'] === 'failed'))
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WhatsApp bulk send failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk messages: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function automateSequence(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_stage' => 'required|string',
            'sequence' => 'required|array',
            'sequence.*.message' => 'required|string',
            'sequence.*.delay_minutes' => 'required|integer|min:1',
            'brand_id' => 'required|uuid|exists:marketing_brands,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Get customers by marketing stage from ERP
            $customers = Customer::where('company_id', auth()->user()->company_id)
                ->where('marketing_stage', $request->target_stage)
                ->where('whatsapp_consent', true)
                ->get();
            
            $scheduledCount = 0;
            
            foreach ($customers as $customer) {
                foreach ($request->sequence as $index => $step) {
                    // Schedule message with delay
                    $delayMinutes = $index * $step['delay_minutes'];
                    
                    // Dispatch job to send message after delay
                    \App\Jobs\Marketing\SendDelayedWhatsAppMessage::dispatch(
                        $customer->phone,
                        $step['message'],
                        $request->brand_id,
                        auth()->user()->company_id,
                        $customer->id
                    )->delay(now()->addMinutes($delayMinutes));
                    
                    $scheduledCount++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Automation sequence scheduled',
                'data' => [
                    'customers_targeted' => $customers->count(),
                    'messages_scheduled' => $scheduledCount,
                    'sequence_steps' => count($request->sequence)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WhatsApp automation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule automation: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getTemplates()
    {
        // Return WhatsApp message templates
        $templates = [
            [
                'id' => 'welcome_template',
                'name' => 'Welcome Message',
                'content' => 'Hello {{name}}! Welcome to {{brand}}. We\'re excited to have you join our community. 🎉',
                'variables' => ['name', 'brand']
            ],
            [
                'id' => 'product_launch',
                'name' => 'Product Launch',
                'content' => '🎉 New Product Alert! {{product_name}} is now available. Limited time offer: {{discount}}. Order now!',
                'variables' => ['product_name', 'discount']
            ],
            [
                'id' => 'follow_up',
                'name' => 'Follow Up',
                'content' => 'Hi {{name}}, just checking in! How are you enjoying your {{product}}? Any questions?',
                'variables' => ['name', 'product']
            ],
            [
                'id' => 'promotional',
                'name' => 'Promotional Offer',
                'content' => '🔥 Special Offer! {{offer_description}}. Use code {{promo_code}} for {{discount}} off. Valid until {{expiry_date}}.',
                'variables' => ['offer_description', 'promo_code', 'discount', 'expiry_date']
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }
    
    public function getProviderStatus()
    {
        try {
            $status = $this->whatsappService->getProviderStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function switchProvider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'primary_provider' => 'required|string|in:wamation,ebulksms,whatsapp_business'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $brand = \App\Models\Marketing\MarketingBrand::where('id', $request->brand_id)
                ->where('company_id', auth()->user()->company_id)
                ->first();
                
            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand not found'
                ], 404);
            }
            
            $whatsappConfig = $brand->whatsapp_config ?? [];
            $whatsappConfig['primary_provider'] = $request->primary_provider;
            
            $brand->update(['whatsapp_config' => $whatsappConfig]);
            
            return response()->json([
                'success' => true,
                'message' => 'Primary provider updated',
                'data' => $brand->whatsapp_config
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch provider: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getLogs(Request $request)
    {
        $query = MarketingWhatsAppLog::where('company_id', auth()->user()->company_id);
        
        // Apply filters
        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $logs = $query->with(['user', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));
            
        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
