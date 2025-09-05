<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingContentLibrary;
use App\Services\AI\AIContentGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMarketingContentVariations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contentId;
    protected $variationCount;
    protected $companyId;

    public function __construct($contentId, $variationCount = 5, $companyId = null)
    {
        $this->contentId = $contentId;
        $this->variationCount = $variationCount;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        try {
            $originalContent = MarketingContentLibrary::findOrFail($this->contentId);
            
            Log::info("Generating content variations", [
                'content_id' => $this->contentId,
                'variation_count' => $this->variationCount,
                'company_id' => $this->companyId
            ]);

            $aiService = app(AIContentGenerator::class);

            for ($i = 0; $i < $this->variationCount; $i++) {
                $this->generateVariation($originalContent, $aiService, $i + 1);
            }

            Log::info("Content variations generated successfully", [
                'content_id' => $this->contentId,
                'variations_created' => $this->variationCount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to generate content variations", [
                'content_id' => $this->contentId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function generateVariation($originalContent, $aiService, $variationNumber)
    {
        $prompt = $this->buildVariationPrompt($originalContent, $variationNumber);
        
        $variation = $aiService->generateContent($prompt, [
            'tone' => $this->getRandomTone(),
            'style' => $this->getRandomStyle(),
            'length' => $this->getRandomLength()
        ]);

        // Create new content record for the variation
        MarketingContentLibrary::create([
            'name' => "Variation {$variationNumber} - {$originalContent->name}",
            'content_type' => $originalContent->content_type,
            'content' => $variation['content'],
            'metadata' => [
                'original_content_id' => $originalContent->id,
                'variation_number' => $variationNumber,
                'generation_prompt' => $prompt,
                'ai_model' => $variation['model'] ?? 'gpt-4',
                'tone' => $variation['tone'] ?? 'professional',
                'style' => $variation['style'] ?? 'informative'
            ],
            'company_id' => $this->companyId,
            'status' => 'draft',
            'performance_metrics' => [
                'views' => 0,
                'clicks' => 0,
                'conversions' => 0,
                'engagement_rate' => 0
            ]
        ]);
    }

    protected function buildVariationPrompt($originalContent, $variationNumber)
    {
        $basePrompt = "Create a marketing content variation for the following original content:\n\n";
        $basePrompt .= "Original Content: {$originalContent->content}\n\n";
        $basePrompt .= "Content Type: {$originalContent->content_type}\n";
        $basePrompt .= "Target Audience: " . ($originalContent->target_audience ?? 'General audience') . "\n\n";
        
        $basePrompt .= "Requirements for Variation {$variationNumber}:\n";
        $basePrompt .= "- Maintain the core message and value proposition\n";
        $basePrompt .= "- Use a different tone and style\n";
        $basePrompt .= "- Optimize for engagement and conversion\n";
        $basePrompt .= "- Include relevant call-to-action\n";
        
        return $basePrompt;
    }

    protected function getRandomTone()
    {
        $tones = ['professional', 'casual', 'friendly', 'authoritative', 'conversational', 'enthusiastic'];
        return $tones[array_rand($tones)];
    }

    protected function getRandomStyle()
    {
        $styles = ['informative', 'storytelling', 'question-based', 'benefit-focused', 'problem-solution', 'testimonial-style'];
        return $styles[array_rand($styles)];
    }

    protected function getRandomLength()
    {
        $lengths = ['short', 'medium', 'long'];
        return $lengths[array_rand($lengths)];
    }
}
