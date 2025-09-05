<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Success response.
     */
    public static function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $code);
    }

    /**
     * Error response.
     */
    public static function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Not found response.
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Unauthorized response.
     */
    public static function unauthorized(string $message = 'Unauthorized access'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden access'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Validation error response.
     */
    public static function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ], 422);
    }

    /**
     * Paginated response.
     */
    public static function paginate(LengthAwarePaginator $collection, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection->items(),
            'pagination' => [
                'current_page' => $collection->currentPage(),
                'last_page' => $collection->lastPage(),
                'per_page' => $collection->perPage(),
                'total' => $collection->total(),
                'from' => $collection->firstItem(),
                'to' => $collection->lastItem(),
                'has_more_pages' => $collection->hasMorePages()
            ],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Created response.
     */
    public static function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * No content response.
     */
    public static function noContent(string $message = 'No content'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 204);
    }

    /**
     * Conflict response.
     */
    public static function conflict(string $message = 'Resource conflict'): JsonResponse
    {
        return self::error($message, 409);
    }

    /**
     * Too many requests response.
     */
    public static function tooManyRequests(string $message = 'Too many requests'): JsonResponse
    {
        return self::error($message, 429);
    }

    /**
     * Server error response.
     */
    public static function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return self::error($message, 500);
    }

    /**
     * Service unavailable response.
     */
    public static function serviceUnavailable(string $message = 'Service unavailable'): JsonResponse
    {
        return self::error($message, 503);
    }

    /**
     * Custom response with additional metadata.
     */
    public static function custom($data = null, string $message = 'Success', int $code = 200, array $metadata = []): JsonResponse
    {
        $response = [
            'success' => $code >= 200 && $code < 300,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];

        if (!empty($metadata)) {
            $response['metadata'] = $metadata;
        }

        return response()->json($response, $code);
    }

    /**
     * File download response.
     */
    public static function download($file, string $filename, array $headers = []): JsonResponse
    {
        $defaultHeaders = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ];

        $headers = array_merge($defaultHeaders, $headers);

        return response()->json([
            'success' => true,
            'message' => 'File ready for download',
            'download_url' => $file,
            'filename' => $filename,
            'timestamp' => now()->toISOString()
        ])->withHeaders($headers);
    }

    /**
     * Export response.
     */
    public static function export($data, string $format, string $filename): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Export completed successfully',
            'data' => [
                'format' => $format,
                'filename' => $filename,
                'download_url' => $data,
                'expires_at' => now()->addHours(24)->toISOString()
            ],
            'timestamp' => now()->toISOString()
        ]);
    }
} 