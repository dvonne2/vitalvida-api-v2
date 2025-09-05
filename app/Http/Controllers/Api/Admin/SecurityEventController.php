<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SecurityEventController extends Controller
{
    /**
     * Get security events with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = SecurityEvent::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage = $request->get('per_page', 15);
        $events = $query->paginate($perPage);

        // Get summary statistics
        $totalEvents = SecurityEvent::count();
        $highSeverity = SecurityEvent::where('severity', 'high')->count();
        $mediumSeverity = SecurityEvent::where('severity', 'medium')->count();
        $lowSeverity = SecurityEvent::where('severity', 'low')->count();

        return response()->json([
            'data' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'user_id' => $event->user_id,
                    'user' => $event->user ? $event->user->username : null,
                    'ip_address' => $event->ip_address,
                    'severity' => $event->severity,
                    'timestamp' => $event->created_at->format('Y-m-d H:i:s'),
                    'details' => $event->details
                ];
            }),
            'summary' => [
                'total_events' => $totalEvents,
                'high_severity' => $highSeverity,
                'medium_severity' => $mediumSeverity,
                'low_severity' => $lowSeverity
            ],
            'meta' => [
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total()
                ]
            ]
        ]);
    }

    /**
     * Get security event statistics
     */
    public function getStats(): JsonResponse
    {
        $today = today();
        $yesterday = today()->subDay();

        $stats = [
            'today' => [
                'total' => SecurityEvent::whereDate('created_at', $today)->count(),
                'high' => SecurityEvent::where('severity', 'high')->whereDate('created_at', $today)->count(),
                'medium' => SecurityEvent::where('severity', 'medium')->whereDate('created_at', $today)->count(),
                'low' => SecurityEvent::where('severity', 'low')->whereDate('created_at', $today)->count(),
            ],
            'yesterday' => [
                'total' => SecurityEvent::whereDate('created_at', $yesterday)->count(),
                'high' => SecurityEvent::where('severity', 'high')->whereDate('created_at', $yesterday)->count(),
                'medium' => SecurityEvent::where('severity', 'medium')->whereDate('created_at', $yesterday)->count(),
                'low' => SecurityEvent::where('severity', 'low')->whereDate('created_at', $yesterday)->count(),
            ],
            'event_types' => SecurityEvent::selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * Get critical security alerts
     */
    public function getCriticalAlerts(): JsonResponse
    {
        $alerts = SecurityEvent::where('severity', 'high')
            ->orWhere('severity', 'critical')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'alerts' => $alerts->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'event_type' => $alert->event_type,
                    'severity' => $alert->severity,
                    'user' => $alert->user ? $alert->user->username : 'Unknown',
                    'ip_address' => $alert->ip_address,
                    'timestamp' => $alert->created_at->format('Y-m-d H:i:s'),
                    'details' => $alert->details
                ];
            }),
            'total_critical' => SecurityEvent::whereIn('severity', ['high', 'critical'])->count()
        ]);
    }

    /**
     * Get security events by IP address
     */
    public function getEventsByIp(string $ipAddress): JsonResponse
    {
        $events = SecurityEvent::where('ip_address', $ipAddress)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'ip_address' => $ipAddress,
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'severity' => $event->severity,
                    'user' => $event->user ? $event->user->username : null,
                    'timestamp' => $event->created_at->format('Y-m-d H:i:s'),
                    'details' => $event->details
                ];
            }),
            'meta' => [
                'total_events' => $events->total(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage()
                ]
            ]
        ]);
    }

    /**
     * Get security events for specific user
     */
    public function getUserEvents(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        $events = SecurityEvent::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email
            ],
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'severity' => $event->severity,
                    'ip_address' => $event->ip_address,
                    'timestamp' => $event->created_at->format('Y-m-d H:i:s'),
                    'details' => $event->details
                ];
            }),
            'meta' => [
                'total_events' => $events->total(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage()
                ]
            ]
        ]);
    }

    /**
     * Export security events
     */
    public function export(Request $request): JsonResponse
    {
        $query = SecurityEvent::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $events = $query->get();

        $csvData = [];
        $csvData[] = ['Timestamp', 'Event Type', 'Severity', 'User', 'IP Address', 'Details'];

        foreach ($events as $event) {
            $csvData[] = [
                $event->created_at->format('Y-m-d H:i:s'),
                $event->event_type,
                $event->severity,
                $event->user ? $event->user->username : 'System',
                $event->ip_address,
                json_encode($event->details)
            ];
        }

        $filename = 'security_events_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Store CSV file temporarily
        $filePath = storage_path('app/temp/' . $filename);
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        return response()->json([
            'success' => true,
            'message' => 'Security events exported successfully',
            'download_url' => url('storage/temp/' . $filename),
            'filename' => $filename,
            'total_records' => count($events)
        ]);
    }

    /**
     * Clean old security events
     */
    public function cleanup(Request $request): JsonResponse
    {
        $retentionDays = $request->get('retention_days', 60);
        $cutoffDate = now()->subDays($retentionDays);

        $deletedCount = SecurityEvent::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old security events",
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays
        ]);
    }
} 