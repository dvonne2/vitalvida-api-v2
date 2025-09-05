<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingAutomationSequence;
use App\Models\Marketing\MarketingBrand;
use App\Models\Customer;
use App\Jobs\Marketing\ProcessAutomationSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MarketingAutomationController extends Controller
{
    public function createSequence(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'trigger_type' => 'required|in:customer_signup,purchase,abandoned_cart,birthday,custom_date',
            'trigger_conditions' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.type' => 'required|in:email,whatsapp,sms,delay',
            'steps.*.delay_hours' => 'required_if:steps.*.type,delay|integer|min:1',
            'steps.*.content_id' => 'required_unless:steps.*.type,delay|uuid',
            'steps.*.channels' => 'required_unless:steps.*.type,delay|array',
            'target_audience' => 'nullable|array',
            'status' => 'nullable|in:draft,active,paused'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $sequence = MarketingAutomationSequence::create([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'trigger_type' => $request->trigger_type,
                'trigger_conditions' => $request->trigger_conditions,
                'steps' => $request->steps,
                'target_audience' => $request->target_audience,
                'status' => $request->status ?? 'draft',
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Automation sequence created successfully',
                'data' => $sequence->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create automation sequence: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getSequences(Request $request)
    {
        $query = MarketingAutomationSequence::with(['brand', 'creator'])
            ->where('company_id', auth()->user()->company_id);
            
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->has('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        $sequences = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        // Add performance metrics for each sequence
        $sequences->getCollection()->transform(function ($sequence) {
            $sequence->performance = [
                'total_triggered' => $sequence->executions()->count(),
                'completed' => $sequence->executions()->where('status', 'completed')->count(),
                'active' => $sequence->executions()->where('status', 'active')->count(),
                'failed' => $sequence->executions()->where('status', 'failed')->count()
            ];
            return $sequence;
        });
            
        return response()->json([
            'success' => true,
            'data' => $sequences
        ]);
    }
    
    public function activate($id)
    {
        $sequence = MarketingAutomationSequence::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Automation sequence not found'
            ], 404);
        }
        
        if ($sequence->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Sequence is already active'
            ], 400);
        }
        
        // Validate sequence configuration
        if (empty($sequence->steps)) {
            return response()->json([
                'success' => false,
                'message' => 'Sequence must have at least one step'
            ], 400);
        }
        
        try {
            $sequence->update([
                'status' => 'active',
                'activated_at' => Carbon::now()
            ]);
            
            // If this is a trigger-based sequence, check for existing customers that match
            if (in_array($sequence->trigger_type, ['customer_signup', 'purchase'])) {
                $this->processExistingCustomers($sequence);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Automation sequence activated successfully',
                'data' => $sequence->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate sequence: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $sequence = MarketingAutomationSequence::with(['brand', 'creator', 'executions'])
            ->where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Automation sequence not found'
            ], 404);
        }
        
        // Add detailed performance metrics
        $sequence->detailed_performance = [
            'total_executions' => $sequence->executions->count(),
            'completed_executions' => $sequence->executions->where('status', 'completed')->count(),
            'active_executions' => $sequence->executions->where('status', 'active')->count(),
            'failed_executions' => $sequence->executions->where('status', 'failed')->count(),
            'avg_completion_time' => $sequence->executions()
                ->where('status', 'completed')
                ->avg(\DB::raw('TIMESTAMPDIFF(HOUR, started_at, completed_at)')),
            'step_performance' => $this->getStepPerformance($sequence)
        ];
        
        return response()->json([
            'success' => true,
            'data' => $sequence
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $sequence = MarketingAutomationSequence::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Automation sequence not found'
            ], 404);
        }
        
        // Don't allow editing active sequences
        if ($sequence->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit active sequence. Please pause it first.'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'trigger_type' => 'sometimes|required|in:customer_signup,purchase,abandoned_cart,birthday,custom_date',
            'trigger_conditions' => 'nullable|array',
            'steps' => 'sometimes|required|array|min:1',
            'steps.*.type' => 'required_with:steps|in:email,whatsapp,sms,delay',
            'target_audience' => 'nullable|array',
            'status' => 'nullable|in:draft,active,paused'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $sequence->update($request->only([
                'name', 'trigger_type', 'trigger_conditions', 'steps', 
                'target_audience', 'status'
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'Automation sequence updated successfully',
                'data' => $sequence->load(['brand', 'creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sequence: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function pause($id)
    {
        $sequence = MarketingAutomationSequence::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Automation sequence not found'
            ], 404);
        }
        
        try {
            $sequence->update(['status' => 'paused']);
            
            return response()->json([
                'success' => true,
                'message' => 'Automation sequence paused successfully',
                'data' => $sequence
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to pause sequence: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function processExistingCustomers($sequence)
    {
        // Get customers that match the trigger conditions
        $customers = Customer::where('company_id', $sequence->company_id);
        
        // Apply target audience filters if specified
        if ($sequence->target_audience) {
            foreach ($sequence->target_audience as $condition) {
                // Apply audience filtering logic based on conditions
                // This would be customized based on your specific audience criteria
            }
        }
        
        $customers = $customers->get();
        
        // Dispatch automation jobs for matching customers
        foreach ($customers as $customer) {
            ProcessAutomationSequence::dispatch($sequence, $customer);
        }
    }
    
    private function getStepPerformance($sequence)
    {
        // This would analyze performance of each step in the sequence
        // Return step-by-step conversion rates, completion rates, etc.
        return [];
    }
}
