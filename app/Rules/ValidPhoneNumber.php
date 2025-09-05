<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $value);
        
        // Check if it's a valid format
        if (!preg_match('/^\+?[1-9]\d{6,14}$/', $cleaned)) {
            $fail('The phone number format is invalid.');
            return;
        }

        // Check for common invalid patterns
        $invalidPatterns = [
            '/^(\d)\1+$/', // All same digits
            '/^(0+)$/',    // All zeros
        ];

        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                $fail('The phone number format is invalid.');
                return;
            }
        }
    }
}
