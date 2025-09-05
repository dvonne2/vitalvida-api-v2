<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id|unique:staff,user_id',
            'staff_type' => 'required|in:gm,telesales_rep,delivery_agent,coo,finance,admin',
            'state_assigned' => 'nullable|string|max:100',
            'performance_score' => 'nullable|numeric|min:0|max:100',
            'daily_limit' => 'nullable|integer|min:1|max:100',
            'status' => 'sometimes|in:active,inactive,suspended,terminated,on_leave',
            'hire_date' => 'nullable|date|before_or_equal:today',
            'guarantor_info' => 'nullable|array',
            'guarantor_info.name' => 'nullable|string|max:255',
            'guarantor_info.phone' => 'nullable|string|max:20',
            'guarantor_info.relationship' => 'nullable|string|max:100',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'target_orders' => 'nullable|integer|min:0',
            'completed_orders' => 'nullable|integer|min:0',
            'ghosted_orders' => 'nullable|integer|min:0',
            'total_earnings' => 'nullable|numeric|min:0',
            'last_activity_date' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['user_id'] = 'required|exists:users,id|unique:staff,user_id,' . $this->staff;
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required.',
            'user_id.exists' => 'Selected user does not exist.',
            'user_id.unique' => 'This user already has a staff record.',
            'staff_type.required' => 'Staff type is required.',
            'staff_type.in' => 'Invalid staff type selected.',
            'state_assigned.string' => 'State assigned must be a string.',
            'state_assigned.max' => 'State assigned cannot exceed 100 characters.',
            'performance_score.numeric' => 'Performance score must be a number.',
            'performance_score.min' => 'Performance score cannot be negative.',
            'performance_score.max' => 'Performance score cannot exceed 100.',
            'daily_limit.integer' => 'Daily limit must be a whole number.',
            'daily_limit.min' => 'Daily limit must be at least 1.',
            'daily_limit.max' => 'Daily limit cannot exceed 100.',
            'status.in' => 'Invalid staff status.',
            'hire_date.date' => 'Invalid hire date format.',
            'hire_date.before_or_equal' => 'Hire date cannot be in the future.',
            'guarantor_info.array' => 'Guarantor info must be an array.',
            'guarantor_info.name.string' => 'Guarantor name must be a string.',
            'guarantor_info.name.max' => 'Guarantor name cannot exceed 255 characters.',
            'guarantor_info.phone.string' => 'Guarantor phone must be a string.',
            'guarantor_info.phone.max' => 'Guarantor phone cannot exceed 20 characters.',
            'guarantor_info.relationship.string' => 'Guarantor relationship must be a string.',
            'guarantor_info.relationship.max' => 'Guarantor relationship cannot exceed 100 characters.',
            'commission_rate.numeric' => 'Commission rate must be a number.',
            'commission_rate.min' => 'Commission rate cannot be negative.',
            'commission_rate.max' => 'Commission rate cannot exceed 100.',
            'target_orders.integer' => 'Target orders must be a whole number.',
            'target_orders.min' => 'Target orders cannot be negative.',
            'completed_orders.integer' => 'Completed orders must be a whole number.',
            'completed_orders.min' => 'Completed orders cannot be negative.',
            'ghosted_orders.integer' => 'Ghosted orders must be a whole number.',
            'ghosted_orders.min' => 'Ghosted orders cannot be negative.',
            'total_earnings.numeric' => 'Total earnings must be a number.',
            'total_earnings.min' => 'Total earnings cannot be negative.',
            'last_activity_date.date' => 'Invalid last activity date format.',
            'is_active.boolean' => 'Active status must be true or false.',
            'notes.string' => 'Notes must be a string.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'staff_type' => 'staff type',
            'state_assigned' => 'state assigned',
            'performance_score' => 'performance score',
            'daily_limit' => 'daily limit',
            'status' => 'staff status',
            'hire_date' => 'hire date',
            'guarantor_info' => 'guarantor information',
            'guarantor_info.name' => 'guarantor name',
            'guarantor_info.phone' => 'guarantor phone',
            'guarantor_info.relationship' => 'guarantor relationship',
            'commission_rate' => 'commission rate',
            'target_orders' => 'target orders',
            'completed_orders' => 'completed orders',
            'ghosted_orders' => 'ghosted orders',
            'total_earnings' => 'total earnings',
            'last_activity_date' => 'last activity date',
            'is_active' => 'active status',
            'notes' => 'notes',
        ];
    }
}
