<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDAReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_agent_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:5',
            'remarks' => 'nullable|string'
        ];
    }
}
