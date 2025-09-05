<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\SecurityEvent;

class ActivityLogging
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log activities for authenticated users and specific routes
        if ($this->shouldLogActivity($request)) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * Determine if the activity should be logged
     */
    private function shouldLogActivity(Request $request): bool
    {
        // Skip logging for certain routes
        $excludedRoutes = [
            'api/health',
            'api/monitoring/*',
            'api/admin/activity-logs',
            'api/admin/system-logs',
            'api/admin/security-events'
        ];

        $currentPath = $request->path();

        foreach ($excludedRoutes as $excludedRoute) {
            if (str_ends_with($excludedRoute, '*')) {
                $pattern = str_replace('*', '', $excludedRoute);
                if (str_starts_with($currentPath, $pattern)) {
                    return false;
                }
            } elseif ($currentPath === $excludedRoute) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log the user activity
     */
    private function logActivity(Request $request, $response): void
    {
        try {
            $user = $request->user();
            $action = $this->determineAction($request);
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $details = $this->getActivityDetails($request, $response);

            ActivityLog::create([
                'user_id' => $user ? $user->id : null,
                'action' => $action,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'details' => $details,
                'timestamp' => now()
            ]);

            // Log security events for suspicious activities
            $this->checkSecurityEvents($request, $user, $ipAddress);

        } catch (\Exception $e) {
            // Log the error but don't break the request
            \Log::error('Activity logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine the action being performed
     */
    private function determineAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();
        $action = '';

        // Map common actions
        switch ($method) {
            case 'GET':
                if (str_contains($path, 'dashboard')) {
                    $action = 'Viewed dashboard';
                } elseif (str_contains($path, 'users')) {
                    $action = 'Viewed users';
                } elseif (str_contains($path, 'logs')) {
                    $action = 'Viewed logs';
                } else {
                    $action = 'Viewed page';
                }
                break;

            case 'POST':
                if (str_contains($path, 'users')) {
                    $action = 'Created user';
                } elseif (str_contains($path, 'login')) {
                    $action = 'User logged in';
                } elseif (str_contains($path, 'logout')) {
                    $action = 'User logged out';
                } else {
                    $action = 'Created record';
                }
                break;

            case 'PUT':
            case 'PATCH':
                if (str_contains($path, 'users')) {
                    $action = 'Updated user';
                } else {
                    $action = 'Updated record';
                }
                break;

            case 'DELETE':
                if (str_contains($path, 'users')) {
                    $action = 'Deleted user';
                } else {
                    $action = 'Deleted record';
                }
                break;

            default:
                $action = 'Performed action';
        }

        return $action;
    }

    /**
     * Get activity details
     */
    private function getActivityDetails(Request $request, $response): array
    {
        $details = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'timestamp' => now()->toISOString()
        ];

        // Add request parameters (excluding sensitive data)
        $params = $request->all();
        unset($params['password'], $params['password_confirmation'], $params['token']);
        
        if (!empty($params)) {
            $details['parameters'] = $params;
        }

        // Add response size if available
        if (method_exists($response, 'getContent')) {
            $details['response_size'] = strlen($response->getContent());
        }

        return $details;
    }

    /**
     * Check for security events
     */
    private function checkSecurityEvents(Request $request, $user, string $ipAddress): void
    {
        // Check for failed login attempts
        if ($request->path() === 'api/admin/login' && $request->method() === 'POST') {
            $response = $request->getResponse();
            if ($response && $response->getStatusCode() === 401) {
                $this->logSecurityEvent('failed_login_attempt', $user, $ipAddress, [
                    'attempted_username' => $request->input('email'),
                    'attempts_count' => $this->getFailedAttemptsCount($ipAddress)
                ]);
            }
        }

        // Check for suspicious IP addresses
        if ($this->isSuspiciousIp($ipAddress)) {
            $this->logSecurityEvent('suspicious_ip_access', $user, $ipAddress, [
                'reason' => 'IP address flagged as suspicious'
            ]);
        }

        // Check for unusual activity patterns
        if ($this->isUnusualActivity($user, $ipAddress)) {
            $this->logSecurityEvent('unusual_activity', $user, $ipAddress, [
                'reason' => 'Unusual activity pattern detected'
            ]);
        }
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(string $eventType, $user, string $ipAddress, array $details): void
    {
        try {
            SecurityEvent::create([
                'event_type' => $eventType,
                'user_id' => $user ? $user->id : null,
                'ip_address' => $ipAddress,
                'severity' => $this->determineSeverity($eventType),
                'details' => $details
            ]);
        } catch (\Exception $e) {
            \Log::error('Security event logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get failed login attempts count for IP
     */
    private function getFailedAttemptsCount(string $ipAddress): int
    {
        return SecurityEvent::where('ip_address', $ipAddress)
            ->where('event_type', 'failed_login_attempt')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
    }

    /**
     * Check if IP is suspicious
     */
    private function isSuspiciousIp(string $ipAddress): bool
    {
        // Simple check - in production you might use a more sophisticated approach
        $recentEvents = SecurityEvent::where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        return $recentEvents > 10;
    }

    /**
     * Check for unusual activity
     */
    private function isUnusualActivity($user, string $ipAddress): bool
    {
        if (!$user) {
            return false;
        }

        // Check for multiple failed attempts
        $failedAttempts = SecurityEvent::where('user_id', $user->id)
            ->where('event_type', 'failed_login_attempt')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        return $failedAttempts > 5;
    }

    /**
     * Determine security event severity
     */
    private function determineSeverity(string $eventType): string
    {
        $highSeverityEvents = [
            'failed_login_attempt',
            'suspicious_ip_access',
            'unusual_activity'
        ];

        return in_array($eventType, $highSeverityEvents) ? 'high' : 'medium';
    }
} 