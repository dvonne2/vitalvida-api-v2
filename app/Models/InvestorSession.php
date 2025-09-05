<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'investor_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'login_at',
        'last_activity_at',
        'logout_at',
        'accessed_pages',
        'downloaded_documents',
        'is_active'
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'logout_at' => 'datetime',
        'accessed_pages' => 'array',
        'downloaded_documents' => 'array',
        'is_active' => 'boolean',
    ];

    // Device type constants
    const DEVICE_DESKTOP = 'desktop';
    const DEVICE_MOBILE = 'mobile';
    const DEVICE_TABLET = 'tablet';

    // Relationships
    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByInvestor($query, $investorId)
    {
        return $query->where('investor_id', $investorId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('login_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('login_at', '>=', now()->subDays($days));
    }

    // Business Logic Methods
    public function getSessionDuration()
    {
        $endTime = $this->logout_at ?? now();
        return $endTime->diffInSeconds($this->login_at);
    }

    public function getSessionDurationFormatted()
    {
        $duration = $this->getSessionDuration();
        
        if ($duration < 60) {
            return $duration . ' seconds';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1) . ' minutes';
        } else {
            $hours = floor($duration / 3600);
            $minutes = round(($duration % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    public function isActive()
    {
        return $this->is_active && !$this->logout_at;
    }

    public function updateLastActivity()
    {
        $this->update([
            'last_activity_at' => now()
        ]);
    }

    public function logout()
    {
        $this->update([
            'logout_at' => now(),
            'is_active' => false
        ]);
    }

    public function addAccessedPage($page)
    {
        $pages = $this->accessed_pages ?? [];
        if (!in_array($page, $pages)) {
            $pages[] = $page;
            $this->update([
                'accessed_pages' => $pages,
                'last_activity_at' => now()
            ]);
        }
    }

    public function addDownloadedDocument($documentId, $documentName)
    {
        $downloads = $this->downloaded_documents ?? [];
        $downloads[] = [
            'document_id' => $documentId,
            'document_name' => $documentName,
            'downloaded_at' => now()->toISOString()
        ];
        
        $this->update([
            'downloaded_documents' => $downloads,
            'last_activity_at' => now()
        ]);
    }

    public function getDeviceTypeDisplayName()
    {
        $deviceNames = [
            self::DEVICE_DESKTOP => 'Desktop',
            self::DEVICE_MOBILE => 'Mobile',
            self::DEVICE_TABLET => 'Tablet'
        ];

        return $deviceNames[$this->device_type] ?? $this->device_type;
    }

    public function getBrowserDisplayName()
    {
        $browserNames = [
            'chrome' => 'Chrome',
            'firefox' => 'Firefox',
            'safari' => 'Safari',
            'edge' => 'Edge',
            'opera' => 'Opera'
        ];

        $browser = strtolower($this->browser ?? '');
        foreach ($browserNames as $key => $name) {
            if (strpos($browser, $key) !== false) {
                return $name;
            }
        }

        return $this->browser ?? 'Unknown';
    }

    public function getOsDisplayName()
    {
        $osNames = [
            'windows' => 'Windows',
            'mac' => 'macOS',
            'linux' => 'Linux',
            'android' => 'Android',
            'ios' => 'iOS'
        ];

        $os = strtolower($this->os ?? '');
        foreach ($osNames as $key => $name) {
            if (strpos($os, $key) !== false) {
                return $name;
            }
        }

        return $this->os ?? 'Unknown';
    }

    public function getActivitySummary()
    {
        return [
            'session_id' => $this->session_id,
            'login_time' => $this->login_at->format('M j, Y g:i A'),
            'duration' => $this->getSessionDurationFormatted(),
            'device' => $this->getDeviceTypeDisplayName(),
            'browser' => $this->getBrowserDisplayName(),
            'os' => $this->getOsDisplayName(),
            'ip_address' => $this->ip_address,
            'pages_accessed' => count($this->accessed_pages ?? []),
            'documents_downloaded' => count($this->downloaded_documents ?? []),
            'status' => $this->isActive() ? 'Active' : 'Ended'
        ];
    }

    public function getSecurityInfo()
    {
        return [
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_type' => $this->getDeviceTypeDisplayName(),
            'browser' => $this->getBrowserDisplayName(),
            'os' => $this->getOsDisplayName(),
            'login_location' => $this->getLocationFromIp(), // Would need IP geolocation service
            'session_secure' => $this->isSecureSession()
        ];
    }

    private function isSecureSession()
    {
        // Check if session is secure (HTTPS, recent activity, etc.)
        $lastActivity = $this->last_activity_at ?? $this->login_at;
        $inactiveTime = now()->diffInMinutes($lastActivity);
        
        return $inactiveTime < 30; // Consider secure if active within 30 minutes
    }

    private function getLocationFromIp()
    {
        // This would integrate with an IP geolocation service
        // For now, return a placeholder
        return 'Location not available';
    }
}
