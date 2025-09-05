<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactoryReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:5',
            'remarks' => 'nullable|string'
        ];
    }
}
