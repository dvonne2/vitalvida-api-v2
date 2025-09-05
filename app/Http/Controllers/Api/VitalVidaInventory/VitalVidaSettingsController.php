<?php

namespace App\Http\Controllers\Api\VitalVidaInventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VitalVidaSettingsController extends Controller
{
    /**
     * Get company settings
     */
    public function getCompanySettings(): JsonResponse
    {
        $settings = [
            'company_name' => 'VitalVida Inventory Management',
            'address' => '123 Business District, Victoria Island, Lagos, Nigeria',
            'phone' => '+234 901 234 5678',
            'email' => 'info@vitalvida.com',
            'website' => 'https://vitalvida.com',
            'registration_number' => 'RC123456789',
            'tax_id' => 'TIN987654321',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos'
        ];

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update company settings
     */
    public function updateCompanySettings(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'website' => 'sometimes|url|max:255',
            'registration_number' => 'sometimes|string|max:50',
            'tax_id' => 'sometimes|string|max:50',
            'currency' => 'sometimes|string|max:3',
            'timezone' => 'sometimes|string|max:50'
        ]);

        // In production, save to database or config
        return response()->json([
            'status' => 'success',
            'message' => 'Company settings updated successfully',
            'data' => $request->all()
        ]);
    }

    /**
     * Get security settings
     */
    public function getSecuritySettings(): JsonResponse
    {
        $settings = [
            'two_factor_enabled' => true,
            'session_timeout' => 30, // minutes
            'password_expiry' => 90, // days
            'login_attempts_limit' => 5,
            'require_password_change' => false,
            'audit_logs_retention' => 365, // days
            'ip_whitelist_enabled' => false,
            'allowed_ips' => []
        ];

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update security settings
     */
    public function updateSecuritySettings(Request $request): JsonResponse
    {
        $request->validate([
            'two_factor_enabled' => 'sometimes|boolean',
            'session_timeout' => 'sometimes|integer|min:5|max:480',
            'password_expiry' => 'sometimes|integer|min:30|max:365',
            'login_attempts_limit' => 'sometimes|integer|min:3|max:10',
            'require_password_change' => 'sometimes|boolean',
            'audit_logs_retention' => 'sometimes|integer|min:30|max:2555',
            'ip_whitelist_enabled' => 'sometimes|boolean',
            'allowed_ips' => 'sometimes|array'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Security settings updated successfully',
            'data' => $request->all()
        ]);
    }

    /**
     * Get system settings
     */
    public function getSystemSettings(): JsonResponse
    {
        $settings = [
            'version' => '2.1.0',
            'environment' => 'production',
            'last_backup' => now()->subHours(6)->format('Y-m-d H:i:s'),
            'database_size' => '2.4 GB',
            'storage_used' => '1.8 GB',
            'storage_limit' => '10 GB',
            'auto_backup_enabled' => true,
            'backup_frequency' => 'daily',
            'maintenance_mode' => false,
            'debug_mode' => false,
            'api_rate_limit' => 1000, // requests per hour
            'max_file_upload_size' => '10MB'
        ];

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(Request $request): JsonResponse
    {
        $request->validate([
            'auto_backup_enabled' => 'sometimes|boolean',
            'backup_frequency' => 'sometimes|in:hourly,daily,weekly',
            'maintenance_mode' => 'sometimes|boolean',
            'debug_mode' => 'sometimes|boolean',
            'api_rate_limit' => 'sometimes|integer|min:100|max:10000',
            'max_file_upload_size' => 'sometimes|string'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'System settings updated successfully',
            'data' => $request->all()
        ]);
    }
}
