<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add proper authorization logic later
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'source_bin_id' => 'required|integer|exists:bins,id',
            'destination_bin_id' => 'required|integer|exists:bins,id|different:source_bin_id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10000',
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'destination_bin_id.different' => 'Destination bin must be different from source bin',
            'quantity.min' => 'Quantity must be at least 1',
            'quantity.max' => 'Quantity cannot exceed 10,000 items',
            'source_bin_id.exists' => 'The selected source bin does not exist',
            'destination_bin_id.exists' => 'The selected destination bin does not exist',
            'product_id.exists' => 'The selected product does not exist'
        ];
    }
}
