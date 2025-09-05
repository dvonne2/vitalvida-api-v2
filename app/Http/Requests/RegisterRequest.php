<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s]+$/' // Only letters and spaces
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns', // Strict email validation
                'max:255',
                'unique:users,email'
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{10,15}$/', // 10-15 digits
                'unique:users,phone'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                // Strong password: uppercase, lowercase, number, special character
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in(['user', 'manager', 'admin', 'production', 'inventory', 'telesales', 'DA', 'accountant', 'CFO', 'CEO'])
            ]
        ];
    }

    public function messages()
    {
        return [
            'name.regex' => 'Name can only contain letters and spaces.',
            'email.email' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number must be 10-15 digits.',
            'password.regex' => 'Password must contain uppercase, lowercase, number, and special character.',
            'role.in' => 'Invalid role selected.'
        ];
    }
}
