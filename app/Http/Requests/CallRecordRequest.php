<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CallRecordRequest extends FormRequest
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
            'outcome' => 'required|in:confirmed,not_interested,callback',
            'notes' => 'nullable|string|max:1000',
            'callback_time' => 'nullable|date|after:now',
            'call_duration' => 'nullable|integer|min:0|max:3600'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'outcome.required' => 'Please select a call outcome.',
            'outcome.in' => 'Invalid call outcome selected.',
            'callback_time.after' => 'Callback time must be in the future.',
            'call_duration.max' => 'Call duration cannot exceed 1 hour.'
        ];
    }
} 