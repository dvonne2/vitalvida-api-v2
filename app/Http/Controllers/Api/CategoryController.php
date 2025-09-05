<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('items');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->paginate($request->get('per_page', 15));

        return ApiResponse::paginate($categories, 'Categories retrieved successfully');
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        try {
            $category = Category::create($request->all());

            return ApiResponse::created($category, 'Category created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success($category->load('items'), 'Category retrieved successfully');
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        try {
            $category->update($request->all());

            return ApiResponse::success($category, 'Category updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            $category->delete();

            return ApiResponse::success(null, 'Category deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
} 