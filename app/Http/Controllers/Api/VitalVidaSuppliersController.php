<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VitalVidaSupplier;
use App\Models\VitalVidaSupplierPerformance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VitalVidaSuppliersController extends Controller
{
    public function overview(): JsonResponse
    {
        $totalSuppliers = VitalVidaSupplier::count();
        $activeSuppliers = VitalVidaSupplier::active()->count();
        $totalPurchaseValue = VitalVidaSupplier::sum('total_purchase_value');
        $averageRating = VitalVidaSupplier::avg('rating');
        $topSupplier = VitalVidaSupplier::orderBy('rating', 'desc')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_suppliers' => $totalSuppliers,
                'active_suppliers' => $activeSuppliers,
                'total_purchase_value' => $totalPurchaseValue,
                'average_rating' => round($averageRating, 1),
                'top_supplier' => $topSupplier?->company_name,
                'growth_this_month' => '+12%'
            ]
        ]);
    }

    public function index(): JsonResponse
    {
        $suppliers = VitalVidaSupplier::with(['performance' => function($query) {
            $query->latest()->take(5);
        }])
        ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => [
                'suppliers' => $suppliers->items(),
                'pagination' => [
                    'current_page' => $suppliers->currentPage(),
                    'total_pages' => $suppliers->lastPage(),
                    'total_items' => $suppliers->total()
                ]
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:vitalvida_suppliers,email',
            'business_address' => 'required|string',
            'website' => 'nullable|url',
            'products_supplied' => 'required|array',
            'payment_terms' => 'required|string',
            'delivery_time' => 'required|string'
        ]);

        // Generate supplier code
        $supplierCount = VitalVidaSupplier::count() + 1;
        $validated['supplier_code'] = 'SUP' . str_pad($supplierCount, 3, '0', STR_PAD_LEFT);

        $supplier = VitalVidaSupplier::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully',
            'data' => ['supplier' => $supplier]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $supplier = VitalVidaSupplier::with(['performance', 'products'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => ['supplier' => $supplier]
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $supplier = VitalVidaSupplier::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'string|max:255',
            'contact_person' => 'string|max:255',
            'phone' => 'string|max:20',
            'email' => 'email|unique:vitalvida_suppliers,email,' . $id,
            'business_address' => 'string',
            'website' => 'nullable|url',
            'products_supplied' => 'array',
            'payment_terms' => 'string',
            'delivery_time' => 'string',
            'status' => 'in:Active,Inactive,Pending,Suspended'
        ]);

        $supplier->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier updated successfully',
            'data' => ['supplier' => $supplier]
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $supplier = VitalVidaSupplier::findOrFail($id);
        $supplier->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier deleted successfully'
        ]);
    }

    public function performance($id): JsonResponse
    {
        $supplier = VitalVidaSupplier::findOrFail($id);
        $performance = VitalVidaSupplierPerformance::where('supplier_id', $id)
            ->orderBy('performance_date', 'desc')
            ->take(12)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'supplier' => $supplier->company_name,
                'performance' => $performance,
                'overall_rating' => $supplier->rating,
                'total_orders' => $supplier->total_orders
            ]
        ]);
    }

    public function rate(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'delivery_rating' => 'required|numeric|min:0|max:5',
            'quality_rating' => 'required|numeric|min:0|max:5',
            'service_rating' => 'required|numeric|min:0|max:5',
            'notes' => 'nullable|string'
        ]);

        $supplier = VitalVidaSupplier::findOrFail($id);

        // Create performance record
        VitalVidaSupplierPerformance::create([
            'supplier_id' => $id,
            'performance_date' => now()->toDateString(),
            'delivery_rating' => $validated['delivery_rating'],
            'quality_rating' => $validated['quality_rating'],
            'service_rating' => $validated['service_rating'],
            'notes' => $validated['notes'] ?? null
        ]);

        // Update overall supplier rating
        $overallRating = ($validated['delivery_rating'] + $validated['quality_rating'] + $validated['service_rating']) / 3;
        $supplier->update(['rating' => $overallRating]);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier rated successfully',
            'data' => ['new_rating' => round($overallRating, 2)]
        ]);
    }

    public function analytics(): JsonResponse
    {
        // Performance trends over time
        $performanceTrends = VitalVidaSupplierPerformance::selectRaw('
            DATE_FORMAT(performance_date, "%Y-%m") as month,
            AVG(delivery_rating) as avg_delivery,
            AVG(quality_rating) as avg_quality,
            AVG(service_rating) as avg_service,
            COUNT(*) as total_reviews
        ')
        ->groupBy('month')
        ->orderBy('month', 'desc')
        ->take(12)
        ->get();

        // Top performing suppliers
        $topSuppliers = VitalVidaSupplier::orderBy('rating', 'desc')
            ->take(5)
            ->get(['id', 'company_name', 'rating', 'total_orders']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'performance_trends' => $performanceTrends,
                'top_suppliers' => $topSuppliers,
                'overall_metrics' => [
                    'total_suppliers' => VitalVidaSupplier::count(),
                    'average_rating' => VitalVidaSupplier::avg('rating'),
                    'total_orders' => VitalVidaSupplier::sum('total_orders'),
                    'total_value' => VitalVidaSupplier::sum('total_purchase_value')
                ]
            ]
        ]);
    }
}
