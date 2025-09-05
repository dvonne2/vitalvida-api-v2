<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeductInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('inventory.deduct');
    }

    public function rules(): array
    {
        return [
            'order_number' => 'required|string|exists:orders,order_number',
            'item_id' => 'required|string',
            'bin_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|in:package_dispatch,order_fulfillment,quality_control,return_processing',
            'warehouse_id' => 'nullable|string'
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => auth()->id()
        ]);
    }
}
