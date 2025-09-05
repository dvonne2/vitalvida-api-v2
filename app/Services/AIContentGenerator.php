<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIContentGenerator
{
    private Client $client;
    private string $claudeApiKey;
    private string $openaiApiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->claudeApiKey = config('services.claude.api_key');
        $this->openaiApiKey = config('services.openai.api_key');
    }

    public function generateAdCopy(array $parameters): array
    {
        $prompt = $this->buildAdCopyPrompt($parameters);
        
        try {
            $response = $this->client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->claudeApiKey,
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01'
                ],
                'json' => [
                    'model' => 'claude-3-sonnet-20240229',
                    'max_tokens' => 1000,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            return [
                'headline' => $this->extractHeadline($data['content'][0]['text']),
                'primary_text' => $this->extractPrimaryText($data['content'][0]['text']),
                'cta' => $this->extractCTA($data['content'][0]['text']),
                'variations' => $this->generateVariations($data['content'][0]['text'])
            ];
        } catch (\Exception $e) {
            Log::error('Claude API error', ['error' => $e->getMessage()]);
            return $this->getFallbackAdCopy($parameters);
        }
    }

    public function generateVideoScript(array $parameters): string
    {
        $prompt = "
        Create a 30-second UGC-style video script for Vitalvida Fulani Hair Gro.
        
        Scene: {$parameters['scene_type']}
        Character: Nigerian woman, {$parameters['age_range']}, {$parameters['persona']}
        Goal: {$parameters['conversion_goal']}
        
        Script should include:
        - Hook in first 3 seconds
        - Problem demonstration
        - Product application
        - Results reveal
        - Clear call-to-action
        - Natural Nigerian speech patterns
        
        Format as detailed shot-by-shot script with timing.
        ";

        return $this->callClaudeAPI($prompt);
    }

    public function generateWhatsAppMessage(array $parameters): string
    {
        $prompt = "
        Create a WhatsApp message for Vitalvida customer follow-up.
        
        Context: {$parameters['context']}
        Customer Stage: {$parameters['stage']}
        Previous Interaction: {$parameters['last_interaction']}
        Goal: {$parameters['goal']}
        
        Requirements:
        - Sound like a human, not a bot
        - Be culturally appropriate for Nigeria
        - Include customer's name if provided
        - Maximum 160 characters
        - Include subtle call-to-action
        - Match the urgency level: {$parameters['urgency']}
        
        Generate 3 variations: formal, casual, and friendly.
        ";

        return $this->callClaudeAPI($prompt);
    }

    public function generateSMSMessage(array $parameters): string
    {
        $prompt = "
        Create an SMS message for Vitalvida customer engagement.
        
        Context: {$parameters['context']}
        Customer: {$parameters['customer_name']}
        Goal: {$parameters['goal']}
        Urgency: {$parameters['urgency']}
        
        Requirements:
        - Maximum 160 characters
        - Clear call-to-action
        - Nigerian cultural context
        - Personal touch
        - Include customer name if available
        
        Generate 2 variations: urgent and friendly.
        ";

        return $this->callClaudeAPI($prompt);
    }

    public function generateEmailContent(array $parameters): array
    {
        $prompt = "
        Create an email campaign for Vitalvida customer engagement.
        
        Subject: {$parameters['subject_line']}
        Customer: {$parameters['customer_name']}
        Goal: {$parameters['goal']}
        Stage: {$parameters['stage']}
        
        Requirements:
        - Compelling subject line
        - Personalized greeting
        - Clear value proposition
        - Social proof elements
        - Strong call-to-action
        - Nigerian cultural references
        - Mobile-friendly format
        
        Generate subject line, body, and call-to-action.
        ";

        $content = $this->callClaudeAPI($prompt);
        
        return [
            'subject' => $this->extractSubjectLine($content),
            'body' => $this->extractEmailBody($content),
            'cta' => $this->extractCTA($content)
        ];
    }

    private function buildAdCopyPrompt(array $parameters): string
    {
        return "
        Create high-converting Facebook ad copy for Vitalvida's Fulani Hair Gro product.
        
        Target Audience: {$parameters['audience']}
        Platform: {$parameters['platform']}
        Pain Point: {$parameters['pain_point']}
        Goal: {$parameters['goal']}
        Tone: {$parameters['tone']}
        
        Requirements:
        - Write in Nigerian Pidgin English mixed with English
        - Focus on emotional transformation
        - Include social proof elements
        - Create urgency without being pushy
        - Use local cultural references
        - Maximum 125 characters for headline
        - Maximum 125 words for primary text
        - Include clear call-to-action
        
        Format your response as:
        HEADLINE: [headline here]
        PRIMARY TEXT: [primary text here]
        CTA: [call-to-action here]
        
        Generate 3 variations with different emotional angles.
        ";
    }

    private function callClaudeAPI(string $prompt): string
    {
        try {
            $response = $this->client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->claudeApiKey,
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01'
                ],
                'json' => [
                    'model' => 'claude-3-sonnet-20240229',
                    'max_tokens' => 1000,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['content'][0]['text'] ?? '';
        } catch (\Exception $e) {
            Log::error('Claude API error', ['error' => $e->getMessage()]);
            return $this->getFallbackContent($prompt);
        }
    }

    private function extractHeadline(string $content): string
    {
        if (preg_match('/HEADLINE:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Transform Your Hair Today!';
    }

    private function extractPrimaryText(string $content): string
    {
        if (preg_match('/PRIMARY TEXT:\s*(.+?)(?:\nCTA:|$)/is', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Say goodbye to thin edges and hello to thick, beautiful hair with our proven formula.';
    }

    private function extractCTA(string $content): string
    {
        if (preg_match('/CTA:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Order Now';
    }

    private function extractSubjectLine(string $content): string
    {
        if (preg_match('/SUBJECT:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Transform Your Hair Today!';
    }

    private function extractEmailBody(string $content): string
    {
        if (preg_match('/BODY:\s*(.+?)(?:\nCTA:|$)/is', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Hello! We noticed you might be interested in our hair growth products.';
    }

    private function generateVariations(string $baseContent): array
    {
        $variations = [];
        $baseText = $this->extractPrimaryText($baseContent);
        
        $styleModifiers = [
            'emotional' => 'Make this more emotional and heart-touching',
            'urgent' => 'Add urgency and scarcity elements',
            'social_proof' => 'Include social proof and testimonials'
        ];

        foreach ($styleModifiers as $style => $modifier) {
            $variations[] = $baseText . ' ' . $modifier;
        }

        return $variations;
    }

    private function getFallbackAdCopy(array $parameters): array
    {
        return [
            'headline' => 'Transform Your Hair Today!',
            'primary_text' => 'Say goodbye to thin edges and hello to thick, beautiful hair with our proven formula.',
            'cta' => 'Order Now',
            'variations' => [
                'Transform your hair journey starts here!',
                'Join thousands of satisfied customers!',
                'Limited time offer - act now!'
            ]
        ];
    }

    private function getFallbackContent(string $prompt): string
    {
        return 'Thank you for your interest in Vitalvida products. Please contact us for more information.';
    }
} 