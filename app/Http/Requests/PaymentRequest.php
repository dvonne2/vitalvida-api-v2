<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
        $rules = [
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:moniepoint,cash,bank_transfer,card',
            'transaction_reference' => 'required|string|max:255|unique:payments,transaction_reference',
            'moniepoint_reference' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,confirmed,failed,refunded,disputed',
            'paid_at' => 'nullable|date',
            'verified_at' => 'nullable|date',
            'verified_by' => 'nullable|exists:users,id',
            'moniepoint_response' => 'nullable|array',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['transaction_reference'] = 'required|string|max:255|unique:payments,transaction_reference,' . $this->payment;
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required.',
            'order_id.exists' => 'Selected order does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount cannot be negative.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
            'transaction_reference.required' => 'Transaction reference is required.',
            'transaction_reference.string' => 'Transaction reference must be a string.',
            'transaction_reference.max' => 'Transaction reference cannot exceed 255 characters.',
            'transaction_reference.unique' => 'Transaction reference already exists.',
            'moniepoint_reference.string' => 'Moniepoint reference must be a string.',
            'moniepoint_reference.max' => 'Moniepoint reference cannot exceed 255 characters.',
            'status.in' => 'Invalid payment status.',
            'paid_at.date' => 'Invalid paid date format.',
            'verified_at.date' => 'Invalid verification date format.',
            'verified_by.exists' => 'Selected verifier does not exist.',
            'moniepoint_response.array' => 'Moniepoint response must be an array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'order_id' => 'order',
            'amount' => 'payment amount',
            'payment_method' => 'payment method',
            'transaction_reference' => 'transaction reference',
            'moniepoint_reference' => 'moniepoint reference',
            'status' => 'payment status',
            'paid_at' => 'paid date',
            'verified_at' => 'verification date',
            'verified_by' => 'verifier',
            'moniepoint_response' => 'moniepoint response',
        ];
    }
}
