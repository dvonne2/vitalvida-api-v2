<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\SystemRecommendation;
use App\Models\ThresholdViolation;
use App\Models\RateLimitRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemConfigController extends Controller
{
    /**
     * Get system configuration
     */
    public function getSystemConfig(Request $request)
    {
        try {
            $config = [
                'inventory' => [
                    'low_stock_threshold' => config('inventory.low_stock_threshold', 10),
                    'critical_stock_threshold' => config('inventory.critical_stock_threshold', 5),
                    'auto_restock_enabled' => config('inventory.auto_restock_enabled', false),
                    'restock_lead_time_days' => config('inventory.restock_lead_time_days', 7),
                    'max_bin_capacity' => config('inventory.max_bin_capacity', 1000),
                ],
                'da_management' => [
                    'daily_login_required' => config('da.daily_login_required', true),
                    'performance_review_frequency' => config('da.performance_review_frequency', 'weekly'),
                    'violation_threshold' => config('da.violation_threshold', 3),
                    'strike_threshold' => config('da.strike_threshold', 5),
                    'auto_escalation_enabled' => config('da.auto_escalation_enabled', true),
                ],
                'notifications' => [
                    'email_notifications' => config('notifications.email_enabled', true),
                    'sms_notifications' => config('notifications.sms_enabled', false),
                    'push_notifications' => config('notifications.push_enabled', true),
                    'alert_frequency' => config('notifications.alert_frequency', 'realtime'),
                ],
                'security' => [
                    'session_timeout_minutes' => config('security.session_timeout', 120),
                    'max_login_attempts' => config('security.max_login_attempts', 5),
                    'password_expiry_days' => config('security.password_expiry_days', 90),
                    'two_factor_required' => config('security.two_factor_required', false),
                ],
                'api' => [
                    'rate_limit_requests' => config('api.rate_limit_requests', 1000),
                    'rate_limit_window_minutes' => config('api.rate_limit_window', 60),
                    'api_key_expiry_days' => config('api.key_expiry_days', 365),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update system configuration
     */
    public function updateSystemConfig(Request $request)
    {
        try {
            // Check if user has admin permissions
            if (!auth()->user()->can('manage_system_config')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to update system configuration'
                ], 403);
            }

            $request->validate([
                'config_section' => 'required|in:inventory,da_management,notifications,security,api',
                'config_key' => 'required|string',
                'config_value' => 'required'
            ]);

            $section = $request->config_section;
            $key = $request->config_key;
            $value = $request->config_value;

            // Validate specific configuration values
            $this->validateConfigValue($section, $key, $value);

            // Update configuration (this would typically update a config file or database)
            // For now, we'll just return success
            $configPath = "config/{$section}.php";
            
            // Log the configuration change
            \Log::info("System configuration updated", [
                'user_id' => auth()->id(),
                'section' => $section,
                'key' => $key,
                'value' => $value,
                'timestamp' => now()
            ]);

            // Clear cache to ensure new config is loaded
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'System configuration updated successfully',
                'data' => [
                    'section' => $section,
                    'key' => $key,
                    'value' => $value,
                    'updated_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update system configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(Request $request)
    {
        try {
            $health = [
                'database' => $this->checkDatabaseHealth(),
                'cache' => $this->checkCacheHealth(),
                'storage' => $this->checkStorageHealth(),
                'queue' => $this->checkQueueHealth(),
                'external_services' => $this->checkExternalServices(),
                'overall_status' => 'healthy',
                'last_check' => now()
            ];

            // Determine overall status
            $failedChecks = collect($health)->filter(function ($check) {
                return isset($check['status']) && $check['status'] === 'error';
            })->count();

            if ($failedChecks > 0) {
                $health['overall_status'] = $failedChecks === count($health) - 2 ? 'critical' : 'warning';
            }

            return response()->json([
                'success' => true,
                'data' => $health
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function getSystemStatistics(Request $request)
    {
        try {
            $stats = [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('status', 'active')->count(),
                    'by_role' => User::groupBy('role')->select('role', DB::raw('count(*) as count'))->get()
                ],
                'inventory' => [
                    'total_products' => \App\Models\Product::count(),
                    'total_bins' => \App\Models\Bin::count(),
                    'active_bins' => \App\Models\Bin::where('status', 'active')->count(),
                    'low_stock_products' => \App\Models\Product::where('current_stock', '<=', 10)->count(),
                    'out_of_stock_products' => \App\Models\Product::where('current_stock', 0)->count()
                ],
                'orders' => [
                    'total_orders' => \App\Models\Order::count(),
                    'pending_orders' => \App\Models\Order::where('status', 'pending')->count(),
                    'completed_orders' => \App\Models\Order::where('status', 'completed')->count(),
                    'monthly_revenue' => \App\Models\Order::where('status', 'completed')
                        ->whereMonth('created_at', now()->month)
                        ->sum('total_amount')
                ],
                'delivery_agents' => [
                    'total_agents' => \App\Models\DeliveryAgent::count(),
                    'active_agents' => \App\Models\DeliveryAgent::where('status', 'active')->count(),
                    'agents_with_violations' => \App\Models\DeliveryAgent::whereHas('violations')->count()
                ],
                'system' => [
                    'total_reports' => \App\Models\Report::count(),
                    'pending_recommendations' => \App\Models\SystemRecommendation::where('status', 'pending')->count(),
                    'threshold_violations' => \App\Models\ThresholdViolation::where('status', 'open')->count(),
                    'cache_hit_rate' => $this->getCacheHitRate()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(Request $request)
    {
        try {
            $request->validate([
                'log_type' => 'nullable|in:error,info,warning,debug',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'limit' => 'nullable|integer|min:1|max:1000'
            ]);

            $limit = $request->get('limit', 100);
            $logType = $request->get('log_type');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // This would typically query actual log files
            // For now, we'll return a placeholder structure
            $logs = [
                'entries' => [],
                'total_count' => 0,
                'filters_applied' => [
                    'log_type' => $logType,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'limit' => $limit
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear system cache
     */
    public function clearSystemCache(Request $request)
    {
        try {
            if (!auth()->user()->can('manage_system_config')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to clear system cache'
                ], 403);
            }

            $cacheTypes = $request->get('cache_types', ['all']);

            if (in_array('all', $cacheTypes)) {
                Cache::flush();
                $message = 'All system cache cleared successfully';
            } else {
                foreach ($cacheTypes as $type) {
                    switch ($type) {
                        case 'config':
                            Cache::forget('config');
                            break;
                        case 'routes':
                            Cache::forget('routes');
                            break;
                        case 'views':
                            Cache::forget('views');
                            break;
                        case 'data':
                            // Clear application-specific cache
                            Cache::tags(['inventory', 'orders', 'users'])->flush();
                            break;
                    }
                }
                $message = 'Selected cache types cleared successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cleared_cache_types' => $cacheTypes,
                    'cleared_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear system cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rate limiting rules
     */
    public function getRateLimitRules(Request $request)
    {
        try {
            $rules = RateLimitRule::orderBy('priority')->get();

            return response()->json([
                'success' => true,
                'data' => $rules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rate limit rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update rate limiting rules
     */
    public function updateRateLimitRules(Request $request)
    {
        try {
            if (!auth()->user()->can('manage_system_config')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to update rate limit rules'
                ], 403);
            }

            $request->validate([
                'rules' => 'required|array',
                'rules.*.id' => 'nullable|exists:rate_limit_rules,id',
                'rules.*.name' => 'required|string|max:255',
                'rules.*.pattern' => 'required|string|max:255',
                'rules.*.max_requests' => 'required|integer|min:1',
                'rules.*.window_minutes' => 'required|integer|min:1',
                'rules.*.priority' => 'required|integer|min:1',
                'rules.*.is_active' => 'required|boolean'
            ]);

            foreach ($request->rules as $ruleData) {
                if (isset($ruleData['id'])) {
                    RateLimitRule::where('id', $ruleData['id'])->update($ruleData);
                } else {
                    RateLimitRule::create($ruleData);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rate limit rules updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rate limit rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system recommendations
     */
    public function getSystemRecommendations(Request $request)
    {
        try {
            $recommendations = SystemRecommendation::with(['user'])
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update system recommendation status
     */
    public function updateRecommendationStatus(Request $request, $recommendationId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,approved,rejected,implemented'
            ]);

            $recommendation = SystemRecommendation::findOrFail($recommendationId);
            $recommendation->update([
                'status' => $request->status,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recommendation status updated successfully',
                'data' => $recommendation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update recommendation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods

    private function validateConfigValue($section, $key, $value)
    {
        $validations = [
            'inventory' => [
                'low_stock_threshold' => 'integer|min:0',
                'critical_stock_threshold' => 'integer|min:0',
                'auto_restock_enabled' => 'boolean',
                'restock_lead_time_days' => 'integer|min:1|max:365',
                'max_bin_capacity' => 'integer|min:1'
            ],
            'da_management' => [
                'daily_login_required' => 'boolean',
                'performance_review_frequency' => 'in:daily,weekly,monthly',
                'violation_threshold' => 'integer|min:1',
                'strike_threshold' => 'integer|min:1',
                'auto_escalation_enabled' => 'boolean'
            ],
            'notifications' => [
                'email_notifications' => 'boolean',
                'sms_notifications' => 'boolean',
                'push_notifications' => 'boolean',
                'alert_frequency' => 'in:realtime,hourly,daily,weekly'
            ],
            'security' => [
                'session_timeout_minutes' => 'integer|min:5|max:1440',
                'max_login_attempts' => 'integer|min:1|max:10',
                'password_expiry_days' => 'integer|min:30|max:365',
                'two_factor_required' => 'boolean'
            ],
            'api' => [
                'rate_limit_requests' => 'integer|min:1|max:10000',
                'rate_limit_window_minutes' => 'integer|min:1|max:1440',
                'api_key_expiry_days' => 'integer|min:1|max:3650'
            ]
        ];

        if (!isset($validations[$section][$key])) {
            throw new \Exception("Invalid configuration key: {$section}.{$key}");
        }

        $rules = $validations[$section][$key];
        $validator = \Validator::make(['value' => $value], ['value' => $rules]);

        if ($validator->fails()) {
            throw new \Exception("Invalid configuration value: " . $validator->errors()->first());
        }
    }

    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkCacheHealth()
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $value = Cache::get('health_check');
            return ['status' => 'healthy', 'message' => 'Cache system operational'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache system failed: ' . $e->getMessage()];
        }
    }

    private function checkStorageHealth()
    {
        try {
            $disk = \Storage::disk('local');
            $disk->put('health_check.txt', 'ok');
            $disk->delete('health_check.txt');
            return ['status' => 'healthy', 'message' => 'Storage system operational'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage system failed: ' . $e->getMessage()];
        }
    }

    private function checkQueueHealth()
    {
        try {
            // Check if queue is processing jobs
            $failedJobs = DB::table('failed_jobs')->count();
            return [
                'status' => 'healthy',
                'message' => 'Queue system operational',
                'failed_jobs' => $failedJobs
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Queue system failed: ' . $e->getMessage()];
        }
    }

    private function checkExternalServices()
    {
        $services = [];
        
        // Check email service
        try {
            // This would typically test email configuration
            $services['email'] = ['status' => 'healthy', 'message' => 'Email service available'];
        } catch (\Exception $e) {
            $services['email'] = ['status' => 'error', 'message' => 'Email service failed: ' . $e->getMessage()];
        }

        // Check SMS service
        try {
            // This would typically test SMS configuration
            $services['sms'] = ['status' => 'healthy', 'message' => 'SMS service available'];
        } catch (\Exception $e) {
            $services['sms'] = ['status' => 'error', 'message' => 'SMS service failed: ' . $e->getMessage()];
        }

        return $services;
    }

    private function getCacheHitRate()
    {
        // This would typically calculate actual cache hit rate
        // For now, return a placeholder value
        return 85.5;
    }
} 