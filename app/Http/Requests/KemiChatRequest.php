<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KemiChatRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:1000',
            'type' => 'required|in:user,kemi,system',
            'context' => 'nullable|array',
            'order_id' => 'nullable|exists:orders,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Message cannot exceed 1000 characters.',
            'type.required' => 'Message type is required.',
            'type.in' => 'Invalid message type specified.',
            'order_id.exists' => 'Referenced order does not exist.'
        ];
    }
} 