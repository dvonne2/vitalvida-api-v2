<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'item_id' => 'required|exists:items,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'adjustment_type' => 'required|in:damage,loss,found,theft,expiry,quality_control,inventory_count,system_adjustment',
            'quantity' => 'required|integer',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'date' => 'required|date'
        ];

        // Additional rules for updates
        if ($this->isMethod('PUT')) {
            $rules = array_merge($rules, [
                'item_id' => 'sometimes|exists:items,id',
                'adjustment_type' => 'sometimes|in:damage,loss,found,theft,expiry,quality_control,inventory_count,system_adjustment',
                'quantity' => 'sometimes|integer',
                'reason' => 'sometimes|string|max:255',
                'date' => 'sometimes|date'
            ]);
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_id.required' => 'Item is required.',
            'item_id.exists' => 'The selected item is invalid.',
            'delivery_agent_id.exists' => 'The selected delivery agent is invalid.',
            'employee_id.exists' => 'The selected employee is invalid.',
            'adjustment_type.required' => 'Adjustment type is required.',
            'adjustment_type.in' => 'The selected adjustment type is invalid.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'reason.required' => 'Reason is required.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason cannot exceed 255 characters.',
            'date.required' => 'Date is required.',
            'date.date' => 'Date must be a valid date.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if item is active
            if ($this->item_id) {
                $item = \App\Models\Item::find($this->item_id);
                if ($item && !$item->is_active) {
                    $validator->errors()->add('item_id', 'The selected item is inactive.');
                }
            }

            // Check if delivery agent is active
            if ($this->delivery_agent_id) {
                $agent = \App\Models\DeliveryAgent::find($this->delivery_agent_id);
                if ($agent && !$agent->isActive()) {
                    $validator->errors()->add('delivery_agent_id', 'The selected delivery agent is inactive.');
                }
            }

            // Validate stock availability for decreases
            if ($this->quantity < 0 && $this->item_id) {
                $item = \App\Models\Item::find($this->item_id);
                if ($item && $item->stock_quantity < abs($this->quantity)) {
                    $validator->errors()->add('quantity', "Insufficient stock. Available: {$item->stock_quantity}, Required: " . abs($this->quantity));
                }
            }

            // Validate adjustment type specific rules
            if ($this->adjustment_type) {
                switch ($this->adjustment_type) {
                    case 'damage':
                    case 'loss':
                    case 'theft':
                        if ($this->quantity > 0) {
                            $validator->errors()->add('quantity', 'Quantity must be negative for damage, loss, or theft adjustments.');
                        }
                        break;
                    case 'found':
                        if ($this->quantity < 0) {
                            $validator->errors()->add('quantity', 'Quantity must be positive for found adjustments.');
                        }
                        break;
                }
            }

            // Validate date is not in the future
            if ($this->date && $this->date > now()->toDateString()) {
                $validator->errors()->add('date', 'Adjustment date cannot be in the future.');
            }
        });
    }
} 