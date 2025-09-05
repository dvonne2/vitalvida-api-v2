<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        $checks = [
            'min_length' => strlen($value) >= 8,
            'has_lowercase' => preg_match('/[a-z]/', $value),
            'has_uppercase' => preg_match('/[A-Z]/', $value),
            'has_number' => preg_match('/\d/', $value),
            'has_special' => preg_match('/[^a-zA-Z\d]/', $value),
        ];

        $failed = [];
        
        if (!$checks['min_length']) $failed[] = 'at least 8 characters';
        if (!$checks['has_lowercase']) $failed[] = 'one lowercase letter';
        if (!$checks['has_uppercase']) $failed[] = 'one uppercase letter';
        if (!$checks['has_number']) $failed[] = 'one number';
        
        if (!empty($failed)) {
            $fail("The password must contain " . implode(', ', $failed) . ".");
        }
    }
}
