<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize()
    {
        // Only authenticated users can create movements
        return true; // Change to auth()->check() when auth is ready
    }

    public function rules()
    {
        return [
            'product_id' => 'required|exists:products,id',
            'from_bin_id' => 'required|exists:bin_stocks,id',
            'to_bin_id' => 'required|exists:bin_stocks,id|different:from_bin_id',
            'quantity' => 'required|integer|min:1|max:1000',
            'reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages()
    {
        return [
            'to_bin_id.different' => 'Source and destination bins must be different.',
            'quantity.max' => 'Cannot transfer more than 1000 items at once.',
            'reason.min' => 'Please provide a detailed reason (at least 10 characters).',
        ];
    }
}
