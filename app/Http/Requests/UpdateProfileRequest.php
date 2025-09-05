<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        
        return [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', new ValidPhoneNumber(), Rule::unique('users')->ignore($user->id)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'state' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'country' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'postal_code' => ['nullable', 'string', 'max:20', 'alpha_num'],
            'emergency_contact' => ['nullable', 'string', 'max:255', 'min:2'],
            'emergency_phone' => ['nullable', new ValidPhoneNumber()],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name must be at least 2 characters long.',
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'date_of_birth.after' => 'Please enter a valid date of birth.',
            'city.alpha_dash' => 'City name can only contain letters, numbers, dashes and underscores.',
            'postal_code.alpha_num' => 'Postal code can only contain letters and numbers.',
            'bio.max' => 'Bio cannot exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'date_of_birth' => 'date of birth',
            'emergency_contact' => 'emergency contact name',
            'emergency_phone' => 'emergency contact phone',
            'postal_code' => 'postal code',
        ];
    }
}
