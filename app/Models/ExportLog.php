<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'export_type',
        'format',
        'filters_applied',
        'integrity_hash',
        'export_id',
        'ip_address',
        'user_agent',
        'records_exported',
        'emailed_to_compliance',
        'downloaded_at'
    ];

    protected $casts = [
        'filters_applied' => 'array',
        'emailed_to_compliance' => 'boolean',
        'downloaded_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateExportId()
    {
        $count = self::count() + 1;
        return 'VV-EXP-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    public static function detectAnomalies($userId, $timeWindow = 60)
    {
        $recentExports = self::where('user_id', $userId)
            ->where('downloaded_at', '>=', now()->subMinutes($timeWindow))
            ->count();

        return [
            'is_anomaly' => $recentExports >= 5,
            'recent_count' => $recentExports,
            'threshold' => 5
        ];
    }
}
