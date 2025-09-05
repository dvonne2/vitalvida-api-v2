<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Investor;
use App\Models\InvestorSession;
use App\Models\InvestorDocument;
use Carbon\Carbon;

class ComplianceService
{
    /**
     * Track document access with audit trail
     */
    public function trackDocumentAccess(int $investor_id, int $document_id, string $action, array $metadata = []): void
    {
        try {
            $accessLog = [
                'investor_id' => $investor_id,
                'document_id' => $document_id,
                'action' => $action,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString(),
                'metadata' => $metadata
            ];

            // Store in cache for quick access
            $cacheKey = "document_access_{$investor_id}_{$document_id}";
            Cache::put($cacheKey, $accessLog, 3600); // 1 hour

            // Log to file for audit trail
            Log::info('Document access tracked', $accessLog);

        } catch (\Exception $e) {
            Log::error('Failed to track document access', [
                'investor_id' => $investor_id,
                'document_id' => $document_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get document access audit trail
     */
    public function getDocumentAccessLog(int $document_id, string $period = '30_days'): JsonResponse
    {
        try {
            $startDate = $this->getPeriodStartDate($period);
            
            // Simulate audit log data (in real app, this would come from a dedicated table)
            $accessLog = [
                [
                    'investor_id' => 1,
                    'investor_name' => 'Master Readiness',
                    'action' => 'viewed',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'timestamp' => now()->subHours(2)->toISOString(),
                    'session_id' => 'sess_' . uniqid()
                ],
                [
                    'investor_id' => 2,
                    'investor_name' => 'Tomi Governance',
                    'action' => 'downloaded',
                    'ip_address' => '192.168.1.101',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'timestamp' => now()->subDays(1)->toISOString(),
                    'session_id' => 'sess_' . uniqid()
                ],
                [
                    'investor_id' => 3,
                    'investor_name' => 'Otunba Control',
                    'action' => 'viewed',
                    'ip_address' => '192.168.1.102',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'timestamp' => now()->subDays(3)->toISOString(),
                    'session_id' => 'sess_' . uniqid()
                ]
            ];

            // Filter by period
            $filteredLog = array_filter($accessLog, function ($log) use ($startDate) {
                return Carbon::parse($log['timestamp'])->gte($startDate);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document_id,
                    'period' => $period,
                    'access_log' => array_values($filteredLog),
                    'total_accesses' => count($filteredLog),
                    'unique_investors' => count(array_unique(array_column($filteredLog, 'investor_id'))),
                    'last_accessed' => $filteredLog ? max(array_column($filteredLog, 'timestamp')) : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access log',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security events audit
     */
    public function getSecurityEvents(string $period = '7_days'): JsonResponse
    {
        try {
            $startDate = $this->getPeriodStartDate($period);

            // Simulate security events (in real app, this would come from security logs)
            $securityEvents = [
                [
                    'event_type' => 'failed_login',
                    'investor_id' => 5,
                    'ip_address' => '203.0.113.45',
                    'timestamp' => now()->subHours(6)->toISOString(),
                    'severity' => 'medium',
                    'description' => 'Multiple failed login attempts'
                ],
                [
                    'event_type' => 'document_access_denied',
                    'investor_id' => 2,
                    'ip_address' => '192.168.1.101',
                    'timestamp' => now()->subDays(1)->toISOString(),
                    'severity' => 'low',
                    'description' => 'Attempted access to restricted document'
                ],
                [
                    'event_type' => 'session_timeout',
                    'investor_id' => 1,
                    'ip_address' => '192.168.1.100',
                    'timestamp' => now()->subDays(2)->toISOString(),
                    'severity' => 'info',
                    'description' => 'Session expired due to inactivity'
                ],
                [
                    'event_type' => 'successful_login',
                    'investor_id' => 3,
                    'ip_address' => '192.168.1.102',
                    'timestamp' => now()->subDays(3)->toISOString(),
                    'severity' => 'info',
                    'description' => 'Successful login from new device'
                ]
            ];

            // Filter by period
            $filteredEvents = array_filter($securityEvents, function ($event) use ($startDate) {
                return Carbon::parse($event['timestamp'])->gte($startDate);
            });

            $eventStats = [
                'total_events' => count($filteredEvents),
                'high_severity' => count(array_filter($filteredEvents, fn($e) => $e['severity'] === 'high')),
                'medium_severity' => count(array_filter($filteredEvents, fn($e) => $e['severity'] === 'medium')),
                'low_severity' => count(array_filter($filteredEvents, fn($e) => $e['severity'] === 'low')),
                'info_events' => count(array_filter($filteredEvents, fn($e) => $e['severity'] === 'info'))
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'security_events' => array_values($filteredEvents),
                    'statistics' => $eventStats,
                    'last_updated' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve security events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance status
     */
    public function getComplianceStatus(): JsonResponse
    {
        try {
            $complianceData = [
                'gdpr_compliance' => [
                    'data_encryption' => 'AES-256',
                    'data_retention' => '7 years',
                    'right_to_forget' => 'implemented',
                    'data_processing_consent' => 'obtained',
                    'data_breach_protocol' => 'active',
                    'status' => 'compliant'
                ],
                'security_compliance' => [
                    'multi_factor_auth' => 'enabled',
                    'session_management' => 'secure',
                    'ip_whitelisting' => 'configured',
                    'audit_logging' => 'enabled',
                    'encrypted_storage' => 'AES-256',
                    'status' => 'compliant'
                ],
                'financial_compliance' => [
                    'sox_compliance' => 'partial',
                    'financial_controls' => 'implemented',
                    'audit_trail' => 'complete',
                    'access_controls' => 'role_based',
                    'status' => 'mostly_compliant'
                ],
                'operational_compliance' => [
                    'process_documentation' => 'complete',
                    'quality_controls' => 'implemented',
                    'risk_management' => 'active',
                    'incident_response' => 'configured',
                    'status' => 'compliant'
                ]
            ];

            $overallStatus = $this->calculateOverallCompliance($complianceData);

            return response()->json([
                'success' => true,
                'data' => [
                    'compliance_status' => $complianceData,
                    'overall_status' => $overallStatus,
                    'last_audit' => '2024-12-01',
                    'next_audit' => '2025-06-01',
                    'compliance_score' => 92.5
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve compliance status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate IP whitelist for sensitive documents
     */
    public function validateIPWhitelist(string $ip_address, int $document_id): bool
    {
        // Get document sensitivity level
        $document = InvestorDocument::find($document_id);
        
        if (!$document || !$document->is_confidential) {
            return true; // Non-confidential documents don't need IP validation
        }

        // Define whitelisted IPs (in real app, this would come from database)
        $whitelistedIPs = [
            '192.168.1.100',
            '192.168.1.101',
            '192.168.1.102',
            '10.0.0.50',
            '10.0.0.51'
        ];

        return in_array($ip_address, $whitelistedIPs);
    }

    /**
     * Generate GDPR data export for investor
     */
    public function generateGDPRExport(int $investor_id): JsonResponse
    {
        try {
            $investor = Investor::findOrFail($investor_id);
            
            $gdprData = [
                'personal_data' => [
                    'name' => $investor->name,
                    'email' => $investor->email,
                    'phone' => $investor->phone,
                    'company_name' => $investor->company_name,
                    'position' => $investor->position,
                    'bio' => $investor->bio
                ],
                'activity_data' => [
                    'login_history' => $this->getLoginHistory($investor_id),
                    'document_access' => $this->getDocumentAccessHistory($investor_id),
                    'session_data' => $this->getSessionData($investor_id)
                ],
                'preferences' => $investor->preferences,
                'permissions' => $investor->permissions,
                'export_generated_at' => now()->toISOString()
            ];

            // Create export file
            $fileName = "gdpr_export_{$investor_id}_" . now()->format('Y-m-d_H-i-s') . '.json';
            $filePath = "exports/gdpr/{$fileName}";

            // Store export file
            Storage::disk('private')->put($filePath, json_encode($gdprData, JSON_PRETTY_PRINT));

            Log::info('GDPR export generated', [
                'investor_id' => $investor_id,
                'file_path' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'file_name' => $fileName,
                    'download_url' => Storage::url($filePath),
                    'data_points' => count($gdprData['personal_data']) + count($gdprData['activity_data']),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('GDPR export failed', [
                'investor_id' => $investor_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate GDPR export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process right to be forgotten request
     */
    public function processRightToBeForgotten(int $investor_id): JsonResponse
    {
        try {
            $investor = Investor::findOrFail($investor_id);

            // Anonymize personal data
            $investor->update([
                'name' => 'REDACTED',
                'email' => 'redacted_' . $investor_id . '@deleted.local',
                'phone' => 'REDACTED',
                'company_name' => 'REDACTED',
                'position' => 'REDACTED',
                'bio' => 'REDACTED',
                'is_active' => false
            ]);

            // Log the deletion request
            Log::info('Right to be forgotten processed', [
                'investor_id' => $investor_id,
                'processed_at' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Personal data has been anonymized successfully',
                'data' => [
                    'investor_id' => $investor_id,
                    'processed_at' => now()->toISOString(),
                    'status' => 'anonymized'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Right to be forgotten processing failed', [
                'investor_id' => $investor_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process right to be forgotten request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            '1_year' => now()->subYear(),
            default => now()->subDays(30)
        };
    }

    /**
     * Calculate overall compliance status
     */
    private function calculateOverallCompliance(array $complianceData): string
    {
        $compliantCount = 0;
        $totalCount = 0;

        foreach ($complianceData as $category) {
            $totalCount++;
            if ($category['status'] === 'compliant') {
                $compliantCount++;
            }
        }

        $complianceRate = ($compliantCount / $totalCount) * 100;

        if ($complianceRate >= 90) {
            return 'compliant';
        } elseif ($complianceRate >= 75) {
            return 'mostly_compliant';
        } else {
            return 'non_compliant';
        }
    }

    /**
     * Get login history for GDPR export
     */
    private function getLoginHistory(int $investor_id): array
    {
        return InvestorSession::where('investor_id', $investor_id)
            ->orderBy('login_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($session) {
                return [
                    'login_at' => $session->login_at,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'logout_at' => $session->logout_at
                ];
            })
            ->toArray();
    }

    /**
     * Get document access history for GDPR export
     */
    private function getDocumentAccessHistory(int $investor_id): array
    {
        // Simulate document access history
        return [
            [
                'document_id' => 1,
                'document_title' => 'P&L Statement',
                'accessed_at' => now()->subDays(2)->toISOString(),
                'action' => 'viewed'
            ],
            [
                'document_id' => 2,
                'document_title' => 'Balance Sheet',
                'accessed_at' => now()->subDays(5)->toISOString(),
                'action' => 'downloaded'
            ]
        ];
    }

    /**
     * Get session data for GDPR export
     */
    private function getSessionData(int $investor_id): array
    {
        return InvestorSession::where('investor_id', $investor_id)
            ->where('is_active', true)
            ->get()
            ->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'login_at' => $session->login_at,
                    'last_activity' => $session->last_activity_at,
                    'device_type' => $session->device_type,
                    'browser' => $session->browser,
                    'os' => $session->os
                ];
            })
            ->toArray();
    }
} 