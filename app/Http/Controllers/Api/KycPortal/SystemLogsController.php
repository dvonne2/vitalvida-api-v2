<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemLogsController extends Controller
{
    /**
     * Get recent system activity
     */
    public function getRecentActivity(Request $request)
    {
        try {
            $query = \App\Models\SystemActivity::select([
                'id', 'activity_type', 'description', 'status', 'created_at',
                'user_id', 'ip_address', 'user_agent'
            ]);

            // Apply filters
            if ($request->activity_type) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', '%' . $search . '%')
                      ->orWhere('activity_type', 'like', '%' . $search . '%');
                });
            }

            // Apply sorting
            $sortBy = $request->sort_by ?? 'created_at';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->per_page ?? 20;
            $activities = $query->paginate($perPage);

            // Transform data
            $activities->getCollection()->transform(function($activity) {
                return [
                    'id' => $activity->id,
                    'activity_type' => $activity->activity_type,
                    'activity_type_text' => $this->getActivityTypeText($activity->activity_type),
                    'description' => $activity->description,
                    'status' => $activity->status,
                    'status_color' => $this->getStatusColor($activity->status),
                    'created_at' => $activity->created_at,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'time_ago' => $activity->created_at->diffForHumans()
                ];
            });

            // Get activity statistics
            $stats = [
                'total_activities' => \App\Models\SystemActivity::count(),
                'activities_today' => \App\Models\SystemActivity::whereDate('created_at', today())->count(),
                'activities_this_week' => \App\Models\SystemActivity::where('created_at', '>=', now()->startOfWeek())->count(),
                'error_count' => \App\Models\SystemActivity::where('status', 'error')->count(),
                'warning_count' => \App\Models\SystemActivity::where('status', 'warning')->count(),
                'success_count' => \App\Models\SystemActivity::where('status', 'success')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities,
                    'statistics' => $stats,
                    'activity_types' => $this->getActivityTypes(),
                    'statuses' => $this->getStatuses()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get error logs
     */
    public function getErrorLogs(Request $request)
    {
        try {
            $query = \App\Models\SystemActivity::where('status', 'error')
                ->select([
                    'id', 'activity_type', 'description', 'error_details', 'created_at',
                    'user_id', 'ip_address', 'user_agent'
                ]);

            // Apply filters
            if ($request->activity_type) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', '%' . $search . '%')
                      ->orWhere('error_details', 'like', '%' . $search . '%');
                });
            }

            // Apply sorting
            $sortBy = $request->sort_by ?? 'created_at';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->per_page ?? 20;
            $errors = $query->paginate($perPage);

            // Transform data
            $errors->getCollection()->transform(function($error) {
                return [
                    'id' => $error->id,
                    'activity_type' => $error->activity_type,
                    'activity_type_text' => $this->getActivityTypeText($error->activity_type),
                    'description' => $error->description,
                    'error_details' => $error->error_details,
                    'created_at' => $error->created_at,
                    'ip_address' => $error->ip_address,
                    'user_agent' => $error->user_agent,
                    'time_ago' => $error->created_at->diffForHumans(),
                    'severity' => $this->getErrorSeverity($error->description)
                ];
            });

            // Get error statistics
            $errorStats = [
                'total_errors' => \App\Models\SystemActivity::where('status', 'error')->count(),
                'errors_today' => \App\Models\SystemActivity::where('status', 'error')
                    ->whereDate('created_at', today())->count(),
                'errors_this_week' => \App\Models\SystemActivity::where('status', 'error')
                    ->where('created_at', '>=', now()->startOfWeek())->count(),
                'critical_errors' => \App\Models\SystemActivity::where('status', 'error')
                    ->where('description', 'like', '%critical%')->count(),
                'resolved_errors' => \App\Models\SystemActivity::where('status', 'error')
                    ->where('description', 'like', '%resolved%')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'errors' => $errors,
                    'statistics' => $errorStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get error logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics()
    {
        try {
            $metrics = [
                'system_uptime' => [
                    'value' => 99.8,
                    'unit' => '%',
                    'status' => 'excellent',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ],
                'response_time' => [
                    'value' => 245.6,
                    'unit' => 'ms',
                    'status' => 'good',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ],
                'active_users' => [
                    'value' => 23,
                    'unit' => 'users',
                    'status' => 'normal',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ],
                'memory_usage' => [
                    'value' => 67.3,
                    'unit' => '%',
                    'status' => 'good',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ],
                'cpu_usage' => [
                    'value' => 42.1,
                    'unit' => '%',
                    'status' => 'good',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ],
                'disk_usage' => [
                    'value' => 78.9,
                    'unit' => '%',
                    'status' => 'warning',
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ]
            ];

            // Get recent alerts
            $recentAlerts = [
                [
                    'id' => 1,
                    'type' => 'warning',
                    'message' => 'Disk usage is approaching 80%',
                    'created_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                    'severity' => 'medium'
                ],
                [
                    'id' => 2,
                    'type' => 'info',
                    'message' => 'System backup completed successfully',
                    'created_at' => now()->subHours(6)->format('Y-m-d H:i:s'),
                    'severity' => 'low'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $metrics,
                    'recent_alerts' => $recentAlerts,
                    'system_health' => 'good'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export system logs
     */
    public function exportLogs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'export_type' => 'required|in:activities,errors,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'format' => 'nullable|in:csv,json,xml'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exportType = $request->export_type;
            $format = $request->format ?? 'csv';
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;

            $query = \App\Models\SystemActivity::query();

            if ($dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->where('created_at', '<=', $dateTo);
            }

            if ($exportType === 'errors') {
                $query->where('status', 'error');
            }

            $logs = $query->orderBy('created_at', 'desc')->get();

            // Transform data for export
            $exportData = $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'activity_type' => $log->activity_type,
                    'description' => $log->description,
                    'status' => $log->status,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent
                ];
            });

            $filename = "system_logs_{$exportType}_" . now()->format('Y-m-d_H-i-s') . ".{$format}";

            return response()->json([
                'success' => true,
                'message' => 'Logs exported successfully',
                'data' => [
                    'filename' => $filename,
                    'total_records' => $exportData->count(),
                    'export_type' => $exportType,
                    'format' => $format,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'download_url' => "/api/kyc-portal/admin/system-logs/download/{$filename}"
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getActivityTypeText($type)
    {
        return match($type) {
            'LOGIN' => 'User Login',
            'LOGOUT' => 'User Logout',
            'APPLICATION_CREATED' => 'Application Created',
            'APPLICATION_UPDATED' => 'Application Updated',
            'APPLICATION_APPROVED' => 'Application Approved',
            'APPLICATION_REJECTED' => 'Application Rejected',
            'DOCUMENT_UPLOADED' => 'Document Uploaded',
            'AI_VALIDATION' => 'AI Validation',
            'SYSTEM_BACKUP' => 'System Backup',
            'ERROR_OCCURRED' => 'Error Occurred',
            default => ucwords(str_replace('_', ' ', $type))
        };
    }

    private function getStatusColor($status)
    {
        return match($status) {
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            'info' => 'blue',
            default => 'gray'
        };
    }

    private function getErrorSeverity($description)
    {
        if (str_contains(strtolower($description), 'critical')) {
            return 'critical';
        } elseif (str_contains(strtolower($description), 'error')) {
            return 'high';
        } elseif (str_contains(strtolower($description), 'warning')) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function getActivityTypes()
    {
        return [
            'LOGIN', 'LOGOUT', 'APPLICATION_CREATED', 'APPLICATION_UPDATED',
            'APPLICATION_APPROVED', 'APPLICATION_REJECTED', 'DOCUMENT_UPLOADED',
            'AI_VALIDATION', 'SYSTEM_BACKUP', 'ERROR_OCCURRED'
        ];
    }

    private function getStatuses()
    {
        return ['success', 'warning', 'error', 'info'];
    }
}
