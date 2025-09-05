<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Customer;
use App\Services\Marketing\MarketingWhatsAppBusinessService;
use App\Services\Marketing\MarketingEmailService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessMarketingAutomationSequence implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sequenceId;
    protected $triggerType;
    protected $triggerData;
    protected $companyId;

    public function __construct($sequenceId, $triggerType = 'manual', $triggerData = [], $companyId = null)
    {
        $this->sequenceId = $sequenceId;
        $this->triggerType = $triggerType;
        $this->triggerData = $triggerData;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        try {
            Log::info("Processing marketing automation sequence", [
                'sequence_id' => $this->sequenceId,
                'trigger_type' => $this->triggerType,
                'company_id' => $this->companyId
            ]);

            // Get customers based on trigger
            $customers = $this->getTriggeredCustomers();
            
            if ($customers->isEmpty()) {
                Log::info("No customers found for automation sequence", [
                    'sequence_id' => $this->sequenceId
                ]);
                return;
            }

            // Process each customer through the sequence
            foreach ($customers as $customer) {
                $this->processCustomerSequence($customer);
            }

            Log::info("Marketing automation sequence completed", [
                'sequence_id' => $this->sequenceId,
                'customers_processed' => $customers->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process marketing automation sequence", [
                'sequence_id' => $this->sequenceId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function getTriggeredCustomers()
    {
        $query = Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        switch ($this->triggerType) {
            case 'new_customer':
                return $query->where('created_at', '>=', now()->subDays(1))->get();
                
            case 'inactive_customer':
                return $query->where('last_contacted_at', '<=', now()->subDays(30))->get();
                
            case 'high_value_customer':
                return $query->whereJsonContains('tags', 'high_value')->get();
                
            case 'abandoned_cart':
                // This would typically come from e-commerce integration
                return $query->whereJsonContains('tags', 'abandoned_cart')->get();
                
            case 'custom':
                return $this->getCustomTriggeredCustomers();
                
            default:
                return collect();
        }
    }

    protected function getCustomTriggeredCustomers()
    {
        $query = Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        // Apply custom filters from trigger data
        if (isset($this->triggerData['status'])) {
            $query->where('status', $this->triggerData['status']);
        }

        if (isset($this->triggerData['tags'])) {
            $query->whereJsonContains('tags', $this->triggerData['tags']);
        }

        if (isset($this->triggerData['source'])) {
            $query->where('source', $this->triggerData['source']);
        }

        return $query->get();
    }

    protected function processCustomerSequence($customer)
    {
        // Define the automation sequence steps
        $sequenceSteps = [
            [
                'step' => 1,
                'action' => 'welcome_message',
                'delay' => 0,
                'channel' => 'whatsapp'
            ],
            [
                'step' => 2,
                'action' => 'follow_up_email',
                'delay' => 24, // hours
                'channel' => 'email'
            ],
            [
                'step' => 3,
                'action' => 'offer_message',
                'delay' => 72, // hours
                'channel' => 'whatsapp'
            ],
            [
                'step' => 4,
                'action' => 'final_reminder',
                'delay' => 168, // hours (1 week)
                'channel' => 'email'
            ]
        ];

        foreach ($sequenceSteps as $step) {
            $this->scheduleSequenceStep($customer, $step);
        }

        // Log the sequence start
        MarketingCustomerTouchpoint::create([
            'customer_id' => $customer->id,
            'campaign_id' => null,
            'touchpoint_type' => 'automation_sequence',
            'channel' => 'multi',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'metadata' => [
                'sequence_id' => $this->sequenceId,
                'trigger_type' => $this->triggerType,
                'total_steps' => count($sequenceSteps)
            ],
            'company_id' => $this->companyId
        ]);
    }

    protected function scheduleSequenceStep($customer, $step)
    {
        $scheduledAt = now()->addHours($step['delay']);
        
        // Schedule the actual message sending
        $jobClass = $this->getJobClassForStep($step);
        
        if ($jobClass) {
            $jobClass::dispatch(
                $customer->id,
                $step['action'],
                $this->sequenceId,
                $this->companyId
            )->delay($scheduledAt);
        }

        // Log the scheduled touchpoint
        MarketingCustomerTouchpoint::create([
            'customer_id' => $customer->id,
            'campaign_id' => null,
            'touchpoint_type' => $step['action'],
            'channel' => $step['channel'],
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'metadata' => [
                'sequence_id' => $this->sequenceId,
                'step_number' => $step['step'],
                'delay_hours' => $step['delay']
            ],
            'company_id' => $this->companyId
        ]);
    }

    protected function getJobClassForStep($step)
    {
        switch ($step['channel']) {
            case 'whatsapp':
                return ProcessWhatsAppBulkMessage::class;
            case 'email':
                return ProcessMarketingAutomationSequence::class; // This would be an email job
            default:
                return null;
        }
    }
}
