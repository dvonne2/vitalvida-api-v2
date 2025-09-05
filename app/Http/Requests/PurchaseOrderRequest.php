<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
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
            'supplier_id' => 'required|exists:suppliers,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:date',
            'payment_terms' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string'
        ];

        // Additional rules for updates
        if ($this->isMethod('PUT')) {
            $rules = array_merge($rules, [
                'supplier_id' => 'sometimes|exists:suppliers,id',
                'date' => 'sometimes|date',
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
            'supplier_id.required' => 'A supplier must be selected.',
            'supplier_id.exists' => 'The selected supplier is invalid.',
            'delivery_agent_id.exists' => 'The selected delivery agent is invalid.',
            'date.required' => 'Order date is required.',
            'date.date' => 'Order date must be a valid date.',
            'expected_date.after_or_equal' => 'Expected date must be on or after the order date.',
            'items.required' => 'At least one item must be added to the purchase order.',
            'items.min' => 'At least one item must be added to the purchase order.',
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
            // Check if supplier is active
            if ($this->supplier_id) {
                $supplier = \App\Models\Supplier::find($this->supplier_id);
                if ($supplier && !$supplier->isActive()) {
                    $validator->errors()->add('supplier_id', 'The selected supplier is inactive.');
                }
            }

            // Check if delivery agent is active
            if ($this->delivery_agent_id) {
                $agent = \App\Models\DeliveryAgent::find($this->delivery_agent_id);
                if ($agent && !$agent->isActive()) {
                    $validator->errors()->add('delivery_agent_id', 'The selected delivery agent is inactive.');
                }
            }

            // Validate total order value
            if ($this->items) {
                $totalValue = 0;
                foreach ($this->items as $item) {
                    $totalValue += ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
                }

                if ($totalValue > 1000000) { // 1 million limit
                    $validator->errors()->add('items', 'Total order value cannot exceed ₦1,000,000.');
                }
            }
        });
    }
} 