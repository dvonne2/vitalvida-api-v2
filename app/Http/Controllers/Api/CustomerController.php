<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::withCount('sales');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return ApiResponse::paginate($customers, 'Customers retrieved successfully');
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string'
        ]);

        try {
            $customer = Customer::create($request->all());

            return ApiResponse::created($customer, 'Customer created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::success($customer->load('sales'), 'Customer retrieved successfully');
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string'
        ]);

        try {
            $customer->update($request->all());

            return ApiResponse::success($customer, 'Customer updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $customer->delete();

            return ApiResponse::success(null, 'Customer deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete customer: ' . $e->getMessage(), 500);
        }
    }
} 