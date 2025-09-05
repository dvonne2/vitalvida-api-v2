<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
        
        // Only log for authenticated users and sensitive operations
        if ($request->user() && $this->shouldLog($request)) {
            $this->logAuditEvent($request, $response, $duration);
        }
        
        return $response;
    }
    
    /**
     * Determine if the request should be logged
     */
    private function shouldLog(Request $request): bool
    {
        $sensitivePaths = [
            '/api/auth/login',
            '/api/auth/logout',
            '/api/auth/change-password',
            '/api/auth/update-profile',
            '/api/admin/',
            '/api/inventory/',
            '/api/delivery/',
        ];
        
        foreach ($sensitivePaths as $path) {
            if (str_starts_with($request->path(), trim($path, '/'))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log the audit event
     */
    private function logAuditEvent(Request $request, Response $response, float $duration): void
    {
        $user = $request->user();
        $statusCode = $response->getStatusCode();
        
        $logData = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'timestamp' => now()->toISOString(),
            'request_id' => uniqid(),
        ];
        
        // Add request data for sensitive operations (be careful with sensitive data)
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')) {
            $logData['request_data'] = $this->sanitizeRequestData($request->all());
        }
        
        // Log based on status code
        if ($statusCode >= 400) {
            Log::warning('API Security Event', $logData);
        } else {
            Log::info('API Audit Log', $logData);
        }
    }
    
    /**
     * Sanitize request data to remove sensitive information
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'current_password', 'new_password', 'token'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }
        
        return $data;
    }
} 