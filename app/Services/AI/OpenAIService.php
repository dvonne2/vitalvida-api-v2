<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIService
{
    protected $apiKey;
    protected $baseUrl;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
        $this->model = config('services.openai.model', 'gpt-4');
    }

    /**
     * Generate content using OpenAI API
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        try {
            if (!$this->apiKey) {
                return [
                    'success' => false,
                    'error' => 'OpenAI API key not configured'
                ];
            }

            $defaultOptions = [
                'max_tokens' => 300,
                'temperature' => 0.7,
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ];

            $options = array_merge($defaultOptions, $options);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional marketing copywriter specializing in Nigerian hair care and beauty products. Create compelling, culturally relevant content that resonates with Nigerian women.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $options['max_tokens'],
                'temperature' => $options['temperature'],
                'top_p' => $options['top_p'],
                'frequency_penalty' => $options['frequency_penalty'],
                'presence_penalty' => $options['presence_penalty'],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? [];

                // Calculate cost (approximate)
                $cost = $this->calculateCost($usage);

                return [
                    'success' => true,
                    'content' => trim($content),
                    'usage_cost' => $cost,
                    'tokens_used' => $usage['total_tokens'] ?? 0,
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                ];
            } else {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'prompt' => $prompt
                ]);

                return [
                    'success' => false,
                    'error' => 'OpenAI API request failed: ' . $response->status(),
                    'details' => $response->json()
                ];
            }

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'error' => $e->getMessage(),
                'prompt' => $prompt
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate multiple content variations
     */
    public function generateVariations(string $prompt, int $count = 3, array $options = []): array
    {
        $variations = [];
        $totalCost = 0;
        $totalTokens = 0;

        for ($i = 0; $i < $count; $i++) {
            // Add variation to prompt
            $variationPrompt = $prompt . "\n\nPlease provide variation " . ($i + 1) . " with a slightly different approach.";
            
            $result = $this->generateContent($variationPrompt, $options);
            
            if ($result['success']) {
                $variations[] = [
                    'id' => $i + 1,
                    'content' => $result['content'],
                    'tokens_used' => $result['tokens_used'],
                    'cost' => $result['usage_cost'],
                ];
                
                $totalCost += $result['usage_cost'];
                $totalTokens += $result['tokens_used'];
            }
        }

        return [
            'success' => count($variations) > 0,
            'variations' => $variations,
            'total_cost' => $totalCost,
            'total_tokens' => $totalTokens,
        ];
    }

    /**
     * Generate content with specific tone and style
     */
    public function generateStyledContent(string $prompt, string $tone, string $style, array $options = []): array
    {
        $styledPrompt = $this->buildStyledPrompt($prompt, $tone, $style);
        return $this->generateContent($styledPrompt, $options);
    }

    /**
     * Generate hashtags for social media
     */
    public function generateHashtags(string $content, string $platform, int $count = 10): array
    {
        $prompt = "Generate {$count} relevant hashtags for this {$platform} post about Nigerian hair care:\n\n{$content}\n\nHashtags should be popular, relevant to Nigerian beauty/hair care, and appropriate for {$platform}. Return only the hashtags separated by spaces.";

        $result = $this->generateContent($prompt, [
            'max_tokens' => 100,
            'temperature' => 0.8,
        ]);

        if ($result['success']) {
            $hashtags = explode(' ', $result['content']);
            $hashtags = array_filter($hashtags, function($tag) {
                return strpos($tag, '#') === 0;
            });
            
            return [
                'success' => true,
                'hashtags' => array_slice($hashtags, 0, $count),
                'usage_cost' => $result['usage_cost'],
                'tokens_used' => $result['tokens_used'],
            ];
        }

        return $result;
    }

    /**
     * Optimize existing content
     */
    public function optimizeContent(string $content, string $platform, string $goal): array
    {
        $prompt = "Optimize this marketing content for {$platform} to achieve {$goal}:\n\n{$content}\n\nProvide the optimized version with explanations of changes made.";

        return $this->generateContent($prompt, [
            'max_tokens' => 500,
            'temperature' => 0.6,
        ]);
    }

    /**
     * Generate A/B testing variations
     */
    public function generateABTestVariations(string $originalContent, string $testType): array
    {
        $prompt = "Create an A/B test variation for this content. Test type: {$testType}\n\nOriginal: {$originalContent}\n\nCreate a variation that tests different approaches while maintaining the same core message.";

        return $this->generateContent($prompt, [
            'max_tokens' => 400,
            'temperature' => 0.7,
        ]);
    }

    /**
     * Calculate cost based on token usage
     */
    private function calculateCost(array $usage): float
    {
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        
        // GPT-4 pricing (approximate, may vary)
        $promptCostPer1k = 0.03; // $0.03 per 1K tokens
        $completionCostPer1k = 0.06; // $0.06 per 1K tokens
        
        $promptCost = ($promptTokens / 1000) * $promptCostPer1k;
        $completionCost = ($completionTokens / 1000) * $completionCostPer1k;
        
        return round($promptCost + $completionCost, 4);
    }

    /**
     * Build styled prompt
     */
    private function buildStyledPrompt(string $prompt, string $tone, string $style): string
    {
        $toneInstructions = [
            'professional' => 'Use formal, business-like language with industry terminology',
            'casual' => 'Use relaxed, conversational language as if talking to a friend',
            'friendly' => 'Use warm, approachable language with positive energy',
            'urgent' => 'Create a sense of urgency and immediate action',
            'emotional' => 'Appeal to emotions and personal connections',
        ];

        $styleInstructions = [
            'storytelling' => 'Frame the content as a story or narrative',
            'benefit-focused' => 'Emphasize benefits and outcomes',
            'problem-solution' => 'Present a problem and offer a solution',
            'testimonial-style' => 'Write as if it\'s a customer testimonial',
            'educational' => 'Provide valuable information and insights',
        ];

        $toneInstruction = $toneInstructions[$tone] ?? '';
        $styleInstruction = $styleInstructions[$style] ?? '';

        return "{$prompt}\n\nTone: {$toneInstruction}\nStyle: {$styleInstruction}";
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(): array
    {
        $cacheKey = 'openai_usage_stats';
        
        return Cache::remember($cacheKey, 3600, function () {
            // This would typically call OpenAI's usage endpoint
            // For now, return mock data
            return [
                'total_usage' => 0,
                'daily_usage' => 0,
                'monthly_usage' => 0,
                'costs' => [
                    'today' => 0,
                    'this_month' => 0,
                    'total' => 0,
                ]
            ];
        });
    }

    /**
     * Validate API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'OpenAI API connection successful',
                    'models' => $response->json()['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API connection failed: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}
