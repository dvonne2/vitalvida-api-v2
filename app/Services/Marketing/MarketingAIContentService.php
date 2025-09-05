<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Exception;

class MarketingAIContentService
{
    public function generateContent($type, $audience, $tone, $product, $companyId)
    {
        try {
            // Get ERP product data
            $productData = Product::where('company_id', $companyId)
                ->where('name', 'like', "%{$product}%")
                ->first();
                
            // Get customer insights from ERP
            $customerInsights = $this->getCustomerInsights($companyId, $audience);
            
            // Build prompt with Nigerian market context
            $prompt = $this->buildPrompt($type, $audience, $tone, $productData, $customerInsights);
            
            // Generate content using OpenAI or similar AI service
            $generatedContent = $this->callAIService($prompt);
            
            // Generate variations to avoid repetition fatigue
            $variations = $this->generateVariations($generatedContent, $type);
            
            return [
                'primary' => $generatedContent,
                'variations' => $variations,
                'metadata' => [
                    'type' => $type,
                    'audience' => $audience,
                    'tone' => $tone,
                    'product_data' => $productData,
                    'customer_insights' => $customerInsights,
                    'market_context' => 'Nigerian'
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to generate content: ' . $e->getMessage());
        }
    }
    
    private function buildPrompt($type, $audience, $tone, $productData, $customerInsights)
    {
        $nigerianContext = $this->getNigerianMarketContext();
        
        $prompt = "Generate {$type} content for Nigerian hair care marketing with the following specifications:\n\n";
        $prompt .= "Target Audience: {$audience}\n";
        $prompt .= "Tone: {$tone}\n";
        
        if ($productData) {
            $prompt .= "Product: {$productData->name}\n";
            $prompt .= "Description: {$productData->description}\n";
            $prompt .= "Price: ₦" . number_format($productData->price, 2) . "\n";
        }
        
        $prompt .= "\nNigerian Market Context:\n";
        $prompt .= "- Use Nigerian currency (₦)\n";
        $prompt .= "- Reference local cities (Lagos, Abuja, Port Harcourt)\n";
        $prompt .= "- Include cultural touchpoints relevant to Nigerian women\n";
        $prompt .= "- Use local expressions and relatable language\n";
        $prompt .= "- Consider Nigerian hair care preferences and challenges\n";
        
        if ($customerInsights) {
            $prompt .= "\nCustomer Insights:\n";
            $prompt .= "- Average age: {$customerInsights->avg_age}\n";
            $prompt .= "- Total customers: {$customerInsights->total_customers}\n";
            $prompt .= "- Average lifetime value: ₦" . number_format($customerInsights->avg_ltv, 2) . "\n";
            $prompt .= "- Most common location: {$customerInsights->most_common_location}\n";
        }
        
        $prompt .= "\nContent Requirements:\n";
        $prompt .= "- Make it engaging and conversion-focused\n";
        $prompt .= "- Include clear call-to-action\n";
        $prompt .= "- Address common Nigerian hair care concerns\n";
        $prompt .= "- Use emojis appropriately for social media\n";
        $prompt .= "- Keep it authentic and culturally relevant\n";
        
        return $prompt;
    }
    
    private function callAIService($prompt)
    {
        // Integration with OpenAI or similar AI service
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a marketing expert specializing in Nigerian hair care products. Generate compelling, culturally relevant marketing content.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ]);
        
        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }
        
        throw new Exception('AI service request failed: ' . $response->body());
    }
    
    private function generateVariations($originalContent, $type)
    {
        $variations = [];
        
        // Generate 3-5 variations with different approaches
        $approaches = [
            'emotional' => 'Focus on emotional benefits and feelings',
            'rational' => 'Focus on practical benefits and features',
            'social' => 'Focus on social proof and community',
            'urgency' => 'Focus on limited time offers and scarcity',
            'story' => 'Focus on storytelling and personal experience'
        ];
        
        foreach ($approaches as $approach => $description) {
            $variationPrompt = "Create a variation of this content focusing on {$description}:\n\n{$originalContent}";
            
            try {
                $variation = $this->callAIService($variationPrompt);
                $variations[] = [
                    'approach' => $approach,
                    'content' => $variation
                ];
            } catch (Exception $e) {
                // Continue with other variations if one fails
                continue;
            }
        }
        
        return $variations;
    }
    
    private function getCustomerInsights($companyId, $audience)
    {
        return Customer::where('company_id', $companyId)
            ->where('segment', $audience)
            ->selectRaw('
                AVG(age) as avg_age,
                COUNT(*) as total_customers,
                AVG(lifetime_value) as avg_ltv,
                most_common_location
            ')
            ->first();
    }
    
    private function getNigerianMarketContext()
    {
        return [
            'cities' => ['Lagos', 'Abuja', 'Port Harcourt', 'Kano', 'Ibadan', 'Kaduna'],
            'hair_types' => ['4A', '4B', '4C', '3A', '3B', '3C'],
            'common_concerns' => [
                'Hair breakage and damage',
                'Dry scalp and dandruff',
                'Hair growth and retention',
                'Natural hair maintenance',
                'Styling and versatility'
            ],
            'cultural_touchpoints' => [
                'Nigerian beauty standards',
                'Traditional hair care practices',
                'Modern hair care trends',
                'Professional appearance',
                'Cultural celebrations'
            ],
            'local_expressions' => [
                'Naija hair',
                'Natural hair journey',
                'Hair goals',
                'Slay queen',
                'Boss lady'
            ]
        ];
    }
    
    public function generateContentVariations($originalContent, $companyId)
    {
        // Generate content variations to avoid repetition fatigue
        $interactionHistory = \App\Models\Marketing\MarketingCustomerTouchpoint::where('company_id', $companyId)
            ->where('content_id', $originalContent->id)
            ->count();
            
        $variationCount = min($interactionHistory + 3, 10); // Max 10 variations
        
        $variations = [];
        
        for ($i = 0; $i < $variationCount; $i++) {
            try {
                $variation = $this->callAIService("Create a unique variation of this content: {$originalContent->title}");
                $variations[] = $variation;
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $variations;
    }
}
