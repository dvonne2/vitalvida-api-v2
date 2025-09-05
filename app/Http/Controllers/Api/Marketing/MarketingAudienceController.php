<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingAudience;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarketingAudienceController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketingAudience::with(['creator'])
            ->where('company_id', auth()->user()->company_id);
            
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        $audiences = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        // Add customer count for each audience
        $audiences->getCollection()->transform(function ($audience) {
            $audience->customer_count = $this->getAudienceCustomerCount($audience);
            return $audience;
        });
            
        return response()->json([
            'success' => true,
            'data' => $audiences
        ]);
    }
    
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'criteria' => 'required|array',
            'criteria.*.field' => 'required|string',
            'criteria.*.operator' => 'required|in:equals,not_equals,contains,not_contains,greater_than,less_than,between,in,not_in',
            'criteria.*.value' => 'required',
            'logic_operator' => 'nullable|in:and,or',
            'status' => 'nullable|in:active,inactive'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $audience = MarketingAudience::create([
                'name' => $request->name,
                'description' => $request->description,
                'criteria' => $request->criteria,
                'logic_operator' => $request->logic_operator ?? 'and',
                'status' => $request->status ?? 'active',
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            // Calculate initial customer count
            $customerCount = $this->getAudienceCustomerCount($audience);
            $audience->update(['customer_count' => $customerCount]);
            
            return response()->json([
                'success' => true,
                'message' => 'Audience created successfully',
                'data' => $audience->load(['creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create audience: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $audience = MarketingAudience::with(['creator'])
            ->where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$audience) {
            return response()->json([
                'success' => false,
                'message' => 'Audience not found'
            ], 404);
        }
        
        // Add detailed metrics
        $audience->customer_count = $this->getAudienceCustomerCount($audience);
        $audience->demographics = $this->getAudienceDemographics($audience);
        $audience->engagement_stats = $this->getAudienceEngagementStats($audience);
        
        return response()->json([
            'success' => true,
            'data' => $audience
        ]);
    }
    
    public function getCustomers($id, Request $request)
    {
        $audience = MarketingAudience::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$audience) {
            return response()->json([
                'success' => false,
                'message' => 'Audience not found'
            ], 404);
        }
        
        // Build customer query based on audience criteria
        $customerQuery = Customer::where('company_id', auth()->user()->company_id);
        $customerQuery = $this->applyAudienceCriteria($customerQuery, $audience->criteria, $audience->logic_operator);
        
        // Apply additional filters
        if ($request->has('search')) {
            $customerQuery->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }
        
        $customers = $customerQuery->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));
            
        return response()->json([
            'success' => true,
            'data' => [
                'audience' => $audience,
                'customers' => $customers
            ]
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $audience = MarketingAudience::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$audience) {
            return response()->json([
                'success' => false,
                'message' => 'Audience not found'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'criteria' => 'sometimes|required|array',
            'criteria.*.field' => 'required_with:criteria|string',
            'criteria.*.operator' => 'required_with:criteria|in:equals,not_equals,contains,not_contains,greater_than,less_than,between,in,not_in',
            'criteria.*.value' => 'required_with:criteria',
            'logic_operator' => 'nullable|in:and,or',
            'status' => 'nullable|in:active,inactive'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $audience->update($request->only([
                'name', 'description', 'criteria', 'logic_operator', 'status'
            ]));
            
            // Recalculate customer count if criteria changed
            if ($request->has('criteria')) {
                $customerCount = $this->getAudienceCustomerCount($audience);
                $audience->update(['customer_count' => $customerCount]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Audience updated successfully',
                'data' => $audience->load(['creator'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update audience: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        $audience = MarketingAudience::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();
            
        if (!$audience) {
            return response()->json([
                'success' => false,
                'message' => 'Audience not found'
            ], 404);
        }
        
        try {
            $audience->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Audience deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete audience: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function getAudienceCustomerCount($audience)
    {
        $customerQuery = Customer::where('company_id', $audience->company_id);
        $customerQuery = $this->applyAudienceCriteria($customerQuery, $audience->criteria, $audience->logic_operator);
        
        return $customerQuery->count();
    }
    
    private function applyAudienceCriteria($query, $criteria, $logicOperator = 'and')
    {
        if (empty($criteria)) {
            return $query;
        }
        
        $method = $logicOperator === 'or' ? 'orWhere' : 'where';
        
        foreach ($criteria as $criterion) {
            $field = $criterion['field'];
            $operator = $criterion['operator'];
            $value = $criterion['value'];
            
            switch ($operator) {
                case 'equals':
                    $query->$method($field, '=', $value);
                    break;
                case 'not_equals':
                    $query->$method($field, '!=', $value);
                    break;
                case 'contains':
                    $query->$method($field, 'like', '%' . $value . '%');
                    break;
                case 'not_contains':
                    $query->$method($field, 'not like', '%' . $value . '%');
                    break;
                case 'greater_than':
                    $query->$method($field, '>', $value);
                    break;
                case 'less_than':
                    $query->$method($field, '<', $value);
                    break;
                case 'between':
                    if (is_array($value) && count($value) === 2) {
                        $query->$method($field, '>=', $value[0])
                              ->$method($field, '<=', $value[1]);
                    }
                    break;
                case 'in':
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    }
                    break;
                case 'not_in':
                    if (is_array($value)) {
                        $query->whereNotIn($field, $value);
                    }
                    break;
            }
        }
        
        return $query;
    }
    
    private function getAudienceDemographics($audience)
    {
        $customerQuery = Customer::where('company_id', $audience->company_id);
        $customerQuery = $this->applyAudienceCriteria($customerQuery, $audience->criteria, $audience->logic_operator);
        
        // Get basic demographics
        $demographics = [
            'total_customers' => $customerQuery->count(),
            'gender_distribution' => $customerQuery->select('gender', DB::raw('count(*) as count'))
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray(),
            'age_groups' => $customerQuery->select(
                DB::raw('CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 25 THEN "18-24"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 35 THEN "25-34"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 45 THEN "35-44"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 55 THEN "45-54"
                    ELSE "55+" 
                END as age_group'),
                DB::raw('count(*) as count')
            )
            ->whereNotNull('date_of_birth')
            ->groupBy('age_group')
            ->pluck('count', 'age_group')
            ->toArray()
        ];
        
        return $demographics;
    }
    
    private function getAudienceEngagementStats($audience)
    {
        $customerQuery = Customer::where('company_id', $audience->company_id);
        $customerQuery = $this->applyAudienceCriteria($customerQuery, $audience->criteria, $audience->logic_operator);
        
        $customerIds = $customerQuery->pluck('id');
        
        // Get engagement statistics
        $totalPurchases = Sale::whereIn('customer_id', $customerIds)->count();
        $totalRevenue = Sale::whereIn('customer_id', $customerIds)->sum('total_amount');
        $avgOrderValue = $totalPurchases > 0 ? $totalRevenue / $totalPurchases : 0;
        
        return [
            'total_purchases' => $totalPurchases,
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $avgOrderValue,
            'customers_with_purchases' => Sale::whereIn('customer_id', $customerIds)
                ->distinct('customer_id')
                ->count()
        ];
    }
}
