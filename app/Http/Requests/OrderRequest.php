<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'source' => 'required|in:meta_ad,instagram,whatsapp,repeat_buyer,manual,referral,organic',
            'delivery_address' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'state' => 'required|string|max:100',
            'assigned_telesales_id' => 'nullable|exists:users,id',
            'assigned_da_id' => 'nullable|exists:users,id',
            'delivery_date' => 'nullable|date|after:today',
            'delivery_notes' => 'nullable|string|max:1000',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = array_merge($rules, [
                'status' => 'sometimes|in:pending,confirmed,processing,ready_for_delivery,assigned,in_transit,delivered,cancelled,ghosted',
                'payment_status' => 'sometimes|in:pending,confirmed,failed,refunded,disputed',
                'otp_verified' => 'sometimes|boolean',
                'is_ghosted' => 'sometimes|boolean',
                'ghost_reason' => 'nullable|string|max:500',
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
            'customer_name.required' => 'Customer name is required.',
            'customer_phone.required' => 'Customer phone number is required.',
            'customer_email.email' => 'Please provide a valid email address.',
            'source.required' => 'Order source is required.',
            'source.in' => 'Invalid order source selected.',
            'delivery_address.required' => 'Delivery address is required.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.product.required' => 'Product name is required.',
            'items.*.quantity.required' => 'Quantity is required.',
            'items.*.quantity.integer' => 'Quantity must be a whole number.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.price.required' => 'Price is required.',
            'items.*.price.numeric' => 'Price must be a number.',
            'items.*.price.min' => 'Price cannot be negative.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a number.',
            'total_amount.min' => 'Total amount cannot be negative.',
            'state.required' => 'State is required.',
            'assigned_telesales_id.exists' => 'Selected telesales representative does not exist.',
            'assigned_da_id.exists' => 'Selected delivery agent does not exist.',
            'delivery_date.date' => 'Invalid delivery date format.',
            'delivery_date.after' => 'Delivery date must be in the future.',
            'status.in' => 'Invalid order status.',
            'payment_status.in' => 'Invalid payment status.',
            'otp_verified.boolean' => 'OTP verification must be true or false.',
            'is_ghosted.boolean' => 'Ghosted status must be true or false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'customer name',
            'customer_phone' => 'customer phone',
            'customer_email' => 'customer email',
            'source' => 'order source',
            'delivery_address' => 'delivery address',
            'items' => 'order items',
            'items.*.product' => 'product name',
            'items.*.quantity' => 'quantity',
            'items.*.price' => 'price',
            'total_amount' => 'total amount',
            'state' => 'state',
            'assigned_telesales_id' => 'telesales representative',
            'assigned_da_id' => 'delivery agent',
            'delivery_date' => 'delivery date',
            'delivery_notes' => 'delivery notes',
            'status' => 'order status',
            'payment_status' => 'payment status',
            'otp_verified' => 'OTP verification',
            'is_ghosted' => 'ghosted status',
            'ghost_reason' => 'ghost reason',
        ];
    }
}
