<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingContentLibrary;
use App\Models\Marketing\MarketingBrand;
use App\Services\Marketing\MarketingAIContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MarketingContentController extends Controller
{
    protected $aiService;
    
    public function __construct(MarketingAIContentService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    public function generateContent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content_type' => 'required|string|in:ad_copy,social_post,email,landing_page,product_description',
            'tone' => 'required|string|in:professional,casual,friendly,urgent,emotional',
            'target_audience' => 'required|string',
            'product_details' => 'required|string',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'platform' => 'nullable|string|in:facebook,instagram,tiktok,google,email,general',
            'word_count' => 'nullable|integer|min:10|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Generate content using AI service
            $generatedContent = $this->aiService->generateContent(
                $request->content_type,
                $request->target_audience,
                $request->tone,
                $request->product_details,
                auth()->user()->company_id
            );
            
            // Save to marketing content library
            $content = MarketingContentLibrary::create([
                'brand_id' => $request->brand_id,
                'content_type' => 'ai_generated',
                'title' => 'AI Generated - ' . ucfirst($request->content_type),
                'variations' => $generatedContent,
                'generation_prompt' => $request->all(),
                'performance_score' => 0,
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Content generated successfully',
                'data' => $content->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate content: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function index(Request $request)
    {
        $query = MarketingContentLibrary::with(['brand', 'creator'])
            ->where('company_id', auth()->user()->company_id);
            
        // Apply filters
        if ($request->has('content_type')) {
            $query->where('content_type', $request->content_type);
        }
        
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        
        if ($request->has('ai_generated')) {
            if ($request->ai_generated) {
                $query->where('content_type', 'ai_generated');
            } else {
                $query->where('content_type', '!=', 'ai_generated');
            }
        }
        
        $content = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }
    
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content_type' => 'required|string|in:image,video,text,audio',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'file' => 'required_if:content_type,image,video,audio|file|max:10240', // 10MB max
            'content' => 'required_if:content_type,text|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $fileUrl = null;
            
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('marketing/content', $fileName, 'public');
                $fileUrl = $filePath;
            }
            
            $content = MarketingContentLibrary::create([
                'brand_id' => $request->brand_id,
                'content_type' => $request->content_type,
                'title' => $request->title,
                'file_url' => $fileUrl,
                'sensory_tags' => $request->tags ?? [],
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Content uploaded successfully',
                'data' => $content->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload content: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getVariations($id)
    {
        $content = MarketingContentLibrary::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$content) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'content' => $content,
                'variations' => $content->variations ?? []
            ]
        ]);
    }
    
    public function updatePerformance($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'performance_score' => 'required|numeric|between:0,1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $content = MarketingContentLibrary::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$content) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found'
            ], 404);
        }
        
        $content->update([
            'performance_score' => $request->performance_score
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Performance score updated',
            'data' => $content
        ]);
    }
}
