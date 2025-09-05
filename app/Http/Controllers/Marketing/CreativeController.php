<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\CreativeAsset;
use App\Models\Marketing\Campaign;
use App\Services\AI\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreativeController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Generate AI-powered marketing copy
     */
    public function generateCopy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|max:1000',
            'type' => 'required|in:ad_copy,social_post,email,landing_page,product_description',
            'tone' => 'required|in:professional,casual,friendly,urgent,emotional',
            'platform' => 'required|in:facebook,instagram,tiktok,google,email,general',
            'target_audience' => 'nullable|string|max:500',
            'product_details' => 'nullable|string|max:1000',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'word_count' => 'nullable|integer|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $prompt = $this->buildCopyPrompt($request->all());
            
            $response = $this->openAIService->generateContent($prompt, [
                'max_tokens' => $request->word_count ? $request->word_count * 2 : 300,
                'temperature' => 0.7,
            ]);

            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate content',
                    'error' => $response['error'] ?? 'Unknown error'
                ], 500);
            }

            // Save the generated content as a creative asset
            $creativeAsset = CreativeAsset::create([
                'name' => 'AI Generated ' . ucfirst($request->type) . ' - ' . now()->format('Y-m-d H:i'),
                'type' => 'copy',
                'content' => $response['content'],
                'platform' => $request->platform,
                'campaign_id' => $request->campaign_id,
                'created_by' => auth()->id(),
                'ai_generated' => true,
                'generation_prompt' => $request->prompt,
                'tags' => [$request->type, $request->tone, $request->platform, 'ai-generated'],
                'status' => 'draft',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Content generated successfully',
                'data' => [
                    'content' => $response['content'],
                    'creative_asset_id' => $creativeAsset->id,
                    'usage_cost' => $response['usage_cost'] ?? 0,
                    'tokens_used' => $response['tokens_used'] ?? 0,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative assets with filtering
     */
    public function getAssets(Request $request): JsonResponse
    {
        $query = CreativeAsset::with(['campaign', 'creator', 'approver']);

        // Apply filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('platform')) {
            $query->byPlatform($request->platform);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->has('ai_generated')) {
            $query->aiGenerated();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }

        $assets = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $assets
        ]);
    }

    /**
     * Store a new creative asset
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:image,video,copy,audio',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // 10MB max
            'platform' => 'required|in:facebook,instagram,tiktok,google,general',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only(['name', 'type', 'content', 'platform', 'campaign_id', 'tags']);
            $data['created_by'] = auth()->id();
            $data['status'] = 'draft';

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('creative-assets', $fileName, 'public');
                
                $data['file_path'] = $filePath;
                $data['file_size'] = $file->getSize();
                $data['mime_type'] = $file->getMimeType();
                
                // Get dimensions for images/videos
                if (in_array($data['type'], ['image', 'video'])) {
                    $dimensions = $this->getFileDimensions($file);
                    $data['dimensions'] = $dimensions;
                }
            }

            $creativeAsset = CreativeAsset::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Creative asset created successfully',
                'data' => $creativeAsset->load(['campaign', 'creator'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating creative asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update creative asset status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,review,approved,published',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $asset = CreativeAsset::findOrFail($id);
            
            $asset->update([
                'status' => $request->status,
                'approved_by' => $request->status === 'approved' ? auth()->id() : null,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asset status updated successfully',
                'data' => $asset->load(['campaign', 'creator', 'approver'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating asset status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative asset analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $query = CreativeAsset::query();

        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        $analytics = [
            'total_assets' => $query->count(),
            'by_type' => $query->selectRaw('type, count(*) as count')->groupBy('type')->get(),
            'by_status' => $query->selectRaw('status, count(*) as count')->groupBy('status')->get(),
            'by_platform' => $query->selectRaw('platform, count(*) as count')->groupBy('platform')->get(),
            'ai_generated' => $query->aiGenerated()->count(),
            'recent_assets' => $query->orderBy('created_at', 'desc')->limit(5)->get(),
            'top_performing' => $query->whereNotNull('performance_metrics')
                                    ->orderByRaw("JSON_EXTRACT(performance_metrics, '$.engagement_rate') DESC")
                                    ->limit(5)
                                    ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Build copy generation prompt
     */
    private function buildCopyPrompt(array $data): string
    {
        $prompt = "Generate marketing copy for a Nigerian hair care brand (Vitalvida).\n\n";
        $prompt .= "Type: {$data['type']}\n";
        $prompt .= "Tone: {$data['tone']}\n";
        $prompt .= "Platform: {$data['platform']}\n";
        
        if (!empty($data['target_audience'])) {
            $prompt .= "Target Audience: {$data['target_audience']}\n";
        }
        
        if (!empty($data['product_details'])) {
            $prompt .= "Product Details: {$data['product_details']}\n";
        }
        
        $prompt .= "\nUser Request: {$data['prompt']}\n\n";
        $prompt .= "Please create compelling, culturally relevant marketing copy that resonates with Nigerian women. ";
        $prompt .= "Focus on natural hair care, beauty, and confidence. Include relevant hashtags if appropriate for the platform.";
        
        if ($data['word_count']) {
            $prompt .= "\n\nWord count target: {$data['word_count']} words.";
        }

        return $prompt;
    }

    /**
     * Get file dimensions for images/videos
     */
    private function getFileDimensions($file): array
    {
        try {
            $path = $file->getPathname();
            
            if ($file->getMimeType() && strpos($file->getMimeType(), 'image/') === 0) {
                $imageInfo = getimagesize($path);
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ];
            }
            
            // For videos, you might need to use FFmpeg or similar
            // For now, return placeholder
            return ['width' => 0, 'height' => 0];
            
        } catch (\Exception $e) {
            return ['width' => 0, 'height' => 0];
        }
    }
}
