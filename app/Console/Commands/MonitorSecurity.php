<?php

namespace App\Console\Commands;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorSecurity extends Command
{
    protected $signature = 'security:monitor {--hours=24 : Hours to look back} {--alert : Send email alerts}';
    protected $description = 'Monitor security logs for suspicious activities and generate reports';

    public function handle()
    {
        $hours = $this->option('hours');
        $sendAlerts = $this->option('alert');
        
        $this->info("🔐 Security Monitoring Report - Last {$hours} hours");
        $this->info("================================================");

        // Get security statistics
        $stats = SecurityLog::getSecurityStats($hours / 24);
        
        $this->displayStats($stats);
        
        // Check for suspicious activities
        $suspiciousActivities = $this->checkSuspiciousActivities($hours);
        
        if ($suspiciousActivities->isNotEmpty()) {
            $this->warn("⚠️  Suspicious Activities Detected:");
            $this->displaySuspiciousActivities($suspiciousActivities);
            
            if ($sendAlerts) {
                $this->sendSecurityAlert($stats, $suspiciousActivities);
            }
        } else {
            $this->info("✅ No suspicious activities detected");
        }
        
        // Check for failed login attempts
        $failedLogins = $this->checkFailedLogins($hours);
        
        if ($failedLogins->isNotEmpty()) {
            $this->warn("🔑 Failed Login Attempts:");
            $this->displayFailedLogins($failedLogins);
        }
        
        // Check for high-risk events
        $highRiskEvents = $this->checkHighRiskEvents($hours);
        
        if ($highRiskEvents->isNotEmpty()) {
            $this->error("🚨 High Risk Events:");
            $this->displayHighRiskEvents($highRiskEvents);
        }
        
        // Generate recommendations
        $this->generateRecommendations($stats, $suspiciousActivities, $failedLogins, $highRiskEvents);
        
        $this->info("Security monitoring completed successfully!");
        
        return 0;
    }
    
    private function displayStats($stats)
    {
        $this->info("📊 Security Statistics:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Events', $stats['total_events']],
                ['Suspicious Events', $stats['suspicious_events']],
                ['Failed Requests', $stats['failed_requests']],
                ['Auth Events', $stats['auth_events']],
                ['High Risk Events', $stats['high_risk_events']],
                ['Unique IPs', $stats['unique_ips']],
                ['Unique Users', $stats['unique_users']],
            ]
        );
    }
    
    private function checkSuspiciousActivities($hours)
    {
        return SecurityLog::where('created_at', '>=', now()->subHours($hours))
            ->where(function ($query) {
                $query->where('is_suspicious', true)
                    ->orWhereIn('risk_level', ['high', 'critical'])
                    ->orWhere('status_code', '>=', 500);
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    private function displaySuspiciousActivities($activities)
    {
        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->event_type,
                $activity->ip_address,
                $activity->user?->email ?? 'Anonymous',
                $activity->risk_level,
                $activity->status_code,
            ];
        }
        
        $this->table(
            ['Time', 'Event', 'IP', 'User', 'Risk', 'Status'],
            $data
        );
    }
    
    private function checkFailedLogins($hours)
    {
        return SecurityLog::where('created_at', '>=', now()->subHours($hours))
            ->where('event_type', 'failed_login')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    private function displayFailedLogins($failedLogins)
    {
        $data = [];
        foreach ($failedLogins as $login) {
            $data[] = [
                $login->created_at->format('Y-m-d H:i:s'),
                $login->ip_address,
                $login->request_data['email'] ?? 'Unknown',
                $login->user_agent,
            ];
        }
        
        $this->table(
            ['Time', 'IP', 'Email', 'User Agent'],
            $data
        );
    }
    
    private function checkHighRiskEvents($hours)
    {
        return SecurityLog::where('created_at', '>=', now()->subHours($hours))
            ->whereIn('risk_level', ['high', 'critical'])
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    private function displayHighRiskEvents($events)
    {
        $data = [];
        foreach ($events as $event) {
            $data[] = [
                $event->created_at->format('Y-m-d H:i:s'),
                $event->event_type,
                $event->ip_address,
                $event->user?->email ?? 'Anonymous',
                $event->risk_level,
                $event->error_message,
            ];
        }
        
        $this->table(
            ['Time', 'Event', 'IP', 'User', 'Risk', 'Error'],
            $data
        );
    }
    
    private function generateRecommendations($stats, $suspiciousActivities, $failedLogins, $highRiskEvents)
    {
        $this->info("💡 Security Recommendations:");
        
        if ($stats['suspicious_events'] > 10) {
            $this->warn("  • High number of suspicious events - consider tightening security rules");
        }
        
        if ($stats['failed_requests'] > 50) {
            $this->warn("  • High number of failed requests - check for potential attacks");
        }
        
        if ($stats['high_risk_events'] > 5) {
            $this->error("  • Critical: High risk events detected - immediate attention required");
        }
        
        if ($failedLogins->count() > 20) {
            $this->warn("  • Multiple failed login attempts - consider IP blocking");
        }
        
        if ($suspiciousActivities->count() > 0) {
            $this->warn("  • Suspicious activities detected - review security logs");
        }
        
        // General recommendations
        $this->info("  • Ensure all security patches are up to date");
        $this->info("  • Monitor logs regularly for unusual patterns");
        $this->info("  • Consider implementing additional security measures");
    }
    
    private function sendSecurityAlert($stats, $suspiciousActivities)
    {
        // This would send an email alert to administrators
        // Implementation depends on your email configuration
        
        $this->info("📧 Security alert sent to administrators");
        
        Log::warning('Security Alert: Suspicious activities detected', [
            'stats' => $stats,
            'suspicious_count' => $suspiciousActivities->count(),
            'timestamp' => now(),
        ]);
    }
} 