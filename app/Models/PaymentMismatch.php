<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMismatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'mismatch_id',
        'order_id',
        'payment_id',
        'entered_phone',
        'entered_order_id',
        'actual_phone',
        'actual_order_id',
        'mismatch_type',
        'payment_amount',
        'webhook_payload',
        'investigation_required',
        'investigation_notes',
        'investigated_at',
        'investigated_by',
        'corrective_action',
        'resolution_type',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
        'penalty_amount',
        'penalty_applied'
    ];

    protected $casts = [
        'webhook_payload' => 'array',
        'payment_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'investigation_required' => 'boolean',
        'penalty_applied' => 'boolean',
        'investigated_at' => 'datetime',
        'resolved_at' => 'datetime'
    ];

    protected $appends = [
        'is_resolved',
        'days_since_created',
        'severity_level'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function investigatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Accessors
    public function getIsResolvedAttribute(): bool
    {
        return $this->resolved_at !== null;
    }

    public function getDaysSinceCreatedAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    public function getSeverityLevelAttribute(): string
    {
        $days = $this->days_since_created;
        
        if ($days > 7) {
            return 'critical';
        } elseif ($days > 3) {
            return 'high';
        } elseif ($days > 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('investigation_required', true)
                    ->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('mismatch_type', $type);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('created_at', '<', now()->subDays(3));
    }

    public function scopeWithPenalty($query)
    {
        return $query->where('penalty_applied', true);
    }

    // Business Methods
    public function markAsInvestigated(User $user, string $notes, string $action): void
    {
        $this->update([
            'investigation_notes' => $notes,
            'investigated_at' => now(),
            'investigated_by' => $user->id,
            'corrective_action' => $action
        ]);
    }

    public function resolve(User $user, string $resolutionType, string $notes): void
    {
        $this->update([
            'resolution_type' => $resolutionType,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'investigation_required' => false
        ]);

        // Apply penalty if needed
        if ($this->shouldApplyPenalty($resolutionType)) {
            $this->applyPenalty();
        }
    }

    public function shouldApplyPenalty(string $resolutionType): bool
    {
        return in_array($resolutionType, ['corrected', 'customer_error']);
    }

    public function applyPenalty(): void
    {
        if (!$this->penalty_applied) {
            // Create strike record for the accountant who made the error
            $accountant = $this->order->payments()
                ->where('verified_by', '!=', null)
                ->first()?->verifiedBy;

            if ($accountant) {
                \App\Models\Strike::create([
                    'user_id' => $accountant->id,
                    'violation_type' => 'payment_mismatch',
                    'violation_description' => "Payment mismatch for Order {$this->order->order_number}",
                    'penalty_amount' => $this->penalty_amount,
                    'strike_number' => $accountant->strikes()->count() + 1,
                    'issued_by' => 'system'
                ]);

                // Update user strike count
                $accountant->increment('strike_count');
            }

            $this->update(['penalty_applied' => true]);
        }
    }

    public function getDetailedSummary(): array
    {
        return [
            'mismatch_id' => $this->mismatch_id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer->name,
            'mismatch_type' => $this->mismatch_type,
            'discrepancies' => [
                'phone' => [
                    'entered' => $this->entered_phone,
                    'actual' => $this->actual_phone,
                    'match' => $this->entered_phone === $this->actual_phone
                ],
                'order_id' => [
                    'entered' => $this->entered_order_id,
                    'actual' => $this->actual_order_id,
                    'match' => $this->entered_order_id === $this->actual_order_id
                ]
            ],
            'payment_amount' => $this->payment_amount,
            'penalty_amount' => $this->penalty_amount,
            'severity_level' => $this->severity_level,
            'days_since_created' => $this->days_since_created,
            'investigation_status' => [
                'required' => $this->investigation_required,
                'completed' => $this->investigated_at !== null,
                'resolved' => $this->resolved_at !== null,
                'penalty_applied' => $this->penalty_applied
            ],
            'timeline' => [
                'created_at' => $this->created_at,
                'investigated_at' => $this->investigated_at,
                'resolved_at' => $this->resolved_at
            ]
        ];
    }
}
