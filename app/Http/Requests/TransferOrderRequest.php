<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferOrderRequest extends FormRequest
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
            'from_location' => 'required|string|max:100',
            'to_location' => 'required|string|max:100|different:from_location',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'transfer_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0'
        ];

        // Additional rules for updates
        if ($this->isMethod('PUT')) {
            $rules = array_merge($rules, [
                'from_location' => 'sometimes|string|max:100',
                'to_location' => 'sometimes|string|max:100|different:from_location',
                'transfer_date' => 'sometimes|date',
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
            'from_location.required' => 'Source location is required.',
            'from_location.string' => 'Source location must be a string.',
            'from_location.max' => 'Source location cannot exceed 100 characters.',
            'to_location.required' => 'Destination location is required.',
            'to_location.string' => 'Destination location must be a string.',
            'to_location.max' => 'Destination location cannot exceed 100 characters.',
            'to_location.different' => 'Destination location must be different from source location.',
            'delivery_agent_id.exists' => 'The selected delivery agent is invalid.',
            'transfer_date.required' => 'Transfer date is required.',
            'transfer_date.date' => 'Transfer date must be a valid date.',
            'expected_date.after_or_equal' => 'Expected date must be on or after the transfer date.',
            'items.required' => 'At least one item must be added to the transfer order.',
            'items.min' => 'At least one item must be added to the transfer order.',
            'items.*.item_id.required' => 'Item is required.',
            'items.*.item_id.exists' => 'The selected item is invalid.',
            'items.*.quantity.required' => 'Quantity is required.',
            'items.*.quantity.integer' => 'Quantity must be a whole number.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_cost.required' => 'Unit cost is required.',
            'items.*.unit_cost.numeric' => 'Unit cost must be a number.',
            'items.*.unit_cost.min' => 'Unit cost must be at least 0.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if delivery agent is active
            if ($this->delivery_agent_id) {
                $agent = \App\Models\DeliveryAgent::find($this->delivery_agent_id);
                if ($agent && !$agent->isActive()) {
                    $validator->errors()->add('delivery_agent_id', 'The selected delivery agent is inactive.');
                }
            }

            // Validate stock availability for each item
            if ($this->items && $this->from_location) {
                foreach ($this->items as $index => $item) {
                    $inventoryItem = \App\Models\Item::where('id', $item['item_id'])
                        ->where('location', $this->from_location)
                        ->first();

                    if (!$inventoryItem) {
                        $validator->errors()->add("items.{$index}.item_id", "Item not found at source location: {$this->from_location}");
                    } elseif ($inventoryItem->stock_quantity < $item['quantity']) {
                        $validator->errors()->add("items.{$index}.quantity", "Insufficient stock. Available: {$inventoryItem->stock_quantity}, Required: {$item['quantity']}");
                    }
                }
            }

            // Validate total transfer value
            if ($this->items) {
                $totalValue = 0;
                foreach ($this->items as $item) {
                    $totalValue += ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
                }

                if ($totalValue > 500000) { // 500k limit for transfers
                    $validator->errors()->add('items', 'Total transfer value cannot exceed ₦500,000.');
                }
            }
        });
    }
} 