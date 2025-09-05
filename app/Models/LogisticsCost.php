<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LogisticsCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_type',
        'origin_location',
        'origin_phone',
        'destination_location',
        'destination_phone',
        'items_description',
        'quantity',
        'transport_company',
        'transport_phone',
        'storekeeper_phone',
        'total_cost',
        'storekeeper_fee',
        'transport_fare',
        'proof_of_payment_path',
        'approved_by',
        'status'
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'storekeeper_fee' => 'decimal:2',
        'transport_fare' => 'decimal:2'
    ];

    // Relationships
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fileUploads(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'uploadable');
    }

    public function escalations(): MorphMany
    {
        return $this->morphMany(Escalation::class, 'escalatable');
    }

    // Business Logic Methods
    
    /**
     * Check if this logistics cost requires escalation
     */
    public function requiresEscalation(): bool
    {
        // Storekeeper fee over ₦1,000 or transport fare over ₦1,500
        return ($this->storekeeper_fee > 1000) || ($this->transport_fare > 1500);
    }

    /**
     * Get the required approval tier based on total cost
     */
    public function getApprovalTier(): string
    {
        return match(true) {
            $this->total_cost <= 5000 => 'fc',
            $this->total_cost <= 10000 => 'gm',
            default => 'ceo'
        };
    }

    /**
     * Calculate cost per unit
     */
    public function getCostPerUnit(): float
    {
        return $this->quantity > 0 ? $this->total_cost / $this->quantity : 0;
    }

    /**
     * Check if cost is within acceptable range for transfer type
     */
    public function isWithinAcceptableRange(): bool
    {
        $maxCosts = [
            'supplier_to_im' => 15000,
            'im_to_da' => 8000,
            'da_to_da' => 5000,
            'da_to_factory' => 12000
        ];

        return $this->total_cost <= ($maxCosts[$this->transfer_type] ?? 10000);
    }

    /**
     * Get transfer type display name
     */
    public function getTransferTypeDisplay(): string
    {
        return match($this->transfer_type) {
            'supplier_to_im' => 'Supplier to Inventory Manager',
            'im_to_da' => 'Inventory Manager to Delivery Agent',
            'da_to_da' => 'Delivery Agent to Delivery Agent',
            'da_to_factory' => 'Delivery Agent to Factory',
            default => 'Unknown Transfer Type'
        };
    }

    /**
     * Create escalation if required
     */
    public function createEscalationIfRequired(): ?Escalation
    {
        if ($this->requiresEscalation()) {
            return $this->escalations()->create([
                'escalation_id' => 'VV-ESC-' . str_pad(Escalation::count() + 1, 6, '0', STR_PAD_LEFT),
                'escalation_type' => 'logistics_cost',
                'created_by' => auth()->id(),
                'priority' => 'high',
                'title' => 'High Logistics Cost Escalation',
                'description' => "Logistics cost exceeds threshold - Storekeeper: ₦{$this->storekeeper_fee}, Transport: ₦{$this->transport_fare}",
                'amount_involved' => $this->total_cost,
                'required_approval' => $this->getApprovalTier()
            ]);
        }
        return null;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRequiresEscalation($query)
    {
        return $query->where(function($q) {
            $q->where('storekeeper_fee', '>', 1000)
              ->orWhere('transport_fare', '>', 1500);
        });
    }

    public function scopeByTransferType($query, $type)
    {
        return $query->where('transfer_type', $type);
    }
}
