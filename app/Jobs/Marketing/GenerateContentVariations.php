<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingContentLibrary;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class GenerateContentVariations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contentId;
    protected $variationsCount;
    protected $companyId;

    public function __construct($contentId, $variationsCount = 3, $companyId = null)
    {
        $this->contentId = $contentId;
        $this->variationsCount = $variationsCount;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        try {
            $originalContent = MarketingContentLibrary::find($this->contentId);
            
            if (!$originalContent) {
                throw new \Exception("Content not found: {$this->contentId}");
            }

            Log::info("Generating content variations", [
                'content_id' => $this->contentId,
                'variations_count' => $this->variationsCount,
                'company_id' => $this->companyId
            ]);

            $openAIService = app(OpenAIService::class);

            // Generate variations based on original content
            for ($i = 1; $i <= $this->variationsCount; $i++) {
                $prompt = $this->buildVariationPrompt($originalContent, $i);
                
                $variation = $openAIService->generateText($prompt, [
                    'max_tokens' => 500,
                    'temperature' => 0.8,
                    'context' => 'Nigerian market marketing content'
                ]);

                // Create variation in content library
                MarketingContentLibrary::create([
                    'brand_id' => $originalContent->brand_id,
                    'content_type' => $originalContent->content_type,
                    'platform' => $originalContent->platform,
                    'content' => $variation,
                    'tone' => $originalContent->tone,
                    'target_audience' => $originalContent->target_audience,
                    'status' => 'draft',
                    'parent_content_id' => $originalContent->id,
                    'variation_number' => $i,
                    'company_id' => $originalContent->company_id,
                    'created_by' => $originalContent->created_by
                ]);

                Log::info("Content variation generated", [
                    'original_content_id' => $this->contentId,
                    'variation_number' => $i
                ]);
            }

            // Update original content to mark variations as generated
            $originalContent->update([
                'variations_generated' => true,
                'variations_count' => $this->variationsCount
            ]);

            Log::info("Content variations generation completed", [
                'content_id' => $this->contentId,
                'total_variations' => $this->variationsCount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to generate content variations", [
                'content_id' => $this->contentId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function buildVariationPrompt($originalContent, $variationNumber)
    {
        $prompt = "Create a variation of the following marketing content for the Nigerian market:\n\n";
        $prompt .= "Original Content: {$originalContent->content}\n\n";
        $prompt .= "Content Type: {$originalContent->content_type}\n";
        $prompt .= "Platform: {$originalContent->platform}\n";
        $prompt .= "Tone: {$originalContent->tone}\n";
        $prompt .= "Target Audience: {$originalContent->target_audience}\n\n";
        
        $prompt .= "Requirements for Variation #{$variationNumber}:\n";
        $prompt .= "- Keep the same core message and call-to-action\n";
        $prompt .= "- Use Nigerian cultural context and local expressions where appropriate\n";
        $prompt .= "- Maintain the same tone and target audience\n";
        $prompt .= "- Make it unique and engaging while preserving the original intent\n";
        $prompt .= "- Use Nigerian Pidgin English or local slang if it fits the brand voice\n";
        $prompt .= "- Reference Nigerian culture, values, or experiences when relevant\n\n";
        
        $prompt .= "Generate only the content variation, no explanations:";

        return $prompt;
    }
}
