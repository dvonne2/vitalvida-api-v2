<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingWhatsAppLog;
use App\Models\Customer;
use App\Services\Marketing\MarketingWhatsAppBusinessService;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppBulkMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customerId;
    protected $message;
    protected $campaignId;
    protected $companyId;
    protected $sequenceId;
    protected $action;

    public function __construct($customerId = null, $action = null, $sequenceId = null, $companyId = null)
    {
        $this->customerId = $customerId;
        $this->action = $action;
        $this->sequenceId = $sequenceId;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        try {
            if ($this->customerId) {
                $this->processSingleCustomer();
            } else {
                $this->processBulkCustomers();
            }

        } catch (\Exception $e) {
            Log::error("Failed to process WhatsApp bulk message", [
                'customer_id' => $this->customerId,
                'action' => $this->action,
                'sequence_id' => $this->sequenceId,
                'company_id' => $this->companyId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function processSingleCustomer()
    {
        $customer = Customer::findOrFail($this->customerId);
        
        if (!$customer->whatsapp) {
            Log::warning("Customer has no WhatsApp number", [
                'customer_id' => $this->customerId
            ]);
            return;
        }

        $message = $this->getMessageForAction($customer);
        
        if (!$message) {
            Log::warning("No message found for action", [
                'customer_id' => $this->customerId,
                'action' => $this->action
            ]);
            return;
        }

        $this->sendWhatsAppMessage($customer, $message);
    }

    protected function processBulkCustomers()
    {
        $query = Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        // Get customers with WhatsApp numbers
        $customers = $query->whereNotNull('whatsapp')
                          ->where('status', 'active')
                          ->limit(100) // Process in batches
                          ->get();

        Log::info("Processing bulk WhatsApp messages", [
            'customers_count' => $customers->count(),
            'company_id' => $this->companyId
        ]);

        foreach ($customers as $customer) {
            $message = $this->getBulkMessage($customer);
            $this->sendWhatsAppMessage($customer, $message);
            
            // Add delay to avoid rate limiting
            sleep(1);
        }
    }

    protected function getMessageForAction($customer)
    {
        switch ($this->action) {
            case 'welcome_message':
                return $this->getWelcomeMessage($customer);
                
            case 'offer_message':
                return $this->getOfferMessage($customer);
                
            case 'follow_up':
                return $this->getFollowUpMessage($customer);
                
            case 'reminder':
                return $this->getReminderMessage($customer);
                
            default:
                return $this->getDefaultMessage($customer);
        }
    }

    protected function getWelcomeMessage($customer)
    {
        return "🎉 Welcome to VitalVida, {$customer->name}! 

We're excited to have you on board. Here's what you can expect from us:

✨ Premium health products
🚚 Fast delivery nationwide
💬 24/7 customer support
🎁 Exclusive member benefits

Reply 'START' to begin your wellness journey!";
    }

    protected function getOfferMessage($customer)
    {
        return "🔥 SPECIAL OFFER for {$customer->name}!

Limited time: 20% OFF on all premium health products!

⚡ Valid until tomorrow
🎯 Free shipping on orders above ₦10,000
💳 Secure payment options

Reply 'OFFER' to claim your discount!";
    }

    protected function getFollowUpMessage($customer)
    {
        return "Hi {$customer->name}! 👋

Just checking in to see how you're enjoying your VitalVida products.

💡 Need recommendations?
❓ Have questions?
⭐ Want to share your experience?

Reply 'HELP' for assistance or 'REVIEW' to share feedback!";
    }

    protected function getReminderMessage($customer)
    {
        return "⏰ Reminder for {$customer->name}

Don't forget to restock your favorite VitalVida products!

🛒 Your cart is waiting
🚚 Same-day delivery available
💎 Premium quality guaranteed

Reply 'SHOP' to browse our collection!";
    }

    protected function getDefaultMessage($customer)
    {
        return "Hello {$customer->name}! 

Thank you for choosing VitalVida for your health and wellness needs.

🌿 Natural ingredients
🔬 Scientifically proven
💪 Quality guaranteed

Reply 'INFO' for more details!";
    }

    protected function getBulkMessage($customer)
    {
        return "🌟 Hello {$customer->name}!

Discover the power of natural wellness with VitalVida!

🌿 Premium health supplements
🚚 Nationwide delivery
💎 Quality guaranteed
🎁 Special member benefits

Reply 'DISCOVER' to explore our products!";
    }

    protected function sendWhatsAppMessage($customer, $message)
    {
        try {
            $whatsappService = app(MarketingWhatsAppBusinessService::class);
            
            $response = $whatsappService->sendBulkMessage(
                $customer->whatsapp,
                $message,
                $this->campaignId ?? null,
                $this->companyId
            );

            // Log the message
            MarketingWhatsAppLog::create([
                'customer_id' => $customer->id,
                'campaign_id' => $this->campaignId,
                'message' => $message,
                'phone_number' => $customer->whatsapp,
                'status' => $response['status'] ?? 'sent',
                'provider_response' => $response,
                'metadata' => [
                    'action' => $this->action,
                    'sequence_id' => $this->sequenceId,
                    'company_id' => $this->companyId
                ],
                'company_id' => $this->companyId
            ]);

            // Update customer last contacted time
            $customer->update([
                'last_contacted_at' => now()
            ]);

            Log::info("WhatsApp message sent successfully", [
                'customer_id' => $customer->id,
                'phone' => $customer->whatsapp,
                'status' => $response['status'] ?? 'sent'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message", [
                'customer_id' => $customer->id,
                'phone' => $customer->whatsapp,
                'error' => $e->getMessage()
            ]);

            // Log failed attempt
            MarketingWhatsAppLog::create([
                'customer_id' => $customer->id,
                'campaign_id' => $this->campaignId,
                'message' => $message,
                'phone_number' => $customer->whatsapp,
                'status' => 'failed',
                'provider_response' => ['error' => $e->getMessage()],
                'metadata' => [
                    'action' => $this->action,
                    'sequence_id' => $this->sequenceId,
                    'company_id' => $this->companyId
                ],
                'company_id' => $this->companyId
            ]);
        }
    }
}
