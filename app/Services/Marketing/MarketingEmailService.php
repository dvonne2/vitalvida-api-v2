<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MarketingEmailService
{
    protected $companyId;
    protected $defaultFromEmail;
    protected $defaultFromName;

    public function __construct($companyId = null)
    {
        $this->companyId = $companyId;
        $this->defaultFromEmail = config('mail.from.address', 'noreply@vitalvida.com');
        $this->defaultFromName = config('mail.from.name', 'VitalVida Marketing');
    }

    /**
     * Send campaign email
     */
    public function sendCampaignEmail($email, $subject, $content, $campaignId = null, $metadata = [])
    {
        try {
            $emailData = [
                'subject' => $subject,
                'content' => $content,
                'campaign_id' => $campaignId,
                'metadata' => $metadata
            ];

            // Send email using Laravel's Mail facade
            Mail::send('emails.marketing.campaign', $emailData, function ($message) use ($email, $subject) {
                $message->to($email)
                        ->subject($subject)
                        ->from($this->defaultFromEmail, $this->defaultFromName);
            });

            // Log successful email
            $this->logEmailTouchpoint($email, $campaignId, 'sent', $metadata);

            Log::info("Campaign email sent successfully", [
                'email' => $email,
                'campaign_id' => $campaignId,
                'subject' => $subject
            ]);

            return [
                'status' => 'sent',
                'email' => $email,
                'campaign_id' => $campaignId
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send campaign email", [
                'email' => $email,
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            // Log failed email
            $this->logEmailTouchpoint($email, $campaignId, 'failed', $metadata);

            return [
                'status' => 'failed',
                'email' => $email,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk campaign emails
     */
    public function sendBulkCampaignEmails($campaignId, $customerIds = [], $filters = [])
    {
        $campaign = MarketingCampaign::findOrFail($campaignId);
        
        // Get customers based on filters or provided IDs
        $customers = $this->getTargetCustomers($customerIds, $filters);
        
        $results = [
            'total' => $customers->count(),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($customers as $customer) {
            if (!$customer->email) {
                $results['failed']++;
                $results['errors'][] = "No email for customer {$customer->id}";
                continue;
            }

            $result = $this->sendCampaignEmail(
                $customer->email,
                $campaign->email_subject,
                $campaign->email_content,
                $campaignId,
                [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name
                ]
            );

            if ($result['status'] === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result['error'];
            }

            // Add delay to avoid rate limiting
            usleep(100000); // 0.1 second delay
        }

        Log::info("Bulk campaign emails completed", [
            'campaign_id' => $campaignId,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Send personalized email
     */
    public function sendPersonalizedEmail($customerId, $template, $data = [])
    {
        $customer = Customer::findOrFail($customerId);
        
        if (!$customer->email) {
            throw new \Exception("Customer has no email address");
        }

        // Personalize content
        $personalizedContent = $this->personalizeContent($template, $customer, $data);
        
        return $this->sendCampaignEmail(
            $customer->email,
            $personalizedContent['subject'],
            $personalizedContent['content'],
            null,
            [
                'customer_id' => $customer->id,
                'template' => $template,
                'personalized' => true
            ]
        );
    }

    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        
        $welcomeData = [
            'customer_name' => $customer->name,
            'welcome_message' => "Welcome to VitalVida! We're excited to have you on board.",
            'next_steps' => [
                'Complete your profile',
                'Browse our products',
                'Join our loyalty program'
            ],
            'special_offer' => 'Get 10% off your first order with code WELCOME10'
        ];

        return $this->sendPersonalizedEmail($customerId, 'welcome', $welcomeData);
    }

    /**
     * Send abandoned cart email
     */
    public function sendAbandonedCartEmail($customerId, $cartItems = [])
    {
        $customer = Customer::findOrFail($customerId);
        
        $cartData = [
            'customer_name' => $customer->name,
            'cart_items' => $cartItems,
            'cart_total' => array_sum(array_column($cartItems, 'price')),
            'recovery_offer' => 'Complete your purchase and get free shipping!'
        ];

        return $this->sendPersonalizedEmail($customerId, 'abandoned_cart', $cartData);
    }

    /**
     * Send follow-up email
     */
    public function sendFollowUpEmail($customerId, $daysSinceLastContact = 7)
    {
        $customer = Customer::findOrFail($customerId);
        
        $followUpData = [
            'customer_name' => $customer->name,
            'days_since_contact' => $daysSinceLastContact,
            'reengagement_offer' => 'We miss you! Here\'s 15% off your next order.',
            'new_products' => $this->getNewProducts()
        ];

        return $this->sendPersonalizedEmail($customerId, 'follow_up', $followUpData);
    }

    /**
     * Get target customers
     */
    protected function getTargetCustomers($customerIds = [], $filters = [])
    {
        $query = Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        if (!empty($customerIds)) {
            $query->whereIn('id', $customerIds);
        }

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        if (isset($filters['last_contacted_before'])) {
            $query->where('last_contacted_at', '<=', $filters['last_contacted_before']);
        }

        if (isset($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->whereNotNull('email')->get();
    }

    /**
     * Personalize content
     */
    protected function personalizeContent($template, $customer, $data)
    {
        $content = $this->getEmailTemplate($template);
        
        // Replace placeholders
        $replacements = [
            '{{customer_name}}' => $customer->name,
            '{{customer_email}}' => $customer->email,
            '{{company_name}}' => 'VitalVida',
            '{{unsubscribe_link}}' => $this->generateUnsubscribeLink($customer->email),
            '{{preferences_link}}' => $this->generatePreferencesLink($customer->email)
        ];

        // Add custom data replacements
        foreach ($data as $key => $value) {
            $replacements["{{$key}}"] = $value;
        }

        $personalizedContent = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );

        return [
            'subject' => $personalizedContent['subject'] ?? 'Message from VitalVida',
            'content' => $personalizedContent['body'] ?? $personalizedContent
        ];
    }

    /**
     * Get email template
     */
    protected function getEmailTemplate($template)
    {
        $templates = [
            'welcome' => [
                'subject' => 'Welcome to VitalVida!',
                'body' => $this->getWelcomeTemplate()
            ],
            'abandoned_cart' => [
                'subject' => 'Complete Your Purchase - Special Offer Inside!',
                'body' => $this->getAbandonedCartTemplate()
            ],
            'follow_up' => [
                'subject' => 'We Miss You! Special Offer Just for You',
                'body' => $this->getFollowUpTemplate()
            ]
        ];

        return $templates[$template] ?? [
            'subject' => 'Message from VitalVida',
            'body' => 'Thank you for your interest in VitalVida!'
        ];
    }

    /**
     * Get welcome template
     */
    protected function getWelcomeTemplate()
    {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h1>Welcome to VitalVida, {{customer_name}}!</h1>
            <p>{{welcome_message}}</p>
            <h3>Next Steps:</h3>
            <ul>
                @foreach($next_steps as $step)
                    <li>{{$step}}</li>
                @endforeach
            </ul>
            <div style="background-color: #f0f8ff; padding: 20px; margin: 20px 0;">
                <h3>Special Offer: {{special_offer}}</h3>
            </div>
            <p>Best regards,<br>The VitalVida Team</p>
        </div>';
    }

    /**
     * Get abandoned cart template
     */
    protected function getAbandonedCartTemplate()
    {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h1>Hi {{customer_name}},</h1>
            <p>We noticed you left some items in your cart. Don\'t miss out!</p>
            <h3>Your Cart Items:</h3>
            <ul>
                @foreach($cart_items as $item)
                    <li>{{$item["name"]}} - ₦{{$item["price"]}}</li>
                @endforeach
            </ul>
            <p><strong>Total: ₦{{cart_total}}</strong></p>
            <div style="background-color: #fff3cd; padding: 20px; margin: 20px 0;">
                <h3>{{recovery_offer}}</h3>
            </div>
            <p>Best regards,<br>The VitalVida Team</p>
        </div>';
    }

    /**
     * Get follow-up template
     */
    protected function getFollowUpTemplate()
    {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h1>Hi {{customer_name}},</h1>
            <p>It\'s been {{days_since_contact}} days since we last heard from you. We miss you!</p>
            <div style="background-color: #d4edda; padding: 20px; margin: 20px 0;">
                <h3>{{reengagement_offer}}</h3>
            </div>
            <h3>New Products You Might Like:</h3>
            <ul>
                @foreach($new_products as $product)
                    <li>{{$product["name"]}} - {{$product["description"]}}</li>
                @endforeach
            </ul>
            <p>Best regards,<br>The VitalVida Team</p>
        </div>';
    }

    /**
     * Generate unsubscribe link
     */
    protected function generateUnsubscribeLink($email)
    {
        $token = base64_encode($email . '|' . time());
        return url('/unsubscribe?token=' . $token);
    }

    /**
     * Generate preferences link
     */
    protected function generatePreferencesLink($email)
    {
        $token = base64_encode($email . '|' . time());
        return url('/preferences?token=' . $token);
    }

    /**
     * Get new products
     */
    protected function getNewProducts()
    {
        // This would typically fetch from your product database
        return [
            ['name' => 'Premium Vitamin C', 'description' => 'Boost your immunity'],
            ['name' => 'Omega-3 Fish Oil', 'description' => 'Support heart health'],
            ['name' => 'Probiotic Blend', 'description' => 'Improve gut health']
        ];
    }

    /**
     * Log email touchpoint
     */
    protected function logEmailTouchpoint($email, $campaignId, $status, $metadata = [])
    {
        $customer = Customer::where('email', $email)->first();
        
        if ($customer) {
            MarketingCustomerTouchpoint::create([
                'customer_id' => $customer->id,
                'campaign_id' => $campaignId,
                'touchpoint_type' => 'email_sent',
                'channel' => 'email',
                'status' => $status,
                'metadata' => array_merge($metadata, ['email' => $email]),
                'company_id' => $this->companyId
            ]);
        }
    }
}
